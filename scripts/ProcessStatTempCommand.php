<?php
declare(strict_types=1);

use Episciences\Paper\Visits\BotDetector;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use geertw\IpAnonymizer\IpAnonymizer;

// Verbosity shortcuts (re-exported for brevity inside the class)
// VERBOSITY_VERBOSE     = -v   → informational messages
// VERBOSITY_VERY_VERBOSE = -vv → per-batch and detailed debug

/**
 * Symfony Console command: process STAT_TEMP → PAPER_STAT.
 *
 * Replaces the legacy scripts/stat.php (Zend_Console_Getopt).
 *
 * Processing per row:
 *   1. Validate real IP stored in STAT_TEMP
 *   2. GeoIP lookup on the real IP
 *   3. Reverse DNS lookup for domain
 *   4. Bot detection via BotDetector (UA-based, COUNTER Robots list)
 *   5. Anonymize IP (255.255.0.0 mask)
 *   6. Insert into PAPER_STAT (or skip if bot)
 *   7. Delete processed rows from STAT_TEMP
 */
class ProcessStatTempCommand extends Command
{
    protected static $defaultName = 'stats:process';

    private const STEP_OF_LINES = 500;

    /** @var array<string, int> Non-resolved IPs cache to avoid repeated reverse-DNS timeouts. */
    private array $nonResolvedIps = [];

    /**
     * When true, skip reverse-DNS lookup (gethostbyaddr) to avoid blocking the process.
     * Set via --no-dns option. Strongly recommended for large datasets.
     */
    private bool $noDns = false;

    protected function configure(): void
    {
        $this
            ->setDescription('Process visits from STAT_TEMP into PAPER_STAT')
            ->addOption('date-s', null, InputOption::VALUE_REQUIRED, 'Process up to this date (yyyy-mm-dd; default: yesterday)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process ALL records regardless of date')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'No DB writes; display each row')
            ->addOption('no-dns', null, InputOption::VALUE_NONE, 'Skip reverse-DNS lookup (recommended for large datasets — gethostbyaddr() can block for minutes per IP)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Stats processing: STAT_TEMP → PAPER_STAT');
        $this->bootstrap();

        $logger  = $this->buildLogger($io);
        $options = $this->resolveOptions($input, $io);
        if (is_int($options)) {
            return $options;
        }

        ['all' => $all, 'date' => $date, 'dryRun' => $dryRun, 'noDns' => $noDns, 'upTo' => $upTo] = $options;

        $this->noDns = $noDns;

        if ($all && !$dryRun && !$io->confirm('Process ALL records? This may take a long time.', false)) {
            $io->writeln('Operation cancelled.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Dry-run mode — no data will be written.');
        }

        // --- Status messages: written via $io so they always appear regardless of logger state ---
        $scope = $all ? 'ALL records (no date filter)' : ('records up to: ' . $date);
        $io->writeln('Scope: ' . $scope);
        $logger->info('Processing ' . $scope . '.');

        // GeoIP info — visible with -v
        $geoIpPath = (defined('GEO_IP_DATABASE_PATH') ? GEO_IP_DATABASE_PATH : '[GEO_IP_DATABASE_PATH undefined]')
            . (defined('GEO_IP_DATABASE') ? GEO_IP_DATABASE : '[GEO_IP_DATABASE undefined]');
        $io->writeln('GeoIP database: ' . $geoIpPath, OutputInterface::VERBOSITY_VERBOSE);
        $logger->info('GeoIP database: ' . $geoIpPath);

        if (!$noDns) {
            $io->warning('DNS lookup enabled (--no-dns not set). gethostbyaddr() may block for several seconds per unique IP. Use --no-dns to skip.');
            $logger->warning('DNS lookup enabled.');
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($db === null) {
            $logger->error('No database adapter available.');
            $io->error('No database adapter available. Check bootstrap/configuration.');
            return Command::FAILURE;
        }
        $io->writeln('Database adapter: OK', OutputInterface::VERBOSITY_VERBOSE);

        $giReader = $this->openGeoIpReader($logger, $io);
        if ($giReader === null) {
            return Command::FAILURE;
        }
        $io->writeln('GeoIP reader: OK', OutputInterface::VERBOSITY_VERBOSE);

        $botDetector = $this->prepareBotDetector($logger, $io);
        $totalCount  = $this->countPendingRows($db, $all, $date, $upTo);

        $io->writeln('Total records to process: ' . $totalCount);
        $logger->info('Total records to process: ' . $totalCount);

        if ($totalCount === 0) {
            $io->success('Nothing to process.');
            $giReader->close();
            return Command::SUCCESS;
        }

        $counters = $this->runProcessingLoop($db, $giReader, $botDetector, $all, $date, $upTo, $dryRun, $io, $logger);
        $giReader->close();

        $summary = $this->buildSummary($counters['processed'], $counters['ignored'], $counters['robots'], $counters['errors']);
        $logger->info($summary);
        $io->success($summary);

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public testable methods (pure logic, no I/O or DB)
    // -------------------------------------------------------------------------

    /**
     * Parse and validate command options.
     *
     * upTo=true  → --date-s was given: process rows with DHIT <= date (backfill up to that date)
     * upTo=false → default: process rows with DHIT = yesterday only (exact day, safe for daily cron)
     *
     * @return array{all: bool, date: ?string, dryRun: bool, noDns: bool, upTo: bool}|int Returns Command::FAILURE on validation error.
     */
    public function resolveOptions(InputInterface $input, SymfonyStyle $io): array|int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $all    = (bool) $input->getOption('all');
        $noDns  = (bool) $input->getOption('no-dns');
        $dateS  = $input->getOption('date-s');

        if ($all && $dateS !== null) {
            $io->error('--all and --date-s are mutually exclusive.');
            return Command::FAILURE;
        }

        $date  = null;
        $upTo  = false;
        if (!$all) {
            if ($dateS !== null) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateS) || !$this->isValidDate((string) $dateS)) {
                    $io->error('Invalid date format. Use yyyy-mm-dd.');
                    return Command::FAILURE;
                }
                $date = (string) $dateS;
                $upTo = true;
            } else {
                $date = date('Y-m-d', strtotime('-1 day'));
            }
        }

        return ['all' => $all, 'date' => $date, 'dryRun' => $dryRun, 'noDns' => $noDns, 'upTo' => $upTo];
    }

    /**
     * Classify a STAT_TEMP row's IP and User-Agent.
     * Returns 'invalid_ip', 'bot', or 'human'.
     */
    public function classifyRow(string $ip, string $userAgent, BotDetector $botDetector): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'invalid_ip';
        }
        return $botDetector->isBot($userAgent) ? 'bot' : 'human';
    }

    /**
     * Build the bind array for an INSERT INTO PAPER_STAT.
     *
     * @param array<string, mixed> $row
     * @param array{domain: string, continent: string, country: string, city: string, lat: float, lon: float} $geo
     * @return array<string, mixed>
     */
    public function buildInsertBind(array $row, array $geo, string $anonymizedIp, string $hit): array
    {
        return [
            ':DOCID'     => (int) $row['DOCID'],
            ':CONSULT'   => (string) ($row['CONSULT'] ?? ''),
            ':IP'        => $anonymizedIp,
            ':ROBOT'     => 0,
            ':AGENT'     => (string) ($row['HTTP_USER_AGENT'] ?? ''),
            ':DOMAIN'    => $geo['domain'],
            ':CONTINENT' => $geo['continent'],
            ':COUNTRY'   => $geo['country'],
            ':CITY'      => $geo['city'],
            ':LAT'       => $geo['lat'],
            ':LON'       => $geo['lon'],
            ':HIT'       => $hit,
            ':COUNTER'   => 1,
        ];
    }

    /**
     * Format the final processing summary line.
     */
    public function buildSummary(int $processed, int $ignored, int $robots, int $errors): string
    {
        return sprintf(
            'Processed: %d | Skipped (invalid IP): %d | Robots: %d | Errors: %d',
            $processed,
            $ignored,
            $robots,
            $errors
        );
    }

    /**
     * Anonymize an IPv4 address with a 255.255.0.0 mask.
     * Returns '127.0.0.1' for invalid input.
     */
    public function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return '127.0.0.1';
        }

        $anonymizer              = new IpAnonymizer();
        $anonymizer->ipv4NetMask = '255.255.0.0';
        $result                  = $anonymizer->anonymize($ip);

        return $result === '' ? '127.0.0.1' : $result;
    }

    /**
     * Normalize a DHIT timestamp to the first day of the month (COUNTER metric).
     * '2024-03-15 10:30:00' → '2024-03-01'
     */
    public function normalizeHit(string $dhit): string
    {
        return substr($dhit, 0, 7) . '-01';
    }

    /**
     * Format a dry-run output line.
     */
    public function formatDryRunLine(bool $isBot, string $ip, string $userAgent): string
    {
        $tag = $isBot ? '[BOT]  ' : '[OK]   ';
        return sprintf('%s IP: %-20s UA: %s', $tag, $ip, $userAgent);
    }

    /**
     * Perform a cached reverse DNS lookup and extract the root domain.
     * Returns '' when the IP does not resolve or when domain extraction fails.
     */
    public function extractDomain(string $ip): string
    {
        if (isset($this->nonResolvedIps[$ip])) {
            return '';
        }

        $hostname = @gethostbyaddr($ip);
        if ($hostname === false || $hostname === $ip) {
            $this->nonResolvedIps[$ip] = 1;
            return '';
        }

        if (preg_match('/(?P<domain>[\w\-]{1,63}\.[a-z\.]{2,6})$/ui', $hostname, $m)) {
            return $m['domain'];
        }

        return '';
    }

    /**
     * Build the INSERT SQL for PAPER_STAT.
     * IP is wrapped in INET_ATON() because PAPER_STAT.IP is an integer column.
     */
    public function buildInsertSql(): string
    {
        return 'INSERT INTO `PAPER_STAT` '
            . '(`DOCID`, `CONSULT`, `IP`, `ROBOT`, `AGENT`, `DOMAIN`, `CONTINENT`, `COUNTRY`, `CITY`, `LAT`, `LON`, `HIT`, `COUNTER`) '
            . 'VALUES (:DOCID, :CONSULT, INET_ATON(:IP), :ROBOT, :AGENT, :DOMAIN, :CONTINENT, :COUNTRY, :CITY, :LAT, :LON, :HIT, :COUNTER) '
            . 'ON DUPLICATE KEY UPDATE COUNTER=COUNTER+1';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildLogger(SymfonyStyle $io): Logger
    {
        $logger  = new Logger('statsProcess');
        $logFile = EPISCIENCES_LOG_PATH . 'statsProcess_' . date('Y-m-d') . '.log';
        $logDir  = dirname($logFile);

        if (is_dir($logDir) && is_writable($logDir)) {
            $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
        }

        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        return $logger;
    }

    private function openGeoIpReader(Logger $logger, SymfonyStyle $io): ?Reader
    {
        if (!defined('GEO_IP_DATABASE_PATH') || !defined('GEO_IP_DATABASE')) {
            $msg = 'GEO_IP_DATABASE_PATH / GEO_IP_DATABASE constants are not defined. '
                 . 'Add a "GEO_IP" section to config/pwd.json and run make update-geoip.';
            $logger->error($msg);
            $io->error($msg);
            return null;
        }

        try {
            return new Reader(GEO_IP_DATABASE_PATH . GEO_IP_DATABASE);
        } catch (\Throwable $e) {
            // Catches InvalidDatabaseException, \InvalidArgumentException (file not found),
            // and any \Error subclass.
            $logger->error('Cannot open GeoIP database: ' . $e->getMessage());
            $io->error('Cannot open GeoIP database: ' . $e->getMessage());
            return null;
        }
    }

    private function prepareBotDetector(Logger $logger, SymfonyStyle $io): BotDetector
    {
        $path = dirname(APPLICATION_PATH) . '/cache/counter-robots/COUNTER_Robots_list.txt';
        if (!file_exists($path)) {
            $logger->warning('COUNTER Robots list not found at ' . $path . '. Run stats:update-robots-list first.');
            $io->warning('COUNTER Robots list not found — bot detection may be incomplete.');
        }
        return new BotDetector($path);
    }

    private function countPendingRows(Zend_Db_Adapter_Abstract $db, bool $all, ?string $date, bool $upTo): int
    {
        $select = $db->select()->from('STAT_TEMP', new Zend_Db_Expr("COUNT('*')"));
        if (!$all && $date !== null) {
            if ($upTo) {
                $select->where("DATE(DHIT) <= ?", $date);
            } else {
                $select->where("DATE(DHIT) = ?", $date);
            }
        }
        return (int) $db->fetchOne($select);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBatch(Zend_Db_Adapter_Abstract $db, bool $all, ?string $date, bool $upTo): array
    {
        $select = $db->select()->from('STAT_TEMP', new Zend_Db_Expr('*, INET_NTOA(IP) as TIP'));
        if (!$all && $date !== null) {
            if ($upTo) {
                $select->where("DATE(DHIT) <= ?", $date);
            } else {
                $select->where("DATE(DHIT) = ?", $date);
            }
        }
        $select->order('DHIT ASC')->limit(self::STEP_OF_LINES);
        return $db->fetchAll($select);
    }

    /**
     * Dispatch to the dry-run or insert path and return processing counters.
     *
     * @return array{processed: int, ignored: int, robots: int, errors: int}
     */
    private function runProcessingLoop(
        Zend_Db_Adapter_Abstract $db,
        Reader $giReader,
        BotDetector $botDetector,
        bool $all,
        ?string $date,
        bool $upTo,
        bool $dryRun,
        SymfonyStyle $io,
        Logger $logger
    ): array {
        if ($dryRun) {
            $this->runDryRunBatches($db, $all, $date, $upTo, $botDetector, $io);
            return ['processed' => 0, 'ignored' => 0, 'robots' => 0, 'errors' => 0];
        }

        return $this->runInsertBatches($db, $all, $date, $upTo, $giReader, $botDetector, $logger, $io);
    }

    /**
     * Iterate batches in dry-run mode: output one line per row, no DB writes.
     */
    private function runDryRunBatches(
        Zend_Db_Adapter_Abstract $db,
        bool $all,
        ?string $date,
        bool $upTo,
        BotDetector $botDetector,
        SymfonyStyle $io
    ): void {
        while (true) {
            $rows = $this->fetchBatch($db, $all, $date, $upTo);
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $row) {
                $io->writeln($this->formatRowOutput($row, $botDetector));
            }
        }
    }

    /**
     * Format one STAT_TEMP row as a dry-run output line.
     * Returns a [SKIP], [BOT], or [OK] prefixed string.
     *
     * @param array<string, mixed> $row
     */
    public function formatRowOutput(array $row, BotDetector $botDetector): string
    {
        $ip        = (string) ($row['TIP'] ?? '');
        $userAgent = (string) ($row['HTTP_USER_AGENT'] ?? '');
        $outcome   = $this->classifyRow($ip, $userAgent, $botDetector);

        if ($outcome === 'invalid_ip') {
            return '[SKIP]  Invalid IP: ' . $ip;
        }

        return $this->formatDryRunLine($outcome === 'bot', $ip, $userAgent);
    }

    /**
     * Iterate batches in insert mode: classify, geo-lookup, write PAPER_STAT, delete STAT_TEMP.
     *
     * -v  → batch progress line after each batch
     * -vv → per-row classification output
     *
     * @return array{processed: int, ignored: int, robots: int, errors: int}
     */
    private function runInsertBatches(
        Zend_Db_Adapter_Abstract $db,
        bool $all,
        ?string $date,
        bool $upTo,
        Reader $giReader,
        BotDetector $botDetector,
        Logger $logger,
        SymfonyStyle $io
    ): array {
        $counters = ['processed' => 0, 'ignored' => 0, 'robots' => 0, 'errors' => 0];

        $insertPrepared = $db->prepare($this->buildInsertSql());

        $dateOp     = $upTo ? '<=' : '=';
        $deleteSql  = $all
            ? 'DELETE FROM `STAT_TEMP` ORDER BY DHIT LIMIT ' . self::STEP_OF_LINES
            : "DELETE FROM `STAT_TEMP` WHERE DATE(DHIT) $dateOp :DATE_TO_DEL ORDER BY DHIT LIMIT " . self::STEP_OF_LINES;
        $deletePrepared = $db->prepare($deleteSql);

        $batchNumber = 0;
        while (true) {
            $rows = $this->fetchBatch($db, $all, $date, $upTo);
            if (empty($rows)) {
                break;
            }

            $batchNumber++;
            $batch = $this->processBatchRows($rows, $giReader, $botDetector, $insertPrepared, $logger, $io);
            foreach ($batch as $key => $delta) {
                $counters[$key] += $delta;
            }

            $progress = sprintf(
                'Batch #%d (%d rows) — processed=%d, ignored=%d, robots=%d, errors=%d',
                $batchNumber,
                count($rows),
                $counters['processed'],
                $counters['ignored'],
                $counters['robots'],
                $counters['errors']
            );
            $logger->info($progress);
            $io->writeln($progress, OutputInterface::VERBOSITY_VERBOSE);

            $this->deleteBatch($deletePrepared, $all, $date, $logger);
        }

        return $counters;
    }

    /**
     * Process one batch of rows: classify, geo-lookup, and insert human visits.
     *
     * With -vv, outputs one line per row (same format as dry-run).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{processed: int, ignored: int, robots: int, errors: int}
     */
    private function processBatchRows(
        array $rows,
        Reader $giReader,
        BotDetector $botDetector,
        mixed $insertPrepared,
        Logger $logger,
        SymfonyStyle $io
    ): array {
        $counters = ['processed' => 0, 'ignored' => 0, 'robots' => 0, 'errors' => 0];

        foreach ($rows as $row) {
            $ip        = (string) ($row['TIP'] ?? '');
            $userAgent = (string) ($row['HTTP_USER_AGENT'] ?? '');
            $outcome   = $this->classifyRow($ip, $userAgent, $botDetector);

            // -vv: show per-row classification
            $io->writeln($this->formatRowOutput($row, $botDetector), OutputInterface::VERBOSITY_VERY_VERBOSE);

            if ($outcome === 'invalid_ip') {
                $counters['ignored']++;
                continue;
            }

            if ($outcome === 'bot') {
                $counters['robots']++;
                continue;
            }

            $bind = $this->buildInsertBind(
                $row,
                $this->geoLookup($giReader, $ip, $logger),
                $this->anonymizeIp($ip),
                $this->normalizeHit((string) ($row['DHIT'] ?? ''))
            );

            try {
                $insertPrepared->execute($bind);
                $counters['processed']++;
            } catch (Zend_Db_Statement_Exception $e) {
                $logger->error('DB insert error: ' . $e->getMessage());
                $counters['errors']++;
            }
        }

        return $counters;
    }

    /**
     * Execute the DELETE batch statement, logging any failure.
     */
    private function deleteBatch(mixed $deletePrepared, bool $all, ?string $date, Logger $logger): void
    {
        try {
            $all
                ? $deletePrepared->execute()
                : $deletePrepared->execute([':DATE_TO_DEL' => $date]);
        } catch (Zend_Db_Statement_Exception $e) {
            $logger->error('DB delete error: ' . $e->getMessage());
        }
    }

    /**
     * Perform GeoIP lookup + reverse DNS on a real IP.
     *
     * @return array{domain: string, continent: string, country: string, city: string, lat: float, lon: float}
     */
    private function geoLookup(Reader $giReader, string $ip, Logger $logger): array
    {
        $data = ['domain' => '', 'continent' => '', 'country' => '', 'city' => '', 'lat' => 0.0, 'lon' => 0.0];

        if (!$this->noDns) {
            $data['domain'] = $this->extractDomain($ip);
        }

        try {
            $record            = $giReader->city($ip)->jsonSerialize();
            $data['continent'] = (string) ($record['continent']['code'] ?? '');
            $data['country']   = (string) ($record['country']['iso_code'] ?? '');
            $data['city']      = '';
            $data['lat']       = (float) ($record['location']['latitude'] ?? 0.0);
            $data['lon']       = (float) ($record['location']['longitude'] ?? 0.0);
        } catch (AddressNotFoundException|InvalidDatabaseException $e) {
            $logger->warning('GeoIP lookup failed for ' . $ip . ': ' . $e->getMessage());
        }

        return $data;
    }

    private function isValidDate(string $date): bool
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return false;
        }
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
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
