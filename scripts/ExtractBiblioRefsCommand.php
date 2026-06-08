<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
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

    private const DEFAULT_TIMEOUT = 360;

    protected function configure(): void
    {
        $this
            ->setDescription('Pre-extract bibliographic references for papers via the Biblioref API (designed for cron)')
            ->addOption('docid',     null, InputOption::VALUE_REQUIRED, 'Process only this DOCID (ignores status filter)')
            ->addOption('rvcode',    null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)')
            ->addOption('dry-run',   null, InputOption::VALUE_NONE,     'Show what would be processed without calling the API')
            ->addOption('published', null, InputOption::VALUE_NONE,     'Also include STATUS_PUBLISHED papers (default: STATUS_SUBMITTED only)')
            ->addOption('accepted',  null, InputOption::VALUE_NONE,     'Also include STATUS_ACCEPTED papers (default: STATUS_SUBMITTED only)')
            ->addOption('api-url',   null, InputOption::VALUE_REQUIRED,  'Override EPISCIENCES_BIBLIOREF[URL] (useful when the configured URL is unreachable from the current host, e.g. inside Docker)');
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
            Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
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

        $statuses = [Episciences_Paper::STATUS_SUBMITTED];
        if ((bool) $input->getOption('published')) {
            $statuses[] = Episciences_Paper::STATUS_PUBLISHED;
        }
        if ((bool) $input->getOption('accepted')) {
            $statuses[] = Episciences_Paper::STATUS_ACCEPTED;
        }

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

        $io->progressStart($total);

        foreach ($rows as $row) {
            $paperDocId = (int) $row['DOCID'];
            $rvCode     = (string) $row['CODE'];
            $newFront   = $row['is_new_front_switched'] === 'yes';
            $isPublished = (int) $row['STATUS'] === Episciences_Paper::STATUS_PUBLISHED;

            if ((int) $row['REPOID'] === 0) {
                $logger->info(sprintf('DOCID %d — temporary version, skipped', $paperDocId));
                $skipped++;
                $io->progressAdvance();
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
                $io->progressAdvance();
                continue;
            }

            if ($dryRun) {
                $source = $isPublished ? 'journal' : 'archive';
                $logger->info(sprintf('[dry-run] Would call /api/extract for DOCID %d [%s] — %s', $paperDocId, $source, $articleUrl));
                $io->progressAdvance();
                continue;
            }

            $logger->info(sprintf('DOCID %d — article URL: %s', $paperDocId, $articleUrl));
            $apiUrl = $baseUrl . '/api/extract?docid=' . $paperDocId . '&url=' . rawurlencode($articleUrl);
            if ($io->isVerbose()) {
                $io->writeln(sprintf('GET %s', $apiUrl));
            }

            $result = $this->callExtractApi($client, $apiUrl, $token, $sslVerify, $articleUrl, self::DEFAULT_TIMEOUT, $logger);

            if ($result === null) {
                $failed++;
            } elseif ($result['alreadyExtracted'] ?? false) {
                $alreadyExtracted++;
            } else {
                $extracted++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

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
        LoggerInterface $logger
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

        $alreadyExtracted = (bool) ($data['alreadyExtracted'] ?? false);
        $refCount         = isset($data['referenceCount']) ? (int) $data['referenceCount'] : null;

        $logger->info(sprintf(
            'DOCID %d — %s%s',
            (int) ($data['docId'] ?? 0),
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
