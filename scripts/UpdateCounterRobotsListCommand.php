<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: download the COUNTER Robots list.
 *
 * Stores the plain-text list at COUNTER_ROBOTS_LIST_PATH for use by
 * ProcessStatTempCommand and BotDetector.
 */
class UpdateCounterRobotsListCommand extends Command
{
    protected static $defaultName = 'stats:update-robots-list';

    public const COUNTER_ROBOTS_URL =
        'https://raw.githubusercontent.com/atmire/COUNTER-Robots/master/generated/COUNTER_Robots_list.txt';

    /** Maximum age in seconds before forcing a re-download (7 days). */
    private const MAX_AGE_SECONDS = 7 * 24 * 3600;

    protected function configure(): void
    {
        $this
            ->setDescription('Download the COUNTER Robots list for bot detection')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-download even if the file is recent')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $force  = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $this->bootstrap();

        $logger = new Logger('updateRobotsList');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'updateRobotsList_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $destPath = $this->buildDestinationPath();

        if ($dryRun) {
            $io->note('Dry-run mode — no file will be written.');
            $io->writeln('Would download: ' . self::COUNTER_ROBOTS_URL);
            $io->writeln('Would write to: ' . $destPath);
            return Command::SUCCESS;
        }

        // Skip download if file is recent enough
        if (!$force && file_exists($destPath)) {
            $age = time() - (int) filemtime($destPath);
            if ($age < self::MAX_AGE_SECONDS) {
                $logger->info('Robots list is recent (age: ' . $age . 's). Skipping. Use --force to override.');
                $io->success('Robots list is up to date. Use --force to re-download.');
                return Command::SUCCESS;
            }
        }

        $logger->info('Downloading COUNTER Robots list from ' . self::COUNTER_ROBOTS_URL);

        try {
            $client   = new Client(['timeout' => 30]);
            $response = $client->get(self::COUNTER_ROBOTS_URL);
            $content  = (string) $response->getBody();
        } catch (GuzzleException $e) {
            $logger->error('Download failed: ' . $e->getMessage());
            $io->error('Failed to download COUNTER Robots list: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $lineCount = $this->parseAndValidateContent($content);
        if ($lineCount === 0) {
            $logger->error('Downloaded content appears invalid (0 non-empty, non-comment lines).');
            $io->error('Downloaded content is empty or invalid.');
            return Command::FAILURE;
        }

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $logger->error('Cannot create directory: ' . $dir);
            $io->error('Failed to create directory: ' . $dir);
            return Command::FAILURE;
        }

        if (file_put_contents($destPath, $content) === false) {
            $logger->error('Cannot write file: ' . $destPath);
            $io->error('Failed to write robots list to disk.');
            return Command::FAILURE;
        }

        chmod($destPath, 0644);

        $logger->info('Robots list saved to ' . $destPath . ' (' . $lineCount . ' patterns).');
        $io->success('COUNTER Robots list updated: ' . $lineCount . ' patterns saved.');
        return Command::SUCCESS;
    }

    /**
     * Count non-blank, non-comment lines in downloaded content.
     */
    public function parseAndValidateContent(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $count = 0;
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Returns the absolute path where the robots list file is stored.
     */
    public function buildDestinationPath(): string
    {
        return dirname(APPLICATION_PATH) . '/cache/counter-robots/COUNTER_Robots_list.txt';
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

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
