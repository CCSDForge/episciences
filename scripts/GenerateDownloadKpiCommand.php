<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: aggregate download KPIs for all published articles.
 *
 * Reads PAPER_STAT for every published paper (STATUS=16 with a DOI) and writes
 * a JSON file keyed by journal rvcode, suitable for Next.js visualisations.
 */
class GenerateDownloadKpiCommand extends Command
{
    protected static $defaultName = 'stats:download-kpi';

    private const CONSULT_FILE   = 'notice'; // page view
    private const CONSULT_NOTICE = 'file';   // file download

    protected function configure(): void
    {
        $this
            ->setDescription('Aggregate download KPIs for all published articles and write a JSON file.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Destination path for the JSON file (default: data/kpi_downloads.json)')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict to one journal (RV code)')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print the JSON output')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write the file; print a summary instead');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $rvcode = $input->getOption('rvcode');
        $pretty = (bool) $input->getOption('pretty');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Download KPI generation');

        $this->bootstrap();

        $logger = $this->buildLogger($io);

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no file will be written.');
        }

        $rvid = null;
        if ($rvcode !== null) {
            $review = Episciences_ReviewsManager::findByRvcode((string) $rvcode);
            if (!$review instanceof Episciences_Review) {
                $io->error("No journal found for RV code '{$rvcode}'.");
                return Command::FAILURE;
            }
            $rvid = $review->getRvid();
            $logger->info("Filtering on journal: {$rvcode} (RVID {$rvid})");
        }

        $outputPath = $this->resolveOutputPath(
            (string) APPLICATION_PATH,
            $input->getOption('output') !== null ? (string) $input->getOption('output') : null
        );

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($db === null) {
            $io->error('No database adapter available. Check bootstrap/configuration.');
            return Command::FAILURE;
        }

        $logger->info('Fetching published papers …');
        $paperRows = $this->fetchPublishedPapers($db, $rvid);
        $logger->info(sprintf('Found %d paper rows (before version deduplication).', count($paperRows)));

        $logger->info('Fetching download statistics …');
        $statRows = $this->fetchStats($db, $rvid);
        $logger->info(sprintf('Found %d stat rows.', count($statRows)));

        $papers     = $this->aggregatePapers($paperRows);
        $stats      = $this->aggregateStats($statRows);
        $journalMap = $this->buildJournalMap($papers, $stats);
        $payload    = $this->buildPayload($journalMap, (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));

        $totalPapers  = $payload['total_papers'];
        $totalJournals = $payload['total_journals'];

        $logger->info(sprintf('Aggregated %d unique papers across %d journals.', $totalPapers, $totalJournals));
        $io->writeln(sprintf('Papers: %d | Journals: %d', $totalPapers, $totalJournals));

        if ($dryRun) {
            $io->success('Dry-run complete — no file written.');
            return Command::SUCCESS;
        }

        try {
            $this->writeJson($payload, $outputPath, $pretty);
        } catch (\RuntimeException $e) {
            $logger->error('Failed to write JSON: ' . $e->getMessage());
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $logger->info('KPI file written: ' . $outputPath);
        $io->success('KPI file written: ' . $outputPath);

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public pure helpers (DB-free — tested without bootstrap)
    // -------------------------------------------------------------------------

    /**
     * Return the resolved output path.
     * Falls back to <project_root>/data/kpi_downloads.json when $custom is null.
     */
    public function resolveOutputPath(string $applicationPath, ?string $custom): string
    {
        if ($custom !== null && $custom !== '') {
            return $custom;
        }

        return rtrim(dirname($applicationPath), '/') . '/data/kpi_downloads.json';
    }

    /**
     * Deduplicate raw paper rows by PAPERID.
     * Multiple rows for the same PAPERID (= different versions) are collapsed:
     *   - DOI      : taken from the first row (identical across versions)
     *   - RVID / RVCODE / JOURNAL_NAME : idem
     *   - PUBLICATION_DATE : earliest non-null value wins
     *
     * Rows with an empty or null DOI are silently skipped.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>> Keyed by PAPERID (int)
     */
    public function aggregatePapers(array $rows): array
    {
        /** @var array<int, array<string, mixed>> $papers */
        $papers = [];

        foreach ($rows as $row) {
            $doi      = (string) ($row['DOI'] ?? '');
            $paperid  = (int) ($row['PAPERID'] ?? 0);

            if ($doi === '' || $paperid === 0) {
                continue;
            }

            if (!isset($papers[$paperid])) {
                $papers[$paperid] = [
                    'doi'              => $doi,
                    'paperid'          => $paperid,
                    'rvid'             => (int) ($row['RVID'] ?? 0),
                    'rvcode'           => (string) ($row['RVCODE'] ?? ''),
                    'journal_name'     => (string) ($row['JOURNAL_NAME'] ?? ''),
                    'publication_date' => $this->normalizeDate((string) ($row['PUBLICATION_DATE'] ?? '')),
                ];
                continue;
            }

            // Keep the earliest publication date across versions.
            $existing = $papers[$paperid]['publication_date'];
            $candidate = $this->normalizeDate((string) ($row['PUBLICATION_DATE'] ?? ''));
            if ($candidate !== null && ($existing === null || $candidate < $existing)) {
                $papers[$paperid]['publication_date'] = $candidate;
            }
        }

        return $papers;
    }

    /**
     * Build a nested stats map from raw PAPER_STAT rows.
     *
     * Return shape: [paperid => [consult => [month => total]]]
     * where consult is 'file' or 'notice', month is 'YYYY-MM'.
     *
     * @param array<int, array<string, mixed>> $statRows
     * @return array<int, array<string, array<string, int>>>
     */
    public function aggregateStats(array $statRows): array
    {
        /** @var array<int, array<string, array<string, int>>> $stats */
        $stats = [];

        foreach ($statRows as $row) {
            $paperid = (int) ($row['PAPERID'] ?? 0);
            $consult = (string) ($row['CONSULT'] ?? '');
            $month   = (string) ($row['MONTH'] ?? '');
            $total   = (int) ($row['total'] ?? 0);

            if ($paperid === 0 || $consult === '' || $month === '') {
                continue;
            }

            if (!isset($stats[$paperid][$consult][$month])) {
                $stats[$paperid][$consult][$month] = 0;
            }
            $stats[$paperid][$consult][$month] += $total;
        }

        return $stats;
    }

    /**
     * Merge deduplicated papers and stats into a per-journal map.
     *
     * Return shape:
     * [rvcode => ['rvid' => int, 'name' => string, 'papers' => list<array>]]
     *
     * @param array<int, array<string, mixed>>                         $papers  Output of aggregatePapers()
     * @param array<int, array<string, array<string, int>>>            $stats   Output of aggregateStats()
     * @return array<string, array<string, mixed>>
     */
    public function buildJournalMap(array $papers, array $stats): array
    {
        /** @var array<string, array<string, mixed>> $journals */
        $journals = [];

        foreach ($papers as $paperid => $paper) {
            $rvcode = $paper['rvcode'];

            if (!isset($journals[$rvcode])) {
                $journals[$rvcode] = [
                    'rvid'   => $paper['rvid'],
                    'name'   => $paper['journal_name'],
                    'papers' => [],
                ];
            }

            $paperStats = $stats[$paperid] ?? [];

            $downloads = $this->buildMetricEntry($paperStats[self::CONSULT_NOTICE] ?? []);
            $pageViews = $this->buildMetricEntry($paperStats[self::CONSULT_FILE] ?? []);

            /** @var list<array<string, mixed>> $journalPapers */
            $journalPapers   = $journals[$rvcode]['papers'];
            $journalPapers[] = [
                'doi'              => $paper['doi'],
                'paperid'          => $paperid,
                'publication_date' => $paper['publication_date'],
                'downloads'        => $downloads,
                'page_views'       => $pageViews,
            ];
            $journals[$rvcode]['papers'] = $journalPapers;
        }

        ksort($journals);

        return $journals;
    }

    /**
     * Wrap the journal map in the top-level JSON envelope.
     *
     * @param array<string, array<string, mixed>> $journalMap  Output of buildJournalMap()
     * @param string                              $generatedAt ISO 8601 timestamp
     * @return array<string, mixed>
     */
    public function buildPayload(array $journalMap, string $generatedAt): array
    {
        $totalPapers = 0;
        foreach ($journalMap as $journal) {
            /** @var list<mixed> $journalPapers */
            $journalPapers = $journal['papers'];
            $totalPapers  += count($journalPapers);
        }

        // Attach papers_count to each journal entry.
        $enriched = [];
        foreach ($journalMap as $rvcode => $journal) {
            /** @var list<mixed> $journalPapers */
            $journalPapers        = $journal['papers'];
            $enriched[$rvcode]    = array_merge($journal, ['papers_count' => count($journalPapers)]);
        }

        return [
            'generated_at'   => $generatedAt,
            'total_papers'   => $totalPapers,
            'total_journals' => count($journalMap),
            'journals'       => $enriched,
        ];
    }

    /**
     * Encode and write the payload to disk.
     *
     * @param array<string, mixed> $data
     * @throws \RuntimeException When the file cannot be written.
     */
    public function writeJson(array $data, string $path, bool $pretty): void
    {
        $dir = dirname($path);
        // Suppress the E_WARNING from mkdir() — we inspect the return value ourselves.
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException(sprintf('Cannot write to "%s".', $path));
        }

        chmod($path, 0644);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a metric entry {total, by_month} from a [month => count] map.
     *
     * @param array<string, int> $byMonth
     * @return array{total: int, by_month: array<string, int>}
     */
    private function buildMetricEntry(array $byMonth): array
    {
        ksort($byMonth);
        return [
            'total'    => array_sum($byMonth),
            'by_month' => $byMonth,
        ];
    }

    /**
     * Extract 'YYYY-MM-DD' from a datetime string, or return null.
     */
    private function normalizeDate(string $raw): ?string
    {
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        return substr($raw, 0, 10);
    }

    /**
     * Fetch all published papers with their journal info.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPublishedPapers(Zend_Db_Adapter_Abstract $db, ?int $rvid): array
    {
        $select = $db->select()
            ->from(['p' => T_PAPERS], ['p.PAPERID', 'p.DOCID', 'p.DOI', 'p.RVID', 'p.PUBLICATION_DATE'])
            ->join(['r' => T_REVIEW], 'p.RVID = r.RVID', ['RVCODE' => 'r.CODE', 'JOURNAL_NAME' => 'r.NAME'])
            ->where('p.STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->where('p.DOI IS NOT NULL')
            ->where('p.DOI != ?', '')
            ->order(['r.CODE', 'p.DOI']);

        if ($rvid !== null) {
            $select->where('p.RVID = ?', $rvid);
        }

        return $db->fetchAll($select);
    }

    /**
     * Fetch aggregated stats for all published papers, collapsed to PAPERID + month.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchStats(Zend_Db_Adapter_Abstract $db, ?int $rvid): array
    {
        $select = $db->select()
            ->from(['ps' => T_PAPER_VISITS], [
                'CONSULT' => 'ps.CONSULT',
                'MONTH'   => new Zend_Db_Expr("SUBSTRING(ps.HIT, 1, 7)"),
                'total'   => new Zend_Db_Expr('SUM(ps.COUNTER)'),
            ])
            ->join(['p' => T_PAPERS], 'ps.DOCID = p.DOCID', ['PAPERID' => 'p.PAPERID'])
            ->where('ps.ROBOT = 0')
            ->where('ps.CONSULT IN (?)', ['notice', 'file'])
            ->where('p.STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->where('p.DOI IS NOT NULL')
            ->where('p.DOI != ?', '')
            ->group(['p.PAPERID', 'ps.CONSULT', new Zend_Db_Expr("SUBSTRING(ps.HIT, 1, 7)")]);

        if ($rvid !== null) {
            $select->where('p.RVID = ?', $rvid);
        }

        return $db->fetchAll($select);
    }

    private function buildLogger(SymfonyStyle $io): Logger
    {
        $logger  = new Logger('downloadKpi');
        $logFile = EPISCIENCES_LOG_PATH . 'downloadKpi_' . date('Y-m-d') . '.log';
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
