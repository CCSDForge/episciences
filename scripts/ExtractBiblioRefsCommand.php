<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Episciences\Console\ProgressAwareStreamHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: pre-extract bibliographic references for papers via the Biblioref /api/extract endpoint.
 *
 * Designed for cron usage. For each matching paper, calls GET /api/extract?url=<article_url>.
 * The API caches results server-side: already-processed papers return immediately.
 *
 * Skips execution entirely when EPISCIENCES_BIBLIOREF['ENABLE'] is false.
 */
class ExtractBiblioRefsCommand extends Command
{
    protected static $defaultName = 'enrichment:extract-biblio-refs';

    public const SUPPORTED_STATUSES = [
        'submitted'         => 0,  // Episciences_Paper::STATUS_SUBMITTED
        'ok-for-reviewing'  => 1,  // Episciences_Paper::STATUS_OK_FOR_REVIEWING
        'being-reviewed'    => 2,  // Episciences_Paper::STATUS_BEING_REVIEWED
        'reviewed'          => 3,  // Episciences_Paper::STATUS_REVIEWED
        'reviewers-invited' => 14, // Episciences_Paper::STATUS_REVIEWERS_INVITED
        'accepted'          => 4,  // Episciences_Paper::STATUS_ACCEPTED
        'published'         => 16, // Episciences_Paper::STATUS_PUBLISHED
    ];

    private const DEFAULT_TIMEOUT = 360;

    protected function configure(): void
    {
        $this
            ->setDescription('Pre-extract bibliographic references for papers via the Biblioref API (designed for cron)')
            ->addOption('docid',             null, InputOption::VALUE_REQUIRED, 'Process only this DOCID (ignores status filter)')
            ->addOption('rvcode',            null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)')
            ->addOption('dry-run',           null, InputOption::VALUE_NONE,     'Show what would be processed without calling the API')
            ->addOption('submitted',         null, InputOption::VALUE_NONE,     'Filter on STATUS_SUBMITTED papers (0)')
            ->addOption('ok-for-reviewing',  null, InputOption::VALUE_NONE,     'Filter on STATUS_OK_FOR_REVIEWING papers (1)')
            ->addOption('being-reviewed',    null, InputOption::VALUE_NONE,     'Filter on STATUS_BEING_REVIEWED papers (2)')
            ->addOption('reviewed',          null, InputOption::VALUE_NONE,     'Filter on STATUS_REVIEWED papers (3)')
            ->addOption('reviewers-invited', null, InputOption::VALUE_NONE,     'Filter on STATUS_REVIEWERS_INVITED papers (14)')
            ->addOption('accepted',          null, InputOption::VALUE_NONE,     'Filter on STATUS_ACCEPTED papers (4)')
            ->addOption('published',         null, InputOption::VALUE_NONE,     'Filter on STATUS_PUBLISHED papers (16)')
            ->addOption('api-url',           null, InputOption::VALUE_REQUIRED, 'Override EPISCIENCES_BIBLIOREF[URL] (useful when the configured URL is unreachable from the current host, e.g. inside Docker)');
    }

    /**
     * Resolve the list of paper statuses to process based on input options.
     * When no specific status flag is given, returns all supported statuses.
     *
     * @return list<int>
     */
    public function resolveStatuses(InputInterface $input): array
    {
        $selected = [];
        foreach (self::SUPPORTED_STATUSES as $optionName => $statusCode) {
            if ((bool) $input->getOption($optionName)) {
                $selected[] = $statusCode;
            }
        }

        return !empty($selected) ? array_values(array_unique($selected)) : array_values(self::SUPPORTED_STATUSES);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Bibliographic reference extraction');

        $this->bootstrap();

        // Cast to generic array so PHPStan does not infer literal values from the local pwd.json.
        /** @var array<string, mixed> $biblioref */
        $biblioref = EPISCIENCES_BIBLIOREF;

        if (empty($biblioref['ENABLE'])) {
            $io->warning('EPISCIENCES_BIBLIOREF is not enabled — nothing to do.');
            return Command::SUCCESS;
        }

        $baseUrl   = $input->getOption('api-url') !== null
            ? (string) $input->getOption('api-url')
            : (string) ($biblioref['URL'] ?? '');
        $sslVerify = (bool) ($biblioref['SSL_VERIFY'] ?? true);
        $token     = (string) ($biblioref['TOKEN'] ?? '');

        if ($baseUrl === '') {
            $io->error('EPISCIENCES_BIBLIOREF[URL] is not configured. Use --api-url to override.');
            return Command::FAILURE;
        }

        $logger = new Logger('extractBiblioRefs');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'extractBiblioRefs_' . date('Y-m-d') . '.log',
            Level::Info
        ));
        $stdoutHandler = null;
        if (!$io->isQuiet()) {
            $stdoutHandler = new ProgressAwareStreamHandler('php://stdout', Level::Info);
            $logger->pushHandler($stdoutHandler);
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no API calls will be made.');
        }

        $rvid   = null;
        $rvcode = $input->getOption('rvcode');
        if ($rvcode !== null) {
            $review = Episciences_ReviewsManager::findByRvcode((string) $rvcode);
            if (!$review instanceof Episciences_Review) {
                $io->error("No journal found for RV code '{$rvcode}'.");
                return Command::FAILURE;
            }
            $rvid = $review->getRvid();
            $logger->info("Filtering on journal: {$rvcode} (RVID {$rvid})");
        }

        $db     = Zend_Db_Table_Abstract::getDefaultAdapter();
        $docid  = $input->getOption('docid');
        $select = $db->select()
            ->from(['p' => T_PAPERS], ['DOCID', 'STATUS', 'REPOID', 'IDENTIFIER', 'VERSION'])
            ->join(['r' => T_REVIEW], 'r.RVID = p.RVID', ['CODE', 'is_new_front_switched'])
            ->order('p.DOCID DESC');

        $statuses = $this->resolveStatuses($input);

        if ($docid !== null) {
            $select->where('p.DOCID = ?', (int) $docid);
        } else {
            $select->where('p.STATUS IN (?)', $statuses);
            $select->where('p.REPOID != 0'); // exclude temporary versions (no external repository)
        }

        if ($rvid !== null) {
            $select->where('p.RVID = ?', $rvid);
        }

        $rows  = $db->fetchAll($select);
        $total = count($rows);

        if ($total === 0) {
            $logger->info('No papers found matching the given criteria.');
            return Command::SUCCESS;
        }

        $statusDesc = $docid !== null ? 'any status' : implode(', ', array_map(
            static fn(int $s): string => (string) $s,
            $statuses
        ));
        $logger->info(sprintf('Starting extraction for %d paper(s) [statuses: %s]', $total, $statusDesc));

        $client           = new Client();
        $extracted        = 0;
        $alreadyExtracted = 0;
        $failed           = 0;
        $skipped          = 0;

        /** @var list<array{docid: int, status: string, result: string, references: string}> $summaryRows */
        $summaryRows      = [];

        $progressBar = $io->createProgressBar($total);
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        foreach ($rows as $row) {
            $paperDocId  = (int) $row['DOCID'];
            $paperStatus = (int) $row['STATUS'];
            $statusLabel = $this->getStatusLabel($paperStatus);
            $rvCode      = (string) $row['CODE'];
            $newFront    = $row['is_new_front_switched'] === 'yes';
            $isPublished = $paperStatus === Episciences_Paper::STATUS_PUBLISHED;

            if ((int) $row['REPOID'] === 0) {
                $logger->info(sprintf('DOCID %d — temporary version, skipped', $paperDocId));
                $skipped++;
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'skipped (temporary)',
                    'references' => '-',
                ];
                $progressBar->advance();
                continue;
            }

            if ($isPublished) {
                $path       = $newFront ? '/articles/' . $paperDocId : '/' . $paperDocId;
                $articleUrl = SERVER_PROTOCOL . '://' . $rvCode . '.' . DOMAIN . $path;
            } else {
                $articleUrl = Episciences_Repositories::getPaperUrl(
                    (int) $row['REPOID'],
                    (string) $row['IDENTIFIER'],
                    $row['VERSION'] !== null ? (float) $row['VERSION'] : null
                );
            }

            if ($articleUrl === '') {
                $paper      = Episciences_PapersManager::get($paperDocId);
                $articleUrl = ($paper instanceof Episciences_Paper) ? ((string) ($paper->getMainPaperUrl() ?? '')) : '';
            }

            if ($articleUrl === '') {
                $articleUrl = (string) (Episciences_Repositories::getDocUrl(
                    (int) $row['REPOID'],
                    (string) $row['IDENTIFIER'],
                    $row['VERSION'] !== null ? (float) $row['VERSION'] : null
                ) ?? '');
            }

            if ($articleUrl === '') {
                $logger->warning(sprintf('DOCID %d — could not determine article URL (REPOID %d), skipping', $paperDocId, (int) $row['REPOID']));
                $failed++;
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'failed (no url)',
                    'references' => '-',
                ];
                $progressBar->advance();
                continue;
            }

            if ($dryRun) {
                $source = $isPublished ? 'journal' : 'archive';
                $logger->info(sprintf('[dry-run] Would call /api/extract for DOCID %d [%s] — %s', $paperDocId, $source, $articleUrl));
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'dry-run',
                    'references' => '-',
                ];
                $progressBar->advance();
                continue;
            }

            $logger->info(sprintf('DOCID %d — article URL: %s', $paperDocId, $articleUrl));
            $apiUrl = $baseUrl . '/api/extract?docid=' . $paperDocId . '&url=' . rawurlencode($articleUrl);
            if ($io->isVerbose()) {
                $io->writeln(sprintf('GET %s', $apiUrl));
            }

            $result = $this->callExtractApi($client, $apiUrl, $token, $sslVerify, $articleUrl, self::DEFAULT_TIMEOUT, $logger, $paperDocId);

            if ($result === null) {
                $failed++;
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'failed',
                    'references' => '-',
                ];
            } elseif ($result['alreadyExtracted'] ?? false) {
                $alreadyExtracted++;
                $refCount = isset($result['referenceCount']) ? (int) $result['referenceCount'] : null;
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'already extracted',
                    'references' => $refCount !== null ? (string) $refCount : '-',
                ];
            } else {
                $extracted++;
                $refCount = isset($result['referenceCount']) ? (int) $result['referenceCount'] : null;
                $summaryRows[] = [
                    'docid'      => $paperDocId,
                    'status'     => $statusLabel,
                    'result'     => 'extracted',
                    'references' => $refCount !== null ? (string) $refCount : '-',
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();

        if (!empty($summaryRows)) {
            $io->section('Extraction results per paper');
            $tableRows = array_map(static fn(array $r): array => [
                (string) $r['docid'],
                $r['status'],
                $r['result'],
                $r['references'],
            ], $summaryRows);
            $io->table(['DOCID', 'Status', 'Result', 'References'], $tableRows);
        }

        if (!$dryRun) {
            $logger->info(sprintf(
                'Extraction complete — extracted: %d, already done: %d, skipped: %d, failed: %d (total: %d)',
                $extracted,
                $alreadyExtracted,
                $skipped,
                $failed,
                $total
            ));
        }

        $io->success('Bibliographic reference extraction completed.');

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getStatusLabel(int $status): string
    {
        return match ($status) {
            Episciences_Paper::STATUS_SUBMITTED         => 'submitted (0)',
            Episciences_Paper::STATUS_OK_FOR_REVIEWING  => 'ok-for-reviewing (1)',
            Episciences_Paper::STATUS_BEING_REVIEWED    => 'being-reviewed (2)',
            Episciences_Paper::STATUS_REVIEWED          => 'reviewed (3)',
            Episciences_Paper::STATUS_REVIEWERS_INVITED => 'reviewers-invited (14)',
            Episciences_Paper::STATUS_ACCEPTED          => 'accepted (4)',
            Episciences_Paper::STATUS_PUBLISHED         => 'published (16)',
            default                                     => (string) $status,
        };
    }

    /**
     * Call the /api/extract endpoint for a single article URL.
     *
     * Returns the decoded response array on success, null on any failure.
     *
     * @return array<string, mixed>|null
     */
    private function callExtractApi(
        Client $client,
        string $apiUrl,
        string $token,
        bool $sslVerify,
        string $articleUrl,
        int $timeout,
        LoggerInterface $logger,
        ?int $paperDocId = null
    ): ?array {
        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $body = $client->get($apiUrl, [
                'headers' => $headers,
                'verify'  => $sslVerify,
                'timeout' => $timeout,
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $logger->error(sprintf(
                'HTTP error for %s (code %s): %s',
                $articleUrl,
                $e->getCode(),
                $e->getMessage()
            ));
            return null;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $logger->error(sprintf(
                'JSON decode error for %s: %s — raw response: %s',
                $articleUrl,
                $e->getMessage(),
                substr($body, 0, 500)
            ));
            return null;
        }

        if (!is_array($data)) {
            $logger->warning(sprintf('Unexpected API response format for %s', $articleUrl));
            return null;
        }

        if (!($data['success'] ?? false)) {
            $logger->error(sprintf(
                'Extraction failed for %s: %s',
                $articleUrl,
                (string) ($data['error'] ?? 'unknown error')
            ));
            return null;
        }

        $docId            = (int) ($data['docId'] ?? $paperDocId ?? 0);
        $alreadyExtracted = (bool) ($data['alreadyExtracted'] ?? false);
        $refCount         = isset($data['referenceCount']) ? (int) $data['referenceCount'] : null;

        $logger->info(sprintf(
            'DOCID %d — %s%s',
            $docId,
            $alreadyExtracted ? 'already extracted' : 'extracted',
            $refCount !== null ? sprintf(' (%d references)', $refCount) : ''
        ));

        return $data;
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
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
    }
}
