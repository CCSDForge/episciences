<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: purge the OpenAIRE Research Graph enrichment caches.
 *
 * Clears the three PSR-6 pools shared by GetCreatorDataCommand, GetFundingDataCommand
 * and GetClassificationJelCommand in one shot, without querying the API. Needed after
 * an OpenAIRE API format migration (e.g. v1 -> Graph v3): cache keys carry no version
 * marker (md5($doi)), so a cache hit on a pre-migration entry silently returns the old
 * format to the new extraction code instead of triggering a fresh API call.
 */
class ClearOpenAireCacheCommand extends Command
{
    protected static $defaultName = 'enrichment:clear-cache';

    private const CACHE_POOLS = [
        'openAireResearchGraph',
        'enrichmentAuthors',
        'enrichmentFunding',
    ];

    protected function configure(): void
    {
        $this->setDescription('Purge the OpenAIRE Research Graph enrichment caches (global, creators, funding)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OpenAIRE enrichment cache purge');

        $this->bootstrap();

        $logger = new Logger('openAireCachePurge');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'openAireCachePurge_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $cacheDir   = dirname(APPLICATION_PATH) . '/cache/';
        $allCleared = true;

        foreach (self::CACHE_POOLS as $pool) {
            $cache   = new FilesystemAdapter($pool, 0, $cacheDir);
            $cleared = $cache->clear();
            $allCleared = $allCleared && $cleared;
            $logger->info("Cache pool '{$pool}' " . ($cleared ? 'cleared' : 'clear failed'));
            if ($cleared) {
                $io->writeln("<info>✓</info> {$pool}");
            } else {
                $io->writeln("<error>✗</error> {$pool}");
            }
        }

        if (!$allCleared) {
            $io->error('OpenAIRE enrichment cache purge failed for one or more pools.');
            return Command::FAILURE;
        }

        $io->success('OpenAIRE enrichment cache purge completed.');
        return Command::SUCCESS;
    }

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // (no rvcode) which causes Bootstrap::_initModule() to fail silently.
        // Mirrors legacy JournalScript pattern: initApp() reads config, initDb() sets adapter.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }
}
