<?php

declare(strict_types=1);

require_once __DIR__ . '/Solr/BootstrapsSolrEnvironment.php';

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit for --list-failed.', 50)
            ->addOption('setup', null, InputOption::VALUE_NONE, "Create the messenger_messages/messenger_failed tables if they don't exist yet (one-time, per environment).");
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
        ]));

        if (count($actions) !== 1) {
            $io->error('Exactly one of --stats, --list-failed, --retry or --setup is required.');

            return Command::FAILURE;
        }

        $this->bootstrapSolrEnvironment();
        $logger = $this->createSolrLogger($io, 'solrQueue');

        $connection = $this->createDbalConnection();
        $transport = MessengerFactory::createTransport($connection);
        $failureTransport = MessengerFactory::createFailureTransport($connection);

        return match ($actions[0]) {
            'stats' => $this->showStats($transport, $failureTransport, $io),
            'list-failed' => $this->listFailed($failureTransport, (int)$input->getOption('limit'), $io),
            'retry' => $this->retry($failureTransport, (string)$input->getOption('retry'), $io, $logger),
            'setup' => $this->setup($transport, $failureTransport, $connection, $io),
        };
    }

    /**
     * Creates the transport tables. On MySQL, the app's DB user commonly has
     * no CREATE/ALTER privilege in staging/prod (only SELECT/INSERT/UPDATE/
     * DELETE) — in that case, print the exact DDL so a DBA can run it
     * manually instead of failing with an opaque permission error.
     */
    private function setup(DoctrineTransport $transport, DoctrineTransport $failureTransport, DbalConnection $connection, SymfonyStyle $io): int
    {
        try {
            $transport->setup();
            $failureTransport->setup();
        } catch (\Throwable $e) {
            $io->error(sprintf('Automatic table creation failed: %s', $e->getMessage()));
            $io->note('This is expected if the database user lacks CREATE/ALTER privileges (common in staging/prod). Ask a DBA to run the following SQL manually, then re-run this command to confirm:');

            foreach ([MessengerFactory::MESSAGES_TABLE, MessengerFactory::FAILED_TABLE] as $tableName) {
                foreach ($this->buildCreateTableSql($connection, $tableName) as $sql) {
                    $io->writeln($sql . ';');
                }
                $io->newLine();
            }

            return Command::FAILURE;
        }

        $io->success('messenger_messages / messenger_failed tables are ready.');

        return Command::SUCCESS;
    }

    /**
     * Mirrors symfony/doctrine-messenger's own table schema
     * (Connection::buildSchemaTable() — private, not reusable directly) so the
     * manually-run DDL matches exactly what setup() would have created.
     *
     * @return list<string>
     */
    private function buildCreateTableSql(DbalConnection $connection, string $tableName): array
    {
        $table = new Table($tableName);
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('body', Types::TEXT, ['notnull' => true]);
        $table->addColumn('headers', Types::TEXT, ['notnull' => true]);
        $table->addColumn('queue_name', Types::STRING, ['length' => 190, 'notnull' => true]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addColumn('available_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addColumn('delivered_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name', 'available_at', 'delivered_at', 'id']);

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
}
