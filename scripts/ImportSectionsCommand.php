<?php
declare(strict_types=1);

use Episciences\Section\Import\Importer;
use Episciences\Section\Import\Row;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: import journal sections from a CSV file.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   rvid;position;title_fr;title_en;description_fr;description_en;status
 *
 * See Episciences\Section\Import\Row for the exact meaning of each column.
 *
 * Replaces: scripts/importSections.php (JournalScript)
 */
final class ImportSectionsCommand extends Command
{
    protected static $defaultName = 'import:sections';

    private Logger $logger;
    private int $importedCount = 0;
    private int $skippedCount  = 0;
    private int $errorCount    = 0;
    private bool $rvidDefined  = false;

    protected function configure(): void
    {
        $this
            ->setDescription('Import journal sections from a semicolon-separated CSV file.')
            ->addOption('csv-file', null, InputOption::VALUE_REQUIRED, 'Path to the CSV file containing sections data')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the import without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $dryRun  = (bool) $input->getOption('dry-run');
        $csvFile = (string) ($input->getOption('csv-file') ?? '');

        if ($csvFile === '') {
            $io->error('Missing required option: --csv-file');
            return Command::FAILURE;
        }

        $io->title('Sections import');
        $this->bootstrap();

        $this->logger = new Logger('import-sections');
        $fileHandler  = new StreamHandler(
            EPISCIENCES_LOG_PATH . 'import-sections_' . date('Y-m-d') . '.log',
            Logger::DEBUG
        );
        $fileHandler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context%\n", null, false, true));
        $this->logger->pushHandler($fileHandler);
        if (!$io->isQuiet()) {
            $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
            $consoleHandler->setFormatter(new LineFormatter("%level_name%: %message%\n", null, false, false));
            $this->logger->pushHandler($consoleHandler);
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        if (!$this->validateCsvFile($csvFile)) {
            return Command::FAILURE;
        }

        $this->processCsvFile($csvFile, $dryRun);
        $this->displaySummary($io);

        return $this->errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function validateCsvFile(string $csvFile): bool
    {
        if (!file_exists($csvFile)) {
            $this->logger->error("CSV file not found: {$csvFile}");
            return false;
        }

        if (!is_readable($csvFile)) {
            $this->logger->error("CSV file is not readable: {$csvFile}");
            return false;
        }

        $this->logger->info("CSV file validated: {$csvFile}");
        return true;
    }

    private function processCsvFile(string $csvFile, bool $dryRun): void
    {
        $handle = fopen($csvFile, 'r');

        if ($handle === false) {
            $this->logger->error('Failed to open CSV file');
            $this->errorCount++;
            return;
        }

        // Skip header row — fgetcsv returns false on empty file or read error
        $header = fgetcsv($handle, 0, ';');
        if ($header !== false) {
            $this->logger->info('CSV header: ' . implode(', ', $header));
        }

        $importer   = new Importer($dryRun);
        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if (count($data) < 7) {
                $this->logger->warning(
                    "Line {$lineNumber}: Invalid format — expected 7 columns, got " . count($data) . ', skipping'
                );
                $this->skippedCount++;
                continue;
            }

            $this->processRow(Row::fromCsvRow($data), $lineNumber, $importer);
        }

        fclose($handle);
    }

    private function processRow(Row $row, int $lineNumber, Importer $importer): void
    {
        if ($row->rvid === null) {
            $this->logger->warning("Line {$lineNumber}: Missing required field 'rvid', skipping");
            $this->skippedCount++;
            return;
        }

        // Define RVID constant from first valid row — required by Section->save()
        if (!$this->rvidDefined && !defined('RVID')) {
            define('RVID', $row->rvid);
            $this->rvidDefined = true;
            $this->logger->info("RVID constant defined as: {$row->rvid}");
        }

        $titles = $row->titles();
        if ($titles === []) {
            $this->logger->warning("Line {$lineNumber}: At least one title (fr or en) is required, skipping");
            $this->skippedCount++;
            return;
        }

        foreach ($row->orphanedDescriptionLanguages($titles) as $lang) {
            $label = $lang === 'fr' ? 'French' : 'English';
            $this->logger->warning(
                "Line {$lineNumber}: {$label} description provided but no {$label} title — ignoring description"
            );
        }
        $descriptions = $row->descriptionsForTitles($titles);

        if (Row::isStatusInvalid($row->status)) {
            $this->logger->warning("Line {$lineNumber}: Invalid status value '{$row->status}', defaulting to open");
        }
        $status = Row::parseStatus($row->status);

        try {
            $sid = $importer->import($row->rvid, $row->position, $titles, $descriptions, $status);

            if ($sid === null) {
                $this->logger->warning(
                    "Line {$lineNumber}: Section already exists for journal {$row->rvid}"
                    . " at position {$row->position}, skipping"
                );
                $this->skippedCount++;
                return;
            }

            $this->logger->info(
                "Section created (SID: {$sid}, journal: {$row->rvid}, status: {$status})",
                ['line' => $lineNumber]
            );
            $this->importedCount++;
        } catch (Throwable $e) {
            $this->logger->error("Exception on line {$lineNumber}: " . $e->getMessage());
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
            $io->success('Sections import completed successfully.');
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
