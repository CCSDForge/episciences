<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: discover zbMATH Open reviews for published papers.
 *
 * Replaces: scripts/getZbReviews.php (JournalScript)
 */
class GetZbReviewsCommand extends Command
{
    protected static $defaultName = 'enrichment:zb-reviews';
    private const ONE_MONTH = 3600 * 24 * 31;
    private const ZB_MATH_BASE_URL = 'https://zbmath.org/';

    protected function configure(): void
    {
        $this
            ->setDescription('Discover and store zbMATH Open reviews for published papers')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $dryRun  = (bool) $input->getOption('dry-run');
        $rvcode  = $input->getOption('rvcode');
        $io->title('zbMATH Open reviews discovery');
        $this->bootstrap();

        $logger = new Logger('zbReviews');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'zbReviews_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
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

        $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
        // Shares the zbmathApiDocument cache with enrichment:classifications-msc
        $cache = new FilesystemAdapter('zbmathApiDocument', self::ONE_MONTH, $cacheDir);

        $db     = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOI', 'DOCID', 'PAPERID'])
            ->where('DOI != ""')
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->order('DOCID ASC');
        if ($rvid !== null) {
            $select->where('RVID = ?', $rvid);
        }
        $papers = $db->fetchAll($select);

        $logger->info('Starting zbMATH reviews discovery for ' . count($papers) . ' papers');
        $io->progressStart(count($papers));

        foreach ($papers as $row) {
            $doi   = $row['DOI'];
            $docId = (int) $row['DOCID'];
            try {
                $apiResponse = $this->queryZbMath($doi, $cache, $logger, $output);
                $reviews     = $this->extractZbMathReviews($apiResponse);

                if (!empty($reviews)) {
                    $logger->info("zbMATH reviews found for DOI {$doi}: " . count($reviews));
                } else {
                    $logger->info("No zbMATH reviews found for DOI {$doi}");
                }

                if (!$dryRun) {
                    foreach ($reviews as $review) {
                        [$relationship, $typeLd, $valueLd, $inputTypeLd, $linkMetaText] =
                            $this->buildLinkedReview($review);
                        $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$linkMetaText]);
                        if (Episciences_Paper_DatasetsManager::addDatasetFromSubmission(
                            $docId, $typeLd, $valueLd, $inputTypeLd, $idMetaDataLastId,
                            ['relationship' => $relationship, 'sourceId' => Episciences_Repositories::ZBMATH_OPEN]
                        ) > 0) {
                            Episciences_PapersManager::updateJsonDocumentData($docId);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $logger->error("zbMATH review error for DOI {$doi}: " . $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('zbMATH Open reviews discovery completed.');
        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryZbMath(string $doi, FilesystemAdapter $cache, Logger $logger, OutputInterface $output): array
    {
        $key  = 'zbmath_api_' . md5($doi);
        $item = $cache->getItem($key);
        if ($item->isHit()) {
            $logger->info("zbMath data from cache for DOI {$doi}");
            return $item->get();
        }

        $url = 'https://api.zbmath.org/v1/document/_structured_search?page=0&results_per_page=1&DOI=' . rawurlencode($doi);
        $output->writeln("zbMath API call: {$url}", OutputInterface::VERBOSITY_VERY_VERBOSE);
        try {
            $body = (new Client(['headers' => ['User-Agent' => 'Episciences', 'Accept' => 'application/json']]))
                        ->get($url)->getBody()->getContents();
            sleep(1);
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (($decoded['status']['status_code'] ?? null) === 404) {
                $logger->info("No results found for DOI {$doi}");
                $decoded = ['result' => []];
            }
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                $logger->info("No results found for DOI {$doi} (404 response)");
                $decoded = ['result' => []];
            } else {
                $logger->error('zbMath API error for DOI ' . $doi . ': ' . $e->getMessage());
                return ['result' => []]; // not cached — will retry next run
            }
        }

        $item->set($decoded);
        $item->expiresAfter(self::ONE_MONTH);
        $cache->save($item);
        return $decoded;
    }

    /**
     * Extract review entries from a zbMATH API response.
     *
     * @param array<string, mixed> $apiResponse
     * @return array<int, array<string, mixed>>
     */
    private function extractZbMathReviews(array $apiResponse): array
    {
        $reviews = [];
        foreach ($apiResponse['result'] ?? [] as $result) {
            if (!isset($result['editorial_contributions']) || !is_array($result['editorial_contributions'])) {
                continue;
            }
            foreach ($result['editorial_contributions'] as $contribution) {
                if (!isset($contribution['contribution_type'], $contribution['reviewer'])
                    || $contribution['contribution_type'] !== 'review') {
                    continue;
                }
                $reviews[] = [
                    'zbmathid' => htmlspecialchars((string) $result['id']),
                    'language' => htmlspecialchars((string) $contribution['language']),
                    'reviewer' => $contribution['reviewer'],
                ];
            }
        }
        return $reviews;
    }

    /**
     * Build dataset metadata for a single zbMATH review entry.
     *
     * @param array<string, mixed> $review
     * @return array{0: string, 1: string, 2: string, 3: string, 4: Episciences_Paper_DatasetMetadata}
     */
    private function buildLinkedReview(array $review): array
    {
        $relationship = 'hasReview';
        $typeLd       = 'zbmath';
        $valueLd      = self::ZB_MATH_BASE_URL . $review['zbmathid'];
        $inputTypeLd  = 'publication';

        $reviewerSignature = htmlspecialchars((string) $review['reviewer']['sign']);
        $reviewerId        = htmlspecialchars((string) $review['reviewer']['reviewer_id']);
        $reviewerUrl       = self::ZB_MATH_BASE_URL . 'authors/?q=rv:' . $reviewerId;
        $reviewerWithLink  = "<a target=\"_blank\" rel=\"noopener\" href=\"{$reviewerUrl}\">{$reviewerSignature}</a>";
        $citationFull      = "<a target=\"_blank\" rel=\"noopener\" href=\"{$valueLd}\">Review available on zbMATH Open</a>"
                           . ", by {$reviewerWithLink}&nbsp;({$review['language']})";

        $linkMetaText = new Episciences_Paper_DatasetMetadata();
        $linkMetaText->setMetatext(json_encode(['citationFull' => $citationFull], JSON_THROW_ON_ERROR));

        return [$relationship, $typeLd, $valueLd, $inputTypeLd, $linkMetaText];
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
