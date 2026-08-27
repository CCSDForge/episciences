<?php
declare(strict_types=1);

use Episciences\Volume\Import\Importer;
use Episciences\Volume\Import\Row;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: import journal volumes from a CSV file.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   position;status;current_issue;special_issue;bib_reference;title_en;title_fr;description_en;description_fr;num;year
 *
 * See Episciences\Volume\Import\Row for the exact meaning of each column.
 *
 * Replaces: scripts/importVolumes.php (JournalScript)
 */
final class ImportVolumesCommand extends Command
{
    protected static $defaultName = 'import:volumes';

    private Logger $logger;
    private int $importedCount = 0;
    private int $skippedCount  = 0;
    private int $errorCount    = 0;

    protected function configure(): void
    {
        $this
            ->setDescription('Import journal volumes from a semicolon-separated CSV file.')
            ->addOption('rvid', null, InputOption::VALUE_REQUIRED, 'Journal RVID (integer)')
            ->addOption('csv-file', null, InputOption::VALUE_REQUIRED, 'Path to the CSV file containing volumes data')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the import without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $dryRun  = (bool) $input->getOption('dry-run');
        $rvid    = $input->getOption('rvid');
        $csvFile = (string) ($input->getOption('csv-file') ?? '');

        if ($rvid === null || $rvid === '') {
            $io->error('Missing required option: --rvid');
            return Command::FAILURE;
        }

        if ($csvFile === '') {
            $io->error('Missing required option: --csv-file');
            return Command::FAILURE;
        }

        $io->title('Volumes import');
        $this->bootstrap();

        $this->logger = new Logger('import-volumes');
        $this->logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'import-volumes_' . date('Y-m-d') . '.log',
            Logger::DEBUG
        ));
        if (!$io->isQuiet()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        $review = Episciences_ReviewsManager::find((int) $rvid);

        if (!$review instanceof Episciences_Review) {
            $this->logger->error('Journal not found', ['rvid' => $rvid]);
            $io->error("No journal found for RVID {$rvid}.");
            return Command::FAILURE;
        }

        // defineJournalConstants() does not define RVID itself; Episciences_Volume's internals
        // (e.g. addNewVolume(), VolumesAndSectionsManager::sort()) read it as a bare constant.
        if (!defined('RVID')) {
            define('RVID', $review->getRvid());
        }
        Zend_Registry::set('reviewSettingsDoi', $review->getDoiSettings());
        defineJournalConstants($review->getCode());

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $this->logger->error("CSV file not found or not readable: {$csvFile}");
            $io->error("CSV file not found or not readable: {$csvFile}");
            return Command::FAILURE;
        }

        $this->processCsvFile($csvFile, $dryRun);
        $this->displaySummary($io);

        return $this->errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function processCsvFile(string $path, bool $dryRun): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->logger->error('Failed to open CSV file');
            $this->errorCount++;
            return;
        }

        $importer = new Importer($dryRun);
        $lineNumber = 0;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            // Skip header row (first column value is 'position')
            if ($lineNumber === 1 && strtolower($data[0]) === 'position') {
                continue;
            }

            $this->processRow(Row::fromCsvRow($data), $lineNumber, $importer, $dryRun);
        }

        fclose($handle);
    }

    private function processRow(Row $row, int $lineNumber, Importer $importer, bool $dryRun): void
    {
        $titles = $row->titles();

        if ($titles === []) {
            $this->logger->warning("Line {$lineNumber}: Skipped — no title provided");
            $this->skippedCount++;
            return;
        }

        $this->logger->info("Processing line {$lineNumber}: " . implode(' / ', $titles));

        try {
            $importer->import($row);

            if ($dryRun) {
                $this->logger->info('[dry-run] Would create volume: ' . implode(' / ', $titles), ['line' => $lineNumber]);
            } else {
                $this->logger->info('Volume saved successfully', ['line' => $lineNumber]);
            }

            $this->importedCount++;
        } catch (Throwable $e) {
            $this->logger->error("Line {$lineNumber}: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $total = $this->importedCount + $this->skippedCount + $this->errorCount;
        $this->logger->info('=== Import summary ===');
        $this->logger->info("Imported : {$this->importedCount}");
        $this->logger->info("Skipped  : {$this->skippedCount}");
        $this->logger->info("Errors   : {$this->errorCount}");
        $this->logger->info("Total    : {$total}");

        if ($this->errorCount > 0) {
            $io->warning("Import completed with {$this->errorCount} error(s). Check the log for details.");
        } else {
            $io->success('Volumes import completed successfully.');
        }
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

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
