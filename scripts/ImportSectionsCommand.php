<?php
declare(strict_types=1);

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
 * - rvid        : (int)  journal RVID — required per row
 * - position    : (int)  section position; auto-incremented if empty
 * - title_fr    : (str)  French title (at least one of title_fr / title_en required)
 * - title_en    : (str)  English title
 * - description_fr : (str) French description (only used when title_fr is set)
 * - description_en : (str) English description (only used when title_en is set)
 * - status      : (int)  1 = open (default), 0 = closed
 *
 * Replaces: scripts/importSections.php (JournalScript)
 */
class ImportSectionsCommand extends Command
{
    protected static $defaultName = 'import:sections';

    private Logger $logger;
    private int $importedCount = 0;
    private int $skippedCount  = 0;
    private int $errorCount    = 0;

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

        $lineNumber  = 1;
        $rvidDefined = false;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if (count($data) < 7) {
                $this->logger->warning(
                    "Line {$lineNumber}: Invalid format — expected 7 columns, got " . count($data) . ', skipping'
                );
                $this->skippedCount++;
                continue;
            }

            $this->processSectionRow($data, $lineNumber, $rvidDefined, $dryRun);
        }

        fclose($handle);
    }

    /**
     * @param array<int, string> $data
     */
    private function processSectionRow(array $data, int $lineNumber, bool &$rvidDefined, bool $dryRun): void
    {
        [$rvid, $position, $titleFr, $titleEn, $descriptionFr, $descriptionEn, $status] = $data;

        if (empty($rvid)) {
            $this->logger->warning("Line {$lineNumber}: Missing required field 'rvid', skipping");
            $this->skippedCount++;
            return;
        }

        // Define RVID constant from first valid row — required by Section->save()
        if (!$rvidDefined && !defined('RVID')) {
            define('RVID', (int) $rvid);
            $rvidDefined = true;
            $this->logger->info("RVID constant defined as: {$rvid}");
        }

        if (empty(trim($titleFr)) && empty(trim($titleEn))) {
            $this->logger->warning("Line {$lineNumber}: At least one title (fr or en) is required, skipping");
            $this->skippedCount++;
            return;
        }

        try {
            if (empty($position)) {
                $position = $dryRun ? 999 : $this->getNextPosition((int) $rvid);
                $this->logger->info("Line {$lineNumber}: Auto-generated position {$position} for journal {$rvid}");
            } else {
                $position = (int) $position;
                if (!$dryRun && $this->sectionExists((int) $rvid, $position)) {
                    $this->logger->warning(
                        "Line {$lineNumber}: Section already exists for journal {$rvid} at position {$position}, skipping"
                    );
                    $this->skippedCount++;
                    return;
                }
            }

            $section = new Episciences_Section();
            $section->setRvid((int) $rvid);
            $section->setPosition($position);

            $titles = [];
            if (!empty(trim($titleFr))) {
                $titles['fr'] = trim($titleFr);
            }
            if (!empty(trim($titleEn))) {
                $titles['en'] = trim($titleEn);
            }
            $section->setTitles($titles);

            $descriptions = [];
            if (!empty(trim($descriptionFr))) {
                if (isset($titles['fr'])) {
                    $descriptions['fr'] = trim($descriptionFr);
                } else {
                    $this->logger->warning("Line {$lineNumber}: French description provided but no French title — ignoring description");
                }
            }
            if (!empty(trim($descriptionEn))) {
                if (isset($titles['en'])) {
                    $descriptions['en'] = trim($descriptionEn);
                } else {
                    $this->logger->warning("Line {$lineNumber}: English description provided but no English title — ignoring description");
                }
            }
            $section->setDescriptions($descriptions);

            $statusValue = self::parseStatusValue($status, $lineNumber, $this->logger);
            $section->setSetting(Episciences_Section::SETTING_STATUS, $statusValue);

            if ($dryRun) {
                $this->logger->info(
                    "[dry-run] Would create section for journal {$rvid} at position {$position} with status {$statusValue}",
                    ['line' => $lineNumber]
                );
                $this->importedCount++;
            } elseif ($section->save()) {
                $this->logger->info(
                    "Section created (SID: {$section->getSid()}, journal: {$rvid}, position: {$position}, status: {$statusValue})",
                    ['line' => $lineNumber]
                );
                $this->importedCount++;
            } else {
                $this->logger->error("Failed to save section", ['line' => $lineNumber]);
                $this->errorCount++;
            }
        } catch (\Throwable $e) {
            $this->logger->error("Exception on line {$lineNumber}: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    /**
     * Parse and validate a raw status value from the CSV.
     *
     * Returns SECTION_OPEN_STATUS when the value is empty or unrecognised.
     */
    public static function parseStatusValue(string $raw, int $lineNumber, Logger $logger): int
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return Episciences_Section::SECTION_OPEN_STATUS;
        }

        // is_numeric guards against non-numeric strings like 'abc' whose (int) cast would
        // silently produce 0 and accidentally match SECTION_CLOSED_STATUS.
        if (!is_numeric($trimmed)) {
            $logger->warning("Line {$lineNumber}: Invalid status value '{$raw}', defaulting to open");
            return Episciences_Section::SECTION_OPEN_STATUS;
        }

        $statusInt = (int) $trimmed;

        if ($statusInt === Episciences_Section::SECTION_CLOSED_STATUS
            || $statusInt === Episciences_Section::SECTION_OPEN_STATUS
        ) {
            return $statusInt;
        }

        $logger->warning("Line {$lineNumber}: Invalid status value '{$raw}', defaulting to open");
        return Episciences_Section::SECTION_OPEN_STATUS;
    }

    private function sectionExists(int $rvid, int $position): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int) $db->fetchOne(
            $db->select()
                ->from(Episciences_SectionsManager::TABLE, 'COUNT(*)')
                ->where('RVID = ?', $rvid)
                ->where('POSITION = ?', $position)
        ) > 0;
    }

    private function getNextPosition(int $rvid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int) $db->fetchOne(
            $db->select()
                ->from(Episciences_SectionsManager::TABLE, 'MAX(POSITION)')
                ->where('RVID = ?', $rvid)
        ) + 1;
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
