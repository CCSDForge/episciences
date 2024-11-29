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

class GetClassificationMsc extends JournalScript
{
    public const CACHE_ZBMATH_API_DOCUMENT = CACHE_PATH_METADATA . 'zbmathApiDocument';
    private const ONE_MONTH = 3600 * 24 * 31;
    private bool $_dryRun = false;
    private Logger $logger;

    private array $allClassificationCodes = [];

    public function __construct(array $localopts)
    {
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        // Initialize Monolog
        $this->logger = new Logger(basename(__FILE__));

        // File handler
        $fileHandler = new StreamHandler(EPISCIENCES_LOG_PATH . 'getMsc2020.log', Logger::DEBUG);
        $fileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $this->logger->pushHandler($fileHandler);

        // Console handler
        $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
        $consoleHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", null, false, true));
        $this->logger->pushHandler($consoleHandler);
    }

    public function run(): void
    {
        $this->logger->info('Starting Classification Data Enrichment process');
        $this->initApp();
        $this->initDb();
        defineJournalConstants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->setAllClassificationCodes();

        $this->logger->info('Fetching papers from database');
        $select = $db
            ->select()
            ->from(T_PAPERS, ["DOI", "DOCID"])
            ->where('DOI != ""')
            ->where("STATUS = ?", Episciences_Paper::STATUS_PUBLISHED)
            //->where('RVID = ?', 3)
            ->order('DOCID ASC');

        $papers = $db->fetchAll($select);
        $this->logger->info('Found ' . count($papers) . ' papers to process');

        foreach ($papers as $value) {
            try {
                $externalId = $value['DOI'];
                $this->logger->info("Processing DOI: {$externalId}");

                $apiResponse = $this->queryZbMathAPI($externalId);
                $mscCodes = $this->extractMSC2020Codes($apiResponse);

                if (!empty($mscCodes)) {
                    $this->logger->info("MSC 2020 Codes found for DOI {$externalId}:", $mscCodes);
                } else {
                    $this->logger->warning("No MSC 2020 Codes found for DOI {$externalId}");
                }

                $docId = $value['DOCID'];
                $collectionOfClassifications = $this->createClassifications($mscCodes, $docId);

                if (!empty($collectionOfClassifications)) {
                    $insert = Episciences_Paper_ClassificationsManager::insert($collectionOfClassifications);
                    $this->logger->info("{$insert} classifications inserted/updated for DOI: {$externalId} - DOCID: {$docId}");
                }
            } catch (Exception $e) {
                $this->logger->error("Error processing DOI {$externalId}: " . $e->getMessage());
            }
        }

        $this->logger->info('MSC 2020 Classification Data Enrichment completed successfully');
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

    private function extractMSC2020Codes(array $apiResponse): array
    {
        if (!isset($apiResponse['result']) || !is_array($apiResponse['result']) || empty($apiResponse['result'])) {
            $this->logger->warning('No results found in the API response');
            return [];
        }

        $mscCodes = [];

        foreach ($apiResponse['result'] as $result) {
            if (isset($result['msc']) && is_array($result['msc'])) {
                foreach ($result['msc'] as $mscItem) {
                    if (isset($mscItem['scheme'], $mscItem['code']) && $mscItem['scheme'] === 'msc2020') {
                        $mscCodes[] = $mscItem['code'];
                    }
                }
            }
        }

        return $mscCodes;
    }

    private function createClassifications(array $mscCodes, int $docId): array
    {

        $collectionOfClassifications = [];

        foreach ($mscCodes as $mscCode) {

            $classification = new Episciences_Paper_Classifications();
            $classification->setClassificationName(Episciences\Classification\msc2020::$classificationName);

            try {
                $classification->checkClassificationCode($mscCode, $this->getAllClassificationCodes());

            } catch (Zend_Exception $e) {
                $this->logger->warning($e->getMessage());
                $this->logger->warning(sprintf('[%s] classification ignored !', $mscCode));
                continue;
            }

            $classification->setClassificationCode($mscCode);
            $classification->setDocid($docId);
            $classification->setSourceId(Episciences_Repositories::ZBMATH_OPEN);
            $collectionOfClassifications[] = $classification;
        }
        $this->logger->info("Found " . count($collectionOfClassifications) . " classifications for DOCID {$docId}");
        return $collectionOfClassifications;
    }

    public function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->_dryRun = $dryRun;
    }

    private function setAllClassificationCodes(): void
    {
        $sql = $this->getDb()?->select()->from(T_PAPER_CLASSIFICATION_MSC2020, ['code']);
        $this->allClassificationCodes = $this->getDb()?->fetchCol($sql);
    }

    public function getAllClassificationCodes(): array
    {
        return $this->allClassificationCodes;
    }
}

$script = new GetClassificationMsc([]);
$script->run();