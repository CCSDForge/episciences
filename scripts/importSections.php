#!/usr/bin/env php
<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

$localopts = [
    'csv_file=s' => 'CSV file path containing sections data',
    'dry_run|n' => 'Perform a dry run without actually saving data',
    'rvid=i' => 'Journal review ID (required for section import)',
    'rvcode=s' => 'Journal review code (alternative to rvid)'
];

require_once __DIR__ . '/loadHeader.php';
require_once "JournalScript.php";

class ImportSections extends JournalScript
{
    private Logger $logger;
    private int $importedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    private bool $dryRun = false;

    public function __construct(array $localopts)
    {
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        $this->setRequiredParams([]);
        parent::__construct();

        // Initialize Monolog
        $this->logger = new Logger('import-sections');

        // File handler
        $fileHandler = new StreamHandler(EPISCIENCES_LOG_PATH . 'import-sections.log', Logger::DEBUG);
        $fileHandler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context%\n", null, false, true));
        $this->logger->pushHandler($fileHandler);

        // Console handler for real-time feedback
        $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
        $consoleHandler->setFormatter(new LineFormatter("%level_name%: %message%\n", null, false, false));
        $this->logger->pushHandler($consoleHandler);
    }

    public function run(): void
    {
        $csvFile = $this->getParam('csv_file');
        
        if (!$csvFile) {
            $this->logger->error('Missing required parameter: csv_file');
            $this->displayHelp();
            return;
        }
        
        $this->dryRun = $this->getParam('dry_run') !== null;

        $this->logger->info('Starting sections import from CSV: ' . $csvFile);
        
        if ($this->dryRun) {
            $this->logger->info('Running in DRY RUN mode - no data will be saved');
        }

        // Initialize application
        $this->initApp();
        
        // Only initialize database if not dry run
        if (!$this->dryRun) {
            $this->initDb();
        }

        if (!$this->validateCsvFile($csvFile)) {
            return;
        }

        $this->processCsvFile($csvFile);
        $this->displaySummary();
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

    private function processCsvFile(string $csvFile): void
    {
        $handle = fopen($csvFile, 'r');
        
        if ($handle === false) {
            $this->logger->error('Failed to open CSV file');
            return;
        }

        // Skip header row
        $header = fgetcsv($handle, 0, ';');
        $this->logger->info('CSV header: ' . implode(', ', $header ?? []));

        $lineNumber = 1;
        $rvid_defined = false;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            
            if (count($data) < 7) {
                $this->logger->warning("Line {$lineNumber}: Invalid format, expected 7 columns, got " . count($data) . ", skipping");
                $this->skippedCount++;
                continue;
            }

            $this->processSectionRow($data, $lineNumber, $rvid_defined);
        }

        fclose($handle);
    }

    private function processSectionRow(array $data, int $lineNumber, bool &$rvid_defined): void
    {
        [$rvid, $position, $titlesFr, $titlesEn, $descriptionsFr, $descriptionsEn, $status] = $data;

        // Validate mandatory rvid
        if (empty($rvid)) {
            $this->logger->warning("Line {$lineNumber}: Missing required field 'rvid', skipping");
            $this->skippedCount++;
            return;
        }

        // Define RVID constant from first valid row (required by Section->save())
        if (!$rvid_defined && !defined('RVID')) {
            define('RVID', (int)$rvid);
            $rvid_defined = true;
            $this->logger->info("RVID constant defined as: {$rvid}");
        }

        // Validate that at least one title is provided
        if (empty(trim($titlesFr)) && empty(trim($titlesEn))) {
            $this->logger->warning("Line {$lineNumber}: At least one title (fr or en) is required, skipping");
            $this->skippedCount++;
            return;
        }

        try {
            // Handle position - auto-increment if not specified
            if (empty($position)) {
                $position = $this->getNextPosition((int)$rvid);
                $this->logger->info("Line {$lineNumber}: Auto-generated position {$position} for journal {$rvid}");
            } else {
                $position = (int)$position;
                // Check if section already exists for this journal and position (skip in dry run)
                if (!$this->dryRun && $this->sectionExists((int)$rvid, $position)) {
                    $this->logger->warning("Line {$lineNumber}: Section already exists for journal {$rvid} at position {$position}, skipping");
                    $this->skippedCount++;
                    return;
                }
            }

            // Create new section
            $section = new Episciences_Section();
            $section->setRvid((int)$rvid);
            $section->setPosition($position);

            // Set titles (multilingual) - at least one is required
            $titles = [];
            if (!empty(trim($titlesFr))) {
                $titles['fr'] = trim($titlesFr);
            }
            if (!empty(trim($titlesEn))) {
                $titles['en'] = trim($titlesEn);
            }
            $section->setTitles($titles);

            // Set descriptions (optional) - language must match title language
            $descriptions = [];
            if (!empty(trim($descriptionsFr)) && isset($titles['fr'])) {
                $descriptions['fr'] = trim($descriptionsFr);
            } elseif (!empty(trim($descriptionsFr)) && !isset($titles['fr'])) {
                $this->logger->warning("Line {$lineNumber}: French description provided but no French title - ignoring description");
            }

            if (!empty(trim($descriptionsEn)) && isset($titles['en'])) {
                $descriptions['en'] = trim($descriptionsEn);
            } elseif (!empty(trim($descriptionsEn)) && !isset($titles['en'])) {
                $this->logger->warning("Line {$lineNumber}: English description provided but no English title - ignoring description");
            }

            // Always set descriptions (even if empty) to avoid uninitialized property error
            $section->setDescriptions($descriptions);

            // Set status - default to open (1) if not specified or invalid
            $statusValue = Episciences_Section::SECTION_OPEN_STATUS; // Default to open
            if (!empty(trim($status))) {
                $statusInt = (int)trim($status);
                if ($statusInt === Episciences_Section::SECTION_CLOSED_STATUS || $statusInt === Episciences_Section::SECTION_OPEN_STATUS) {
                    $statusValue = $statusInt;
                } else {
                    $this->logger->warning("Line {$lineNumber}: Invalid status value '{$status}', defaulting to open (1)");
                }
            }
            $section->setSetting(Episciences_Section::SETTING_STATUS, $statusValue);

            // Save section (unless dry run)
            if ($this->dryRun) {
                $this->logger->info("Line {$lineNumber}: [DRY RUN] Would create section for journal {$rvid} at position {$position} with status {$statusValue}");
                $this->importedCount++;
            } else {
                if ($section->save()) {
                    $this->logger->info("Line {$lineNumber}: Section created successfully (SID: {$section->getSid()}, Journal: {$rvid}, Position: {$position}, Status: {$statusValue})");
                    $this->importedCount++;
                } else {
                    $this->logger->error("Line {$lineNumber}: Failed to save section");
                    $this->errorCount++;
                }
            }

        } catch (Exception $e) {
            $this->logger->error("Line {$lineNumber}: Exception occurred: {$e->getMessage()}");
            $this->errorCount++;
        }
    }

    private function sectionExists(int $rvid, int $position): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        
        $select = $db->select()
            ->from(Episciences_SectionsManager::TABLE, 'COUNT(*)')
            ->where('RVID = ?', $rvid)
            ->where('POSITION = ?', $position);
            
        return (int)$db->fetchOne($select) > 0;
    }

    private function getNextPosition(int $rvid): int
    {
        if ($this->dryRun) {
            // In dry run mode, we can't query the database, so return a placeholder
            return 999;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        
        $select = $db->select()
            ->from(Episciences_SectionsManager::TABLE, 'MAX(POSITION)')
            ->where('RVID = ?', $rvid);
            
        $maxPosition = (int)$db->fetchOne($select);
        return $maxPosition + 1;
    }

    private function displaySummary(): void
    {
        $total = $this->importedCount + $this->skippedCount + $this->errorCount;
        
        $this->logger->info('=== Import Summary ===');
        $this->logger->info("Imported: {$this->importedCount} sections");
        $this->logger->info("Skipped: {$this->skippedCount} sections");
        $this->logger->info("Errors: {$this->errorCount} sections");
        $this->logger->info("Total processed: {$total}");
        
        if ($this->errorCount > 0) {
            $this->logger->warning('Import completed with errors. Check the log file for details.');
        } else {
            $this->logger->info('Import completed successfully!');
        }
    }
}

$script = new ImportSections($localopts);
$script->run();