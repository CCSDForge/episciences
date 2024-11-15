<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

require_once __DIR__ . '/loadHeader.php';
require_once "JournalScript.php";

class GetZbReviews extends JournalScript
{
    public const CACHE_ZBMATH_API_DOCUMENT = CACHE_PATH_METADATA . 'zbmathApiDocument';
    private const ONE_MONTH = 3600 * 24 * 31;
    const HTTPS_ZBMATH_ORG_BASE_URL = 'https://zbmath.org/';
    private bool $_dryRun = false;
    private Logger $logger;

    public function __construct(array $localopts)
    {
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        // Initialize Monolog
        $this->logger = new Logger(basename(__FILE__));

        // File handler
        $fileHandler = new StreamHandler(EPISCIENCES_LOG_PATH . 'getzbMATH-reviews.log', Logger::DEBUG);
        $fileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $this->logger->pushHandler($fileHandler);

        // Console handler
        $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
        $consoleHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", null, false, true));
        $this->logger->pushHandler($consoleHandler);
    }

    public function run(): void
    {
        $this->logger->info('Starting zbMATH Open Reviews discovery');
        $this->initApp();
        $this->initDb();
        defineJournalConstants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->logger->info('Fetching papers from database');
        $select = $db
            ->select()
            ->from(T_PAPERS, ["DOI", "DOCID", 'PAPERID'])
            ->where('DOI != ""')
            ->where("STATUS = ?", Episciences_Paper::STATUS_PUBLISHED)
            //->where('RVID = ?', 3)
            // ->where('DOCID = 2050')
            ->order('DOCID ASC');

        $papers = $db->fetchAll($select);
        $this->logger->info('Found ' . count($papers) . ' papers to process');

        foreach ($papers as $value) {
            try {
                $externalId = $value['DOI'];
                $this->logger->info("Processing DOI: {$externalId}");

                $apiResponse = $this->queryZbMathAPI($externalId);
                $reviews = $this->extractzbMathReviews($apiResponse);

                if (!empty($reviews)) {
                    $this->logger->info("zbMATH Reviews found for DOI {$externalId}:", $reviews);
                } else {
                    $this->logger->warning("No zbMATH Reviews found for DOI {$externalId}");
                }

                foreach ($reviews as $review) {
                    list($docId, $relationship, $typeLd, $valueLd, $inputTypeLd, $linkMetaText) = $this->prepareLinkedReview($value['DOCID'], $review);
                    $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$linkMetaText]);
                    if (Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $valueLd, $inputTypeLd, $idMetaDataLastId, ['relationship' => $relationship, 'sourceId' => Episciences_Repositories::ZBMATH_OPEN]) > 0) {
                        Episciences_PapersManager::updateJsonDocumentData($docId);
                    }
                }
            } catch (Exception $e) {
                $this->logger->error("Error processing DOI {$externalId}: " . $e->getMessage());
            }
        }

        $this->logger->info('zbMATH Open Reviews discovery completed successfully');
    }

    private function queryZbMathAPI(string $externalId): array
    {
        $cache = new FilesystemAdapter('', 0, self::CACHE_ZBMATH_API_DOCUMENT);
        $cacheKey = 'zbmath_api_' . md5($externalId);

        return $cache->get($cacheKey, function (ItemInterface $item) use ($externalId) {
            $this->logger->info("Cache miss for DOI {$externalId}. Querying ZbMath API");
            $item->expiresAfter(self::ONE_MONTH);

            $client = new Client([
                'headers' => [
                    'User-Agent' => 'Episciences',
                    'Accept' => 'application/json',
                ],
            ]);

            $url = "https://api.zbmath.org/v1/document/_structured_search?page=0&results_per_page=1&" . urlencode('external id') . "=" . urlencode($externalId);

            try {
                $response = $client->get($url);
                sleep(1);
                $body = $response->getBody()->getContents();
                $decodedBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                // Check if the response indicates a 404 status
                if (isset($decodedBody['status']['status_code']) && $decodedBody['status']['status_code'] === 404) {
                    $this->logger->info("No results found for DOI {$externalId}");
                    return ['result' => []];
                }

                return $decodedBody;
            } catch (GuzzleException $e) {
                if ($e->getCode() === 404) {
                    $this->logger->info("No results found for DOI {$externalId} (404 response)");
                    return ['result' => []];
                }
                throw new Exception('API request failed: ' . $e->getMessage());
            }
        });
    }

    private function extractzbMathReviews(array $apiResponse): array
    {
        if (!isset($apiResponse['result']) || !is_array($apiResponse['result']) || empty($apiResponse['result'])) {
            $this->logger->warning('No results found in the API response');
            return [];
        }

        $review = [];

        foreach ($apiResponse['result'] as $result) {

            if (isset($result['editorial_contributions']) && is_array($result['editorial_contributions'])) {
                $reviewIndex = 0;
                foreach ($result['editorial_contributions'] as $contribution) {
                    if (isset($contribution['contribution_type'], $contribution['reviewer']) && $contribution['contribution_type'] === 'review') {
                        $review[$reviewIndex]['zbmathid'] = htmlspecialchars($result['id']);
                        $review[$reviewIndex]['language'] = htmlspecialchars($contribution['language']);
                        $review[$reviewIndex]['reviewer'] = $contribution['reviewer'];
                        $reviewIndex++;
                    }
                }
            }
        }

        return $review;
    }

    /**
     * @param $DOCID
     * @param mixed $review
     * @return array
     */
    private function prepareLinkedReview($DOCID, array $review): array
    {
        $docId = $DOCID;
        $relationship = 'hasReview';
        $typeLd = 'zbmath';
        $valueLd = self::HTTPS_ZBMATH_ORG_BASE_URL . $review['zbmathid'];
        $inputTypeLd = 'publication';

        $linkMetaText = new Episciences_Paper_DatasetMetadata();
        $reviewerSignature = htmlspecialchars($review["reviewer"]["sign"]);
        $reviewerUrl = self::HTTPS_ZBMATH_ORG_BASE_URL . 'authors/?q=rv:' . htmlspecialchars($review["reviewer"]["reviewer_id"]);
        $reviewerSignatureWithUrl = "<a target=\"_blank\" rel=\"noopener\" href=\"{$reviewerUrl}\">{$reviewerSignature}</a>";
        $citation['citationFull'] = "<a target=\"_blank\" rel=\"noopener\" href=\"{$valueLd}\">Review available on zbMATH Open</a>, by {$reviewerSignatureWithUrl}&nbsp;({$review['language']})";
        $this->logger->info("zbMATH Citation will be {$citation['citationFull']}:");
        $this->getSetMetatext($linkMetaText, $citation);
        return array($docId, $relationship, $typeLd, $valueLd, $inputTypeLd, $linkMetaText);
    }

    /**
     * @param Episciences_Paper_DatasetMetadata $linkMetaText
     * @param array $citation
     * @return void
     */
    private function getSetMetatext(Episciences_Paper_DatasetMetadata $linkMetaText, array $citation): void
    {
        $linkMetaText->setMetatext(json_encode($citation));
    }

    public function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->_dryRun = $dryRun;
    }
}

$script = new GetZbReviews([]);
$script->run();