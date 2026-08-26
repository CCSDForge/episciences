<?php

declare(strict_types=1);

require_once __DIR__ . '/Messenger/TransportProfileRegistry.php';

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Episciences\Messenger\Bus\BusFactory;
use Episciences\Messenger\Dbal\DbalConnectionFactory;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;
use Episciences\Messenger\Log\CliLoggerFactory;
use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Messenger\Transport\TransportFactory;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Minimal, hand-rolled equivalent of Symfony FrameworkBundle's
 * messenger:failed:* console commands (not available here — this app has no
 * Kernel/bundle system to auto-register them). Operates on one Messenger
 * queue, selected by --transport via TransportProfileRegistry.
 */
class EpisciencesQueueCommand extends Command
{
    protected static $defaultName = 'episciences:queue';

    protected function configure(): void
    {
        $this
            ->setDescription('Inspect and manage one Messenger queue (see --transport).')
            ->addOption('transport', null, InputOption::VALUE_REQUIRED, 'Which queue to operate on: ' . implode(', ', TransportProfileRegistry::names()) . '.')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show pending and failed message counts.')
            ->addOption('list-failed', null, InputOption::VALUE_NONE, 'List failed messages.')
            ->addOption('retry', null, InputOption::VALUE_REQUIRED, 'Retry the failed message with this id, synchronously, in this process.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit for --list-failed / --list-dispatch-failures.', 50)
            ->addOption('setup', null, InputOption::VALUE_NONE, "Create the messenger_messages/messenger_failed tables and this transport's own dispatch-failure table if they don't exist yet (one-time, per environment and per transport).")
            ->addOption('list-dispatch-failures', null, InputOption::VALUE_NONE, 'List enqueue calls that failed even after their bounded retry (no message row was ever created for these).')
            ->addOption('retry-dispatch-failure', null, InputOption::VALUE_REQUIRED, 'Re-attempt the recorded dispatch failure with this id; removes it on success.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $transportName = $input->getOption('transport');
        if ($transportName === null) {
            $io->error('--transport is required. Known transports: ' . implode(', ', TransportProfileRegistry::names()) . '.');

            return Command::FAILURE;
        }

        try {
            $profile = TransportProfileRegistry::get((string)$transportName);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

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

        $io->title($profile->label());

        $profile->bootstrap();
        $logger = CliLoggerFactory::create($profile->logPrefix() . 'Queue', !$io->isQuiet());

        $connection = DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
        $transportConfig = $profile->config();
        $transport = TransportFactory::createTransport($connection, $transportConfig);
        $failureTransport = TransportFactory::createFailureTransport($connection, $transportConfig);
        $dispatchFailureStore = $profile->failureStore($connection);

        return match ($actions[0]) {
            'stats' => $this->showStats($transportConfig->name, $transport, $failureTransport, $io),
            'list-failed' => $this->listFailed($failureTransport, (int)$input->getOption('limit'), $io),
            'retry' => $this->retry($profile, $failureTransport, (string)$input->getOption('retry'), $io, $logger),
            'setup' => $this->setup($transport, $failureTransport, $dispatchFailureStore, $connection, $transportConfig, $io),
            'list-dispatch-failures' => $this->listDispatchFailures($profile, $dispatchFailureStore, (int)$input->getOption('limit'), $io),
            'retry-dispatch-failure' => $this->retryDispatchFailure(
                $profile,
                $transportConfig->name,
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
     *
     * messenger_messages/messenger_failed are shared across every transport,
     * so --setup running once per transport must not try to re-diff/alter
     * tables a previous transport's --setup already created —
     * DoctrineTransport::setup() diffs the schema rather than "create if
     * absent", so each table is only ever set up once, guarded by
     * tablesExist().
     */
    private function setup(
        DoctrineTransport $transport,
        DoctrineTransport $failureTransport,
        AbstractDbalEnqueueFailureStore $dispatchFailureStore,
        DbalConnection $connection,
        TransportConfig $transportConfig,
        SymfonyStyle $io
    ): int {
        try {
            $schemaManager = $connection->createSchemaManager();

            if (!$schemaManager->tablesExist([$transportConfig->messagesTable])) {
                $transport->setup();
            }

            if (!$schemaManager->tablesExist([$transportConfig->failedTable])) {
                $failureTransport->setup();
            }

            $dispatchFailureStore->setup();
        } catch (Throwable $e) {
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

        $io->success(sprintf(
            '%s / %s / %s tables are ready.',
            $transportConfig->messagesTable,
            $transportConfig->failedTable,
            $dispatchFailureStore->tableName()
        ));

        return Command::SUCCESS;
    }

    /**
     * Builds the DDL from the bridge's own public configureSchema() instead
     * of a hand-typed column list, so this can never drift from what
     * setup() actually creates on a symfony/doctrine-messenger version bump.
     *
     * @return list<string>
     */
    private function buildCreateTableSql(DoctrineTransport $transport, DbalConnection $connection): array
    {
        $schema = $transport->configureSchema(new Schema(), $connection, static fn () => true);
        $table = current($schema->getTables());

        return $connection->getDatabasePlatform()->getCreateTableSQL($table);
    }

    private function showStats(string $transportName, DoctrineTransport $transport, DoctrineTransport $failureTransport, SymfonyStyle $io): int
    {
        $io->table(['Queue', 'Pending messages'], [
            [$transportName, $transport->getMessageCount()],
            [$transportName . ' (failed)', $failureTransport->getMessageCount()],
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

    private function retry(TransportProfileInterface $profile, DoctrineTransport $failureTransport, string $id, SymfonyStyle $io, Logger $logger): int
    {
        $envelope = $failureTransport->find($id);

        if ($envelope === null) {
            $io->error(sprintf('No failed message found with id "%s".', $id));

            return Command::FAILURE;
        }

        $handleBus = BusFactory::createHandleBus($profile->handlers());

        try {
            $handleBus->dispatch($envelope->withoutAll(ErrorDetailsStamp::class));
            $failureTransport->ack($envelope);
            $io->success(sprintf('Message "%s" retried successfully and removed from the failure queue.', $id));

            return Command::SUCCESS;
        } catch (Throwable $e) {
            // HandleMessageMiddleware wraps handler throwables in a
            // HandlerFailedException — unwrap it so the real error is logged
            // and displayed instead of the wrapper's generic message.
            $realError = $e instanceof HandlerFailedException
                ? ($e->getWrappedExceptions()[0] ?? $e)
                : $e;

            $logger->error(sprintf('episciences:queue --retry=%s failed again: %s', $id, $realError->getMessage()));
            $io->error(sprintf('Retry failed: %s', $realError->getMessage()));

            return Command::FAILURE;
        }
    }

    private function listDispatchFailures(TransportProfileInterface $profile, AbstractDbalEnqueueFailureStore $dispatchFailureStore, int $limit, SymfonyStyle $io): int
    {
        $rows = $dispatchFailureStore->all($limit);

        if ($rows === []) {
            $io->success('No dispatch failures.');

            return Command::SUCCESS;
        }

        $columns = $profile->dispatchFailureColumns();
        $headers = array_map(
            static fn (string $col): string => ucfirst(str_replace('_', ' ', $col)),
            $columns
        );

        $io->table(
            $headers,
            array_map(
                static fn (array $row): array => array_map(static fn (string $col) => $row[$col], $columns),
                $rows
            )
        );

        return Command::SUCCESS;
    }

    /**
     * Re-attempts a producer-side dispatch that never made it into
     * messenger_messages (see BoundedRetryDispatcher). Unlike --retry, which
     * replays an envelope that already exists in the failure transport, this
     * re-enqueues from the raw row recorded at the time of failure.
     */
    private function retryDispatchFailure(
        TransportProfileInterface $profile,
        string $transportName,
        DoctrineTransport $transport,
        AbstractDbalEnqueueFailureStore $dispatchFailureStore,
        int $id,
        SymfonyStyle $io,
        Logger $logger
    ): int {
        $row = $dispatchFailureStore->find($id);

        if ($row === null) {
            $io->error(sprintf('No dispatch failure found with id "%d".', $id));

            return Command::FAILURE;
        }

        $sendBus = BusFactory::createSendBus($transportName, $transport, $profile->messageClasses());

        try {
            $sendBus->dispatch($profile->rebuildMessage($row));
        } catch (Throwable $e) {
            $dispatchFailureStore->markRetryFailed($id, $e->getMessage());
            $logger->error(sprintf('episciences:queue --retry-dispatch-failure=%d failed again: %s', $id, $e->getMessage()));
            $io->error(sprintf('Retry failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $dispatchFailureStore->delete($id);
        $io->success(sprintf('Dispatch failure #%d re-enqueued successfully and removed.', $id));

        return Command::SUCCESS;
    }
}
