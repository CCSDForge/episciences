<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: parse Apache access logs → STAT_TEMP.
 *
 * Replaces the legacy scripts/UpdateStatistics.php.
 *
 * Processing per log file:
 *   1. Resolve log file path for the target date (plain or .gz)
 *   2. Parse each line: extract docId, IP, User-Agent, timestamp
 *   3. Filter to article notice/file-download patterns only
 *   4. Bulk-insert matching rows into STAT_TEMP (transaction)
 *   5. Record processed date in STAT_PROCESSING_LOG (duplicate prevention)
 *
 * The stats:process cron (ProcessStatTempCommand) then enriches STAT_TEMP
 * rows with GeoIP data and moves them to PAPER_STAT.
 */
class ImportApacheLogsCommand extends Command
{
    protected static $defaultName = 'stats:import-logs';

    /** Apache combined-log patterns mapped to CONSULT type. */
    private const PATTERNS = [
        'notice'   => '/GET \/articles\/(\d+) HTTP/',
        'file'     => '/GET \/articles\/(\d+)\/download HTTP/',
        'file'     => '/GET \/articles\/(\d+)\/preview HTTP/',
    ];

    private string $logsBasePath = '../logs/httpd';

    private ?ProgressBar $progressBar = null;

    protected function configure(): void
    {
        $this
            ->setDescription('Parse Apache access logs and insert article visits into STAT_TEMP.')
            ->addOption('rvcode',      null, InputOption::VALUE_REQUIRED, 'Journal code (e.g., mbj) — mutually exclusive with --all')
            ->addOption('all',         null, InputOption::VALUE_NONE,     'Process all journals with is_new_front_switched = yes — mutually exclusive with --rvcode')
            ->addOption('date',        null, InputOption::VALUE_OPTIONAL, 'Single date to process (YYYY-MM-DD; default: yesterday)')
            ->addOption('month',       null, InputOption::VALUE_OPTIONAL, 'Process entire month (YYYY-MM)')
            ->addOption('year',        null, InputOption::VALUE_OPTIONAL, 'Process entire year (YYYY)')
            ->addOption('start-date',  null, InputOption::VALUE_OPTIONAL, 'Start of date range (YYYY-MM-DD)')
            ->addOption('end-date',    null, InputOption::VALUE_OPTIONAL, 'End of date range (YYYY-MM-DD)')
            ->addOption('force',       null, InputOption::VALUE_NONE,     'Reprocess dates already recorded in STAT_PROCESSING_LOG')
            ->addOption('logs-path',   null, InputOption::VALUE_OPTIONAL, 'Base path to Apache log directory', $this->logsBasePath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        $io = new SymfonyStyle($input, $output);
        $io->title('stats:import-logs — Apache logs → STAT_TEMP');

        $this->bootstrap();

        $logger = $this->buildLogger($io);

        $rvcode = $input->getOption('rvcode');
        $all    = (bool) $input->getOption('all');

        if ($rvcode && $all) {
            $io->error('--rvcode and --all are mutually exclusive.');
            return Command::FAILURE;
        }

        if (!$rvcode && !$all) {
            $io->error('Specify either --rvcode=CODE or --all.');
            return Command::FAILURE;
        }

        try {
            $dateRange = $this->resolveDateRange($input);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->logsBasePath = (string) $input->getOption('logs-path');
        $io->writeln('Logs path : ' . $this->logsBasePath);
        $logger->info('Logs path: ' . $this->logsBasePath);

        $rvcodes = $all ? $this->fetchNewFrontRvcodes($logger) : [$rvcode];

        if (empty($rvcodes)) {
            $io->warning('No journal found with is_new_front_switched = yes.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Journals to process: %s', implode(', ', $rvcodes)));
        $logger->info(sprintf('Journals to process: %s', implode(', ', $rvcodes)));

        $force         = (bool) $input->getOption('force');
        $totalInserted = 0;

        foreach ($rvcodes as $rvcode) {
            $io->section("Journal: {$rvcode}");
            $logger->info("--- Journal: {$rvcode} ---");
            $siteName = $rvcode . '.episciences.org';

            foreach ($dateRange as $dateObj) {
                $date = $dateObj->format('Y-m-d');

                if (!$force && $this->isDateAlreadyProcessed($rvcode, $date)) {
                    $io->writeln("Skipping {$date} (already processed — use --force to reprocess).");
                    $logger->info("Skipping {$date}: already in STAT_PROCESSING_LOG.");
                    continue;
                }

                $logFile = $this->buildLogFilePath($siteName, $dateObj);
                if ($logFile === null) {
                    $io->warning("No log file found for {$date}.");
                    $logger->warning("Log file not found for {$date}.");
                    continue;
                }

                $logger->info("Processing {$logFile}");
                $accesses = $this->collectArticleAccesses($logFile, $date, $logger);

                if (empty($accesses)) {
                    $io->writeln("No article accesses found for {$date}.");
                    $logger->info("No article accesses for {$date}.");
                    $this->markDateAsProcessed($rvcode, $date, $logFile, 0, 'success');
                    continue;
                }

                $io->writeln(sprintf('Found %d accesses for %s.', count($accesses), $date));
                $logger->info(sprintf('Found %d accesses for %s.', count($accesses), $date));

                try {
                    $inserted = $this->insertIntoStatTemp($accesses, $io, $logger);
                } catch (\Exception $e) {
                    $io->error("DB error for {$date}: " . $e->getMessage());
                    $logger->error("DB error for {$date}: " . $e->getMessage());
                    $this->markDateAsProcessed($rvcode, $date, $logFile, 0, 'error');
                    return Command::FAILURE;
                }

                $this->markDateAsProcessed($rvcode, $date, $logFile, $inserted, 'success');
                $io->writeln("Inserted {$inserted} records for {$date}.");
                $logger->info("Inserted {$inserted} records for {$date}.");
                $totalInserted += $inserted;
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $io->success(sprintf('Done. Total inserted: %d record(s) in %s s.', $totalInserted, $elapsed));
        $logger->info(sprintf('Done. Total inserted: %d in %s s.', $totalInserted, $elapsed));

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Date resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the list of dates to process from command options.
     *
     * @return \DateTime[]
     * @throws \Exception
     */
    private function resolveDateRange(InputInterface $input): array
    {
        $date      = $input->getOption('date');
        $month     = $input->getOption('month');
        $year      = $input->getOption('year');
        $startDate = $input->getOption('start-date');
        $endDate   = $input->getOption('end-date');

        $yesterday = (new \DateTime('yesterday'))->format('Y-m-d');

        // Count explicitly set options (exclude implicit default date)
        $explicit = 0;
        if ($date !== null && $date !== $yesterday) $explicit++;
        if ($month)                                  $explicit++;
        if ($year)                                   $explicit++;
        if ($startDate || $endDate)                  $explicit++;

        if ($explicit > 1) {
            throw new \Exception('Specify only one of: --date, --month, --year, or --start-date/--end-date.');
        }

        if ($year) {
            return $this->yearRange($year);
        }

        if ($month) {
            return $this->monthRange($month);
        }

        if ($startDate || $endDate) {
            if (!$startDate || !$endDate) {
                throw new \Exception('--start-date and --end-date must both be provided.');
            }
            return $this->dateRange($startDate, $endDate);
        }

        $target = ($date !== null && $date !== $yesterday) ? $date : $yesterday;
        return [$this->parseDate($target)];
    }

    /** @return \DateTime[] */
    private function yearRange(string $year): array
    {
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new \Exception('Invalid --year format. Use YYYY.');
        }
        return $this->dateRange($year . '-01-01', $year . '-12-31');
    }

    /** @return \DateTime[] */
    private function monthRange(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \Exception('Invalid --month format. Use YYYY-MM.');
        }
        $start = $this->parseDate($month . '-01');
        return $this->dateRange($month . '-01', $start->format('Y-m-t'));
    }

    /** @return \DateTime[] */
    private function dateRange(string $startDate, string $endDate): array
    {
        $start = $this->parseDate($startDate);
        $end   = $this->parseDate($endDate);

        if ($start > $end) {
            throw new \Exception('--start-date must be before or equal to --end-date.');
        }

        $dates   = [];
        $current = clone $start;
        while ($current <= $end) {
            $dates[] = clone $current;
            $current->modify('+1 day');
        }
        return $dates;
    }

    private function parseDate(string $date): \DateTime
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            throw new \Exception("Invalid date format '{$date}'. Use YYYY-MM-DD.");
        }
        $dt->setTime(0, 0, 0);
        return $dt;
    }

    // -------------------------------------------------------------------------
    // Log file resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the log file path for a given site and date, trying plain then .gz.
     */
    private function buildLogFilePath(string $siteName, \DateTime $date): ?string
    {
        $base = sprintf(
            '%s/%s/%s/%s/%s-%s.access_log',
            $this->logsBasePath,
            $siteName,
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $siteName
        );

        if (file_exists($base)) {
            return $base;
        }
        if (file_exists($base . '.gz')) {
            return $base . '.gz';
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Log parsing
    // -------------------------------------------------------------------------

    /**
     * Parse a log file and return matching article access records for $targetDate.
     *
     * @return array<int, array{doc_id: int, ip: int, user_agent: string, date_time: string, timestamp: int, access_type: string}>
     */
    private function collectArticleAccesses(string $logFile, string $targetDate, Logger $logger): array
    {
        $startOfDay = strtotime($targetDate . ' 00:00:00');
        $endOfDay   = strtotime($targetDate . ' 23:59:59');

        $accesses = [];
        $handle   = $this->openLogFile($logFile);
        $lines    = 0;
        $matched  = 0;

        while (($line = $this->readLine($handle, $logFile)) !== false) {
            $lines++;

            $timestamp = $this->extractTimestamp($line, $logger);
            if ($timestamp < $startOfDay || $timestamp > $endOfDay) {
                continue;
            }

            $accessInfo = $this->matchAccessPattern($line);
            if ($accessInfo === null) {
                continue;
            }

            $matched++;
            ['accessType' => $accessType, 'docId' => $rawDocId] = $accessInfo;

            $docId = filter_var($rawDocId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999999]]);
            if ($docId === false) {
                $logger->warning("Invalid docId '{$rawDocId}', skipping.");
                continue;
            }

            $ipv4 = $this->extractIP($line);
            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                $logger->warning("Invalid IP '{$ipv4}', skipping.");
                continue;
            }

            $accesses[] = [
                'doc_id'      => $docId,
                'ip'          => (int) sprintf('%u', ip2long($ipv4)),
                'user_agent'  => $this->sanitizeUserAgent($this->extractUserAgent($line)),
                'date_time'   => date('Y-m-d H:i:s', $timestamp),
                'timestamp'   => $timestamp,
                'access_type' => $accessType,
            ];
        }

        $this->closeFile($handle, $logFile);

        usort($accesses, static fn(array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        $logger->info(sprintf('Scanned %d lines, matched %d accesses for %s.', $lines, $matched, $targetDate));

        return $accesses;
    }

    /** Match a log line against article patterns; returns ['accessType', 'docId'] or null. */
    private function matchAccessPattern(string $line): ?array
    {
        // notice pattern
        if (preg_match('/GET \/articles\/(\d+) HTTP/', $line, $m)) {
            return ['accessType' => 'notice', 'docId' => $m[1]];
        }
        // file download or preview
        if (preg_match('/GET \/articles\/(\d+)\/(?:download|preview) HTTP/', $line, $m)) {
            return ['accessType' => 'file', 'docId' => $m[1]];
        }
        return null;
    }

    private function extractTimestamp(string $line, Logger $logger): int
    {
        if (preg_match('/\[(\d{2}\/[A-Za-z]+\/\d{4}):(\d{2}:\d{2}:\d{2}) [+-]\d{4}\]/', $line, $m)) {
            $dt = \DateTime::createFromFormat('d/M/Y H:i:s', $m[1] . ' ' . $m[2]);
            if ($dt !== false) {
                return $dt->getTimestamp();
            }
        }
        $logger->warning('Could not extract timestamp from line; using current time.');
        return time();
    }

    private function extractIP(string $line): string
    {
        // Logs may have a syslog prefix before the Apache combined-log fields:
        //   Dec 15 12:36:46 hostname httpd: example.org 1.2.3.4 - - [...]
        // Match the client IP by its position just before the "- -" ident/auth tokens.
        if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3}) - -/', $line, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extractUserAgent(string $line): string
    {
        // Combined Log Format: last quoted field is the User-Agent
        preg_match_all('/"([^"]*)"/', $line, $m);
        $parts = $m[1] ?? [];
        return count($parts) >= 2 ? end($parts) : '';
    }

    private function sanitizeUserAgent(string $ua): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $ua) ?? '';
        return mb_substr($clean, 0, 2000, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Database insertion
    // -------------------------------------------------------------------------

    /**
     * Bulk-insert article accesses into STAT_TEMP inside a transaction.
     *
     * @param array<int, array<string, mixed>> $accesses
     * @throws \Exception on DB error (transaction is rolled back)
     */
    private function insertIntoStatTemp(array $accesses, SymfonyStyle $io, Logger $logger): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($db === null) {
            throw new \Exception('No database adapter available.');
        }

        $sql  = 'INSERT INTO STAT_TEMP (DOCID, IP, HTTP_USER_AGENT, DHIT, CONSULT) VALUES (?, ?, ?, ?, ?)';
        $stmt = $db->prepare($sql);

        $this->progressBar = $io->createProgressBar(count($accesses));
        $this->progressBar->start();

        $db->beginTransaction();
        $inserted = 0;

        try {
            foreach ($accesses as $access) {
                $stmt->execute([
                    (int)    $access['doc_id'],
                    (int)    $access['ip'],
                    (string) $access['user_agent'],
                    (string) $access['date_time'],
                    (string) $access['access_type'],
                ]);
                $inserted++;
                $this->progressBar->advance();
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $logger->error('Transaction rolled back: ' . $e->getMessage());
            throw $e;
        }

        $this->progressBar->finish();
        $io->newLine(2);

        return $inserted;
    }

    // -------------------------------------------------------------------------
    // STAT_PROCESSING_LOG
    // -------------------------------------------------------------------------

    /**
     * Return the list of journal rvcode values that have is_new_front_switched = 'yes'.
     *
     * @return string[]
     */
    private function fetchNewFrontRvcodes(Logger $logger): array
    {
        $db   = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql  = "SELECT CODE FROM REVIEW WHERE is_new_front_switched = 'yes' ORDER BY CODE";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $logger->info(sprintf('Found %d journal(s) with is_new_front_switched = yes.', count($codes)));
        return $codes;
    }

    private function isDateAlreadyProcessed(string $rvcode, string $date): bool
    {
        $db   = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql  = 'SELECT COUNT(*) FROM STAT_PROCESSING_LOG WHERE JOURNAL_CODE = ? AND PROCESSED_DATE = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$rvcode, $date]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function markDateAsProcessed(string $rvcode, string $date, string $filePath, int $records, string $status): void
    {
        $db  = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = 'INSERT INTO STAT_PROCESSING_LOG (JOURNAL_CODE, PROCESSED_DATE, FILE_PATH, RECORDS_PROCESSED, STATUS)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    PROCESSED_AT      = CURRENT_TIMESTAMP,
                    FILE_PATH         = VALUES(FILE_PATH),
                    RECORDS_PROCESSED = VALUES(RECORDS_PROCESSED),
                    STATUS            = VALUES(STATUS)';
        $stmt = $db->prepare($sql);
        $stmt->execute([$rvcode, $date, $filePath, $records, $status]);
    }

    // -------------------------------------------------------------------------
    // File helpers (plain + gzip)
    // -------------------------------------------------------------------------

    /** @return resource */
    private function openLogFile(string $logFile)
    {
        if (str_ends_with($logFile, '.gz')) {
            $handle = gzopen($logFile, 'r');
        } else {
            $handle = fopen($logFile, 'r');
        }

        if ($handle === false) {
            throw new \RuntimeException("Cannot open log file: {$logFile}");
        }

        return $handle;
    }

    /** @param resource $handle */
    private function readLine($handle, string $logFile): string|false
    {
        return str_ends_with($logFile, '.gz') ? gzgets($handle) : fgets($handle);
    }

    /** @param resource $handle */
    private function closeFile($handle, string $logFile): void
    {
        str_ends_with($logFile, '.gz') ? gzclose($handle) : fclose($handle);
    }

    // -------------------------------------------------------------------------
    // Bootstrap (identical pattern to ProcessStatTempCommand / GenerateDownloadKpiCommand)
    // -------------------------------------------------------------------------

    private function buildLogger(SymfonyStyle $io): Logger
    {
        $logger  = new Logger('importApacheLogs');
        $logFile = EPISCIENCES_LOG_PATH . 'importApacheLogs_' . date('Y-m-d') . '.log';
        $logDir  = dirname($logFile);

        if (is_dir($logDir) && is_writable($logDir)) {
            $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
        }

        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        return $logger;
    }

    private function bootstrap(): void
    {
        // Guard against re-execution when multiple tests call execute() in the same process.
        if (defined('REVIEW_TMP_PATH')) {
            return;
        }

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