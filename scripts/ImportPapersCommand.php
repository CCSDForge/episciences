<?php
declare(strict_types=1);

use Episciences\Paper\Import\PaperImporter;
use Episciences\Paper\Import\PublicationDateResolver;
use Episciences\Paper\Import\ReviewResolver;
use Episciences\Paper\Import\Row;
use Episciences\Paper\Import\VolumeSectionResolver;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: import or update papers from a CSV file.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   identifier;repoid;version;status;volume_id;volume_title_fr;volume_title_en;volume_num;volume_year;
 *   section_id;section_title_fr;section_title_en;uid;publication_date;editors;doi;docid;rvid;submission_date
 *
 * See Episciences\Paper\Import\Row for the exact meaning of each column.
 *
 * A row is imported as a new paper if no existing paper matches it (by identifier+version,
 * or by docid) for the resolved journal, or applied as an update otherwise. Volumes/sections
 * referenced by title are looked up and reused, or created on the fly, per
 * Episciences\Paper\Import\VolumeSectionResolver.
 *
 * All rows in one CSV file must resolve to the same journal — RVID is a process-wide PHP
 * constant, so it is locked from the first valid row; a row referencing a different
 * journal is rejected as a per-row error without aborting the rest of the import.
 *
 * Replaces: scripts/update_papers.php (JournalScript, interactive)
 */
final class ImportPapersCommand extends Command
{
    protected static $defaultName = 'import:papers';

    private Logger $logger;
    private int $importedCount = 0;
    private int $updatedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    private ?Episciences_Review $lockedReview = null;

    protected function configure(): void
    {
        $this
            ->setDescription('Import or update papers from a semicolon-separated CSV file.')
            ->addOption('csv-file', null, InputOption::VALUE_REQUIRED, 'Path to the CSV file containing papers data')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the import without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $csvFile = (string)($input->getOption('csv-file') ?? '');

        if ($csvFile === '') {
            $io->error('Missing required option: --csv-file');
            return Command::FAILURE;
        }

        $io->title('Papers import');
        $this->bootstrap();

        $this->logger = new Logger('import-papers');
        $this->logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'import-papers_' . date('Y-m-d') . '.log',
            Logger::DEBUG
        ));
        if (!$io->isQuiet()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

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

        $importer = new PaperImporter($dryRun, new VolumeSectionResolver($dryRun), new PublicationDateResolver());

        $lineNumber = 0;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if (Row::isBlankCsvRecord($data)) {
                continue;
            }

            // Skip header row (first column value is 'identifier')
            if ($lineNumber === 1 && strtolower($data[0]) === 'identifier') {
                continue;
            }

            $this->processRow($data, $lineNumber, $importer);
        }

        fclose($handle);
    }

    /**
     * @param array<int, string> $data
     */
    private function processRow(array $data, int $lineNumber, PaperImporter $importer): void
    {
        $row = Row::fromCsvRow($data);

        if ($row->hasInvalidStatus()) {
            $this->logger->warning(
                "Line {$lineNumber}: Invalid status value '{$row->rawStatus}', falling back to the default status"
            );
        }

        if ($row->rvidOrCode === null) {
            $this->logger->warning("Line {$lineNumber}: Missing required field 'rvid', skipping");
            $this->skippedCount++;
            return;
        }

        $review = ReviewResolver::resolve($row->rvidOrCode);
        if (!$review) {
            $this->logger->error("Line {$lineNumber}: No journal found for rvid/rvcode '{$row->rvidOrCode}'");
            $this->errorCount++;
            return;
        }

        if ($this->lockedReview === null) {
            $this->lockJournal($review);
        } elseif ($review->getRvid() !== $this->lockedReview->getRvid()) {
            $this->logger->error(
                "Line {$lineNumber}: row references journal '{$review->getCode()}' but this run is locked to "
                . "'{$this->lockedReview->getCode()}' — a single CSV run can only process one journal"
            );
            $this->errorCount++;
            return;
        }

        $this->logger->info("Processing line {$lineNumber}: {$row->identifier}");

        try {
            $result = $importer->import($row, $this->lockedReview->getRvid());
            if ($result->wasUpdate) {
                $this->updatedCount++;
                $this->logger->info("Line {$lineNumber}: paper #{$result->docid} updated");
            } else {
                $this->importedCount++;
                $this->logger->info("Line {$lineNumber}: paper #{$result->docid} imported");
            }
        } catch (Throwable $e) {
            $this->logger->error("Line {$lineNumber}: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    /**
     * Locks this run to a single journal, since RVID is a process-wide PHP constant that
     * legacy code (Episciences_Volume, Episciences_Submit, ...) reads directly.
     */
    private function lockJournal(Episciences_Review $review): void
    {
        $this->lockedReview = $review;

        define('RVID', $review->getRvid());
        defineJournalConstants($review->getCode());
        Zend_Registry::set('reviewSettingsDoi', $review->getDoiSettings());

        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages')) > 2) {
            Zend_Registry::get('Zend_Translate')->addTranslation(REVIEW_PATH . 'languages');
        }

        $this->logger->info("Journal locked for this run: {$review->getCode()} (RVID {$review->getRvid()})");
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $total = $this->importedCount + $this->updatedCount + $this->skippedCount + $this->errorCount;
        $this->logger->info('=== Import summary ===');
        $this->logger->info("Imported : {$this->importedCount}");
        $this->logger->info("Updated  : {$this->updatedCount}");
        $this->logger->info("Skipped  : {$this->skippedCount}");
        $this->logger->info("Errors   : {$this->errorCount}");
        $this->logger->info("Total    : {$total}");

        if ($this->errorCount > 0) {
            $io->warning("Import completed with {$this->errorCount} error(s). Check the log for details.");
        } else {
            $io->success('Papers import completed successfully.');
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

        // Episciences_Translation_Plugin (which normally registers Zend_Translate) only runs on
        // a real HTTP dispatch, never here. Without this, lockJournal()'s addTranslation() call
        // hits Zend_Registry::get('Zend_Translate') on an unregistered key and fails hard for any
        // journal that has custom translations under REVIEW_PATH/languages.
        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::set('Zend_Translate', new Zend_Translate([
                'adapter' => Zend_Translate::AN_ARRAY,
                'content' => ['' => ''],
                'locale' => 'en',
            ]));
        }
        Zend_Registry::isRegistered('lang') || Zend_Registry::set('lang', 'en');
    }
}
