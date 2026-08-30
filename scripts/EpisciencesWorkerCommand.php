<?php

declare(strict_types=1);

require_once __DIR__ . '/Messenger/TransportProfileRegistry.php';
require_once __DIR__ . '/Messenger/ParsesMemoryLimit.php';

use Episciences\Messenger\Bus\BusFactory;
use Episciences\Messenger\Dbal\DbalConnectionFactory;
use Episciences\Messenger\Log\CliLoggerFactory;
use Episciences\Messenger\Transport\TransportFactory;
use Episciences\Messenger\Worker\WorkerEventDispatcherFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;

/**
 * Continuously consumes one Messenger queue, selected by --transport via
 * TransportProfileRegistry. Deployed as one process per transport (see
 * src/php-fpm/episciences-worker@.service) so a slow Solr document build
 * never blocks a cheap Next.js revalidation POST.
 */
class EpisciencesWorkerCommand extends Command
{
    use ParsesMemoryLimit;

    protected static $defaultName = 'episciences:worker';

    protected function configure(): void
    {
        $this
            ->setDescription('Continuously consume one Messenger queue (see --transport).')
            ->addOption('transport', null, InputOption::VALUE_REQUIRED, 'Which queue to consume: ' . implode(', ', TransportProfileRegistry::names()) . '.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after processing this many messages.')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds.')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop once memory usage exceeds this limit (e.g. "512M").');
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

        $io->title($profile->label() . ' worker');

        $profile->bootstrap();
        $logger = CliLoggerFactory::create($profile->logPrefix() . 'Worker', !$io->isQuiet());

        $connection = DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
        $transportConfig = $profile->config();
        $transport = TransportFactory::createTransport($connection, $transportConfig);
        $failureTransport = TransportFactory::createFailureTransport($connection, $transportConfig);

        $handleBus = BusFactory::createHandleBus($profile->handlers());

        $eventDispatcher = WorkerEventDispatcherFactory::create(
            $transportConfig->name,
            $transport,
            $failureTransport,
            $profile->retryStrategy(),
            $logger
        );
        $profile->registerWorkerListeners($eventDispatcher);

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

        $worker = new Worker([$transportConfig->name => $transport], $handleBus, $eventDispatcher, $logger);

        $logger->info(sprintf('%s worker starting.', $profile->label()));
        $worker->run();
        $logger->info(sprintf('%s worker stopped.', $profile->label()));

        $io->success(sprintf('%s worker stopped.', $profile->label()));

        return Command::SUCCESS;
    }
}
