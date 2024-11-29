<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;

require_once __DIR__ . '/loadHeader.php';
require_once "JournalScript.php";

class GetClassificationJel extends JournalScript
{
    public const CACHE_OPENAIRE_API = CACHE_PATH_METADATA . 'openAireResearchGraph';
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
        $fileHandler = new StreamHandler(EPISCIENCES_LOG_PATH . 'getJel.log', Logger::DEBUG);
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
            //  ->where('RVID = ?', 3)
            // ->limit(10)
            ->order('DOCID ASC');

        $papers = $db->fetchAll($select);
        $this->logger->info('Found ' . count($papers) . ' papers to process');

        foreach ($papers as $value) {
            try {
                $externalId = $value['DOI'];
                $this->logger->info("Processing DOI: {$externalId}");

                $jelCodes = $this->fetchAndProcessApiData($externalId);

                if (!empty($jelCodes)) {
                    $this->logger->info("JEL Codes found for DOI {$externalId}:", $jelCodes);
                } else {
                    $this->logger->warning("No JEL Codes found for DOI {$externalId}");
                }

                $docId = $value['DOCID'];
                $collectionOfClassifications = $this->createClassifications($jelCodes, $docId);

                if (!empty($collectionOfClassifications)) {
                    $insert = Episciences_Paper_ClassificationsManager::insert($collectionOfClassifications);
                    $this->logger->info("{$insert} JEL classifications inserted/updated for DOI: {$externalId} - DOCID: {$docId}");
                }
            } catch (Exception $e) {
                $this->logger->error("Error processing DOI {$externalId}: " . $e->getMessage());
            }
        }

        $this->logger->info('JEL Classification Data Enrichment completed successfully');
    }

    public function fetchAndProcessApiData(string $doi): array
    {
        $cache = new FilesystemAdapter('', 0, self::CACHE_OPENAIRE_API);
        $cacheKey = 'api_result_' . md5($doi);

        /** @var CacheItem $cachedApiResult */
        $cachedApiResult = $cache->getItem($cacheKey);

        if (!$cachedApiResult->isHit()) {
            $this->logger->info("No cache found for DOI: {$doi}, calling OpenAIRE API");
            $client = new Client();
            $url = 'https://api.openaire.eu/search/publications/?format=json&doi=' . urlencode($doi);

            try {
                $response = $client->get($url);
                $jsonData = json_decode($response->getBody(), true);
                $cachedApiResult->set($jsonData)->expiresAfter(self::ONE_MONTH); // Cache for one month
                $cache->save($cachedApiResult);
            } catch (GuzzleException $e) {
                $this->logger->error('Error fetching API data: ' . $e->getMessage());
                return [];
            }
        } else {
            $this->logger->info("Using cached result for DOI: {$doi}");
        }

        $jsonData = $cachedApiResult->get();
        return $this->processApiData($jsonData);
    }

    public function processApiData(array $jsonData): array
    {
        $results = [];

        if (isset($jsonData['response']['results']['result'])) {
            $apiResults = $jsonData['response']['results']['result'];

            foreach ($apiResults as $result) {
                if (isset($result['metadata']['oaf:entity']['oaf:result']['subject'])) {
                    $subjects = $result['metadata']['oaf:entity']['oaf:result']['subject'];

                    if (!is_array($subjects)) {
                        $subjects = [$subjects];
                    }

                    foreach ($subjects as $subject) {
                        if (isset($subject['@classid']) && $subject['@classid'] === 'jel' && isset($subject['$'])) {
                            $value = $subject['$'];
                            if (str_starts_with($value, 'jel:')) {
                                $processedValue = ltrim($value, 'jel:');
                                if ($processedValue !== '') {
                                    $results[] = $processedValue;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_unique($results);
    }

    private function createClassifications(array $jelCodes, int $docId): array
    {
        $collectionOfClassifications = [];

        foreach ($jelCodes as $jelCode) {

            $classification = new Episciences_Paper_Classifications();
            $classification->setClassificationName(Episciences\Classification\jel::$classificationName);

            try {
                $classification->checkClassificationCode($jelCode, $this->getAllClassificationCodes());

            } catch (Zend_Exception $e) {
                $this->logger->warning($e->getMessage());
                $this->logger->warning(sprintf('[%s] classification ignored !', $jelCode));
                continue;
            }

            $classification->setClassificationCode($jelCode);
            $classification->setDocid($docId);
            $classification->setSourceId(Episciences_Repositories::GRAPH_OPENAIRE_ID);
            $collectionOfClassifications[] = $classification;
        }
        $this->logger->info("Found " . count($collectionOfClassifications) . " JEL classifications for DOCID {$docId}");
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
        $sql = $this->getDb()?->select()->from(T_PAPER_CLASSIFICATION_JEL, ['code']);
        $this->allClassificationCodes = $this->getDb()?->fetchCol($sql);
    }

    public function getAllClassificationCodes(): array
    {
        return $this->allClassificationCodes;
    }
}

$script = new GetClassificationJel([]);
$script->run();
