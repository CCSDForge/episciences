<?php

declare(strict_types=1);

require_once __DIR__ . '/Solr/BootstrapsSolrEnvironment.php';

use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\MessengerFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;

/**
 * Continuously consumes the Solr indexing/deletion queue. There is no
 * update/delete mode flag: both IndexPaperMessage and DeletePaperMessage
 * share the same transport and are routed to the correct handler
 * automatically by Messenger's HandlersLocator.
 */
class SolrWorkerCommand extends Command
{
    use BootstrapsSolrEnvironment;

    protected static $defaultName = 'solr:worker';

    protected function configure(): void
    {
        $this
            ->setDescription('Continuously consume the Solr indexing/deletion queue')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after processing this many messages.')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds.')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop once memory usage exceeds this limit (e.g. "512M").');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Solr indexing queue worker');

        $this->bootstrapSolrEnvironment();
        $logger = $this->createSolrLogger($io, 'solrWorker');

        $connection = $this->createDbalConnection();
        $transport = MessengerFactory::createTransport($connection);
        $failureTransport = MessengerFactory::createFailureTransport($connection);

        // Cleared before every message below, so a long-running worker never
        // serves a journal/volume/section snapshot older than the message
        // currently being handled (see BootstrapsSolrEnvironment::createDocumentBuilder()).
        $volumeSectionCache = new ArrayAdapter(0, false);

        $indexHandler = new IndexPaperMessageHandler($this->createDocumentBuilder($volumeSectionCache), new SolariumClientFactory());
        $deleteHandler = new DeletePaperMessageHandler(new SolariumClientFactory());
        $handleBus = MessengerFactory::createHandleBus($indexHandler, $deleteHandler);

        $eventDispatcher = MessengerFactory::createWorkerEventDispatcher($transport, $failureTransport, $logger);
        $eventDispatcher->addListener(
            WorkerMessageReceivedEvent::class,
            static fn () => $volumeSectionCache->clear()
        );

        $limit = $input->getOption('limit');
        if ($limit !== null) {
            $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener((int)$limit, $logger));
        }

        $timeLimit = $input->getOption('time-limit');
        if ($timeLimit !== null) {
            $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener((int)$timeLimit, $logger));
        }

        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit !== null) {
            $eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener($this->parseMemoryLimit((string)$memoryLimit), $logger));
        }

        $worker = new Worker([MessengerFactory::TRANSPORT_NAME => $transport], $handleBus, $eventDispatcher, $logger);

        $logger->info('Solr worker starting.');
        $worker->run();
        $logger->info('Solr worker stopped.');

        $io->success('Solr worker stopped.');

        return Command::SUCCESS;
    }

    private function parseMemoryLimit(string $limit): int
    {
        // Accepts a bare byte count (512), a unit suffix (512K/512M/512G), or
        // either with a trailing "b" (512MB) — PHP's own memory_limit ini
        // syntax only supports the former, this is more forgiving on purpose.
        if (preg_match('/^\s*(\d+)\s*([kmg])?b?\s*$/i', $limit, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid --memory-limit value: %s', $limit));
        }

        $value = (int)$matches[1];
        $unit = strtolower($matches[2] ?? '');

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
