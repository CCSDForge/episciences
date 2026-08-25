<?php

declare(strict_types=1);

require_once __DIR__ . '/Solr/BootstrapsSolrEnvironment.php';

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Enqueue\DbalEnqueueFailureStore;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Episciences\Solr\Indexing\Messenger\MessengerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Minimal, hand-rolled equivalent of Symfony FrameworkBundle's
 * messenger:failed:* console commands (not available here — this app has no
 * Kernel/bundle system to auto-register them, see scripts/console.php).
 * Also fills the "no audit trail" gap left by legacy INDEX_QUEUE, whose rows
 * simply disappear on success and get stuck forever on transport failure —
 * failed messages here persist in the failure transport until explicitly
 * retried or inspected.
 */
class SolrQueueCommand extends Command
{
    use BootstrapsSolrEnvironment;

    protected static $defaultName = 'solr:queue';

    protected function configure(): void
    {
        $this
            ->setDescription('Inspect and manage the Solr indexing queue')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show pending and failed message counts.')
            ->addOption('list-failed', null, InputOption::VALUE_NONE, 'List failed messages.')
            ->addOption('retry', null, InputOption::VALUE_REQUIRED, 'Retry the failed message with this id, synchronously, in this process.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit for --list-failed / --list-dispatch-failures.', 50)
            ->addOption('setup', null, InputOption::VALUE_NONE, "Create the messenger_messages/messenger_failed/solr_enqueue_failures tables if they don't exist yet (one-time, per environment).")
            ->addOption('list-dispatch-failures', null, InputOption::VALUE_NONE, 'List enqueue calls that failed even after their bounded retry (no message row was ever created for these — see solr_enqueue_failures).')
            ->addOption('retry-dispatch-failure', null, InputOption::VALUE_REQUIRED, 'Re-attempt the recorded dispatch failure with this id; removes it from solr_enqueue_failures on success.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Solr indexing queue');

        $actions = array_keys(array_filter([
            'stats' => (bool)$input->getOption('stats'),
            'list-failed' => (bool)$input->getOption('list-failed'),
            'retry' => $input->getOption('retry') !== null,
            'setup' => (bool)$input->getOption('setup'),
            'list-dispatch-failures' => (bool)$input->getOption('list-dispatch-failures'),
            'retry-dispatch-failure' => $input->getOption('retry-dispatch-failure') !== null,
        ]));

        if (count($actions) !== 1) {
            $io->error('Exactly one of --stats, --list-failed, --retry, --setup, --list-dispatch-failures or --retry-dispatch-failure is required.');

            return Command::FAILURE;
        }

        $this->bootstrapSolrEnvironment();
        $logger = $this->createSolrLogger($io, 'solrQueue');

        $connection = $this->createDbalConnection();
        $transport = MessengerFactory::createTransport($connection);
        $failureTransport = MessengerFactory::createFailureTransport($connection);
        $dispatchFailureStore = new DbalEnqueueFailureStore($connection);

        return match ($actions[0]) {
            'stats' => $this->showStats($transport, $failureTransport, $io),
            'list-failed' => $this->listFailed($failureTransport, (int)$input->getOption('limit'), $io),
            'retry' => $this->retry($failureTransport, (string)$input->getOption('retry'), $io, $logger),
            'setup' => $this->setup($transport, $failureTransport, $dispatchFailureStore, $connection, $io),
            'list-dispatch-failures' => $this->listDispatchFailures($dispatchFailureStore, (int)$input->getOption('limit'), $io),
            'retry-dispatch-failure' => $this->retryDispatchFailure(
                $transport,
                $dispatchFailureStore,
                (int)$input->getOption('retry-dispatch-failure'),
                $io,
                $logger
            ),
        };
    }

    /**
     * Creates the transport tables. On MySQL, the app's DB user commonly has
     * no CREATE/ALTER privilege in staging/prod (only SELECT/INSERT/UPDATE/
     * DELETE) — in that case, print the exact DDL so a DBA can run it
     * manually instead of failing with an opaque permission error.
     */
    private function setup(
        DoctrineTransport $transport,
        DoctrineTransport $failureTransport,
        DbalEnqueueFailureStore $dispatchFailureStore,
        DbalConnection $connection,
        SymfonyStyle $io
    ): int {
        try {
            $transport->setup();
            $failureTransport->setup();
            $dispatchFailureStore->setup();
        } catch (\Throwable $e) {
            $io->error(sprintf('Automatic table creation failed: %s', $e->getMessage()));
            $io->note('This is expected if the database user lacks CREATE/ALTER privileges (common in staging/prod). Ask a DBA to run the following SQL manually, then re-run this command to confirm:');

            foreach ([$transport, $failureTransport] as $tableTransport) {
                foreach ($this->buildCreateTableSql($tableTransport, $connection) as $sql) {
                    $io->writeln($sql . ';');
                }
                $io->newLine();
            }

            foreach ($dispatchFailureStore->buildCreateTableSql() as $sql) {
                $io->writeln($sql . ';');
            }

            return Command::FAILURE;
        }

        $io->success('messenger_messages / messenger_failed / solr_enqueue_failures tables are ready.');

        return Command::SUCCESS;
    }

    /**
     * Builds the DDL from the bridge's own public configureSchema() instead of
     * a hand-typed column list, so this can never drift from what setup()
     * actually creates on a symfony/doctrine-messenger version bump — the
     * table shape comes from the library itself, not a copy of it.
     *
     * @return list<string>
     */
    private function buildCreateTableSql(DoctrineTransport $transport, DbalConnection $connection): array
    {
        $schema = $transport->configureSchema(new Schema(), $connection, static fn () => true);
        $table = current($schema->getTables());

        return $connection->getDatabasePlatform()->getCreateTableSQL($table);
    }

    private function showStats(DoctrineTransport $transport, DoctrineTransport $failureTransport, SymfonyStyle $io): int
    {
        $io->table(['Queue', 'Pending messages'], [
            [MessengerFactory::TRANSPORT_NAME, $transport->getMessageCount()],
            [MessengerFactory::TRANSPORT_NAME . ' (failed)', $failureTransport->getMessageCount()],
        ]);

        return Command::SUCCESS;
    }

    private function listFailed(DoctrineTransport $failureTransport, int $limit, SymfonyStyle $io): int
    {
        $rows = [];
        foreach ($failureTransport->all($limit) as $envelope) {
            $errorDetails = $envelope->last(ErrorDetailsStamp::class);
            $rows[] = [
                $envelope->last(TransportMessageIdStamp::class)?->getId(),
                $envelope->getMessage()::class,
                $errorDetails?->getExceptionClass() ?? '',
                $errorDetails?->getExceptionMessage() ?? '',
            ];
        }

        if ($rows === []) {
            $io->success('No failed messages.');

            return Command::SUCCESS;
        }

        $io->table(['ID', 'Message', 'Exception', 'Error'], $rows);

        return Command::SUCCESS;
    }

    private function retry(DoctrineTransport $failureTransport, string $id, SymfonyStyle $io, \Monolog\Logger $logger): int
    {
        $envelope = $failureTransport->find($id);

        if ($envelope === null) {
            $io->error(sprintf('No failed message found with id "%s".', $id));

            return Command::FAILURE;
        }

        $indexHandler = new IndexPaperMessageHandler($this->createDocumentBuilder(), new SolariumClientFactory());
        $deleteHandler = new DeletePaperMessageHandler(new SolariumClientFactory());
        $handleBus = MessengerFactory::createHandleBus($indexHandler, $deleteHandler);

        try {
            $handleBus->dispatch($envelope->withoutAll(ErrorDetailsStamp::class));
            $failureTransport->ack($envelope);
            $io->success(sprintf('Message "%s" retried successfully and removed from the failure queue.', $id));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $logger->error(sprintf('solr:queue --retry=%s failed again: %s', $id, $e->getMessage()));
            $io->error(sprintf('Retry failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function listDispatchFailures(DbalEnqueueFailureStore $dispatchFailureStore, int $limit, SymfonyStyle $io): int
    {
        $rows = $dispatchFailureStore->all($limit);

        if ($rows === []) {
            $io->success('No dispatch failures.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Action', 'Docid', 'Priority/Query', 'Attempts', 'Last error', 'Created at'],
            array_map(
                static fn (array $row): array => [
                    $row['id'],
                    $row['action'],
                    $row['docid'],
                    $row['action'] === 'index' ? $row['priority'] : $row['solr_query'],
                    $row['attempts'],
                    $row['last_error'],
                    $row['created_at'],
                ],
                $rows
            )
        );

        return Command::SUCCESS;
    }

    /**
     * Re-attempts a producer-side dispatch that never made it into
     * messenger_messages (see SolrIndexQueuePort). Unlike --retry, which
     * replays an envelope that already exists in the failure transport, this
     * re-enqueues from the raw docid/action recorded at the time of failure.
     */
    private function retryDispatchFailure(
        DoctrineTransport $transport,
        DbalEnqueueFailureStore $dispatchFailureStore,
        int $id,
        SymfonyStyle $io,
        \Monolog\Logger $logger
    ): int {
        $row = $dispatchFailureStore->find($id);

        if ($row === null) {
            $io->error(sprintf('No dispatch failure found with id "%d".', $id));

            return Command::FAILURE;
        }

        $sendBus = MessengerFactory::createSendBus($transport);
        $docId = $row['docid'] !== null ? (int)$row['docid'] : null;

        try {
            if ($row['action'] === 'index') {
                $sendBus->dispatch(new IndexPaperMessage((int)$docId, (int)$row['priority']));
            } else {
                $sendBus->dispatch(new DeletePaperMessage($docId, $row['solr_query']));
            }
        } catch (\Throwable $e) {
            $dispatchFailureStore->markRetryFailed($id, $e->getMessage());
            $logger->error(sprintf('solr:queue --retry-dispatch-failure=%d failed again: %s', $id, $e->getMessage()));
            $io->error(sprintf('Retry failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $dispatchFailureStore->delete($id);
        $io->success(sprintf('Dispatch failure #%d re-enqueued successfully and removed.', $id));

        return Command::SUCCESS;
    }
}
