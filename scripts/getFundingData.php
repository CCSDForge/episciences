<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


$localopts = [
    'doi=s' => 'Process a single paper by DOI',
    'paperid=i' => 'Process a single paper by Paper ID',
    'clear-cache' => 'Clear OpenAIRE and enrichment caches',
    'no-cache' => 'Bypass cache and fetch fresh data from OpenAIRE',
    'dry-run' => 'Work with Test API',
];


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getFundingData extends JournalScript
{

    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * @var bool
     */
    protected bool $_noCache = false;

    /**
     * getFundingData constructor.
     * @param $localopts
     */
    public function __construct($localopts)
    {
        // missing required parameters will be asked later
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        $this->setDryRun((bool)$this->getParam('dry-run'));
        $this->setNoCache((bool)$this->getParam('no-cache'));

        // Initialize Monolog logger
        $this->logger = new Logger('fundingEnrichment');
        $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'fundingEnrichment_' . date('Y-m-d') . '.log', Logger::DEBUG));

        // Add console output handler if verbose mode is enabled
        if ($this->isVerbose()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
    }

    /**
     * Main execution method for funding data enrichment
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        // Handle cache clearing option
        if ($this->getParam('clear-cache')) {
            $this->initApp(false);
            $this->clearCache();
            return;
        }

        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        defineJournalConstants();

        // Handle single paper processing by DOI
        if ($this->getParam('doi')) {
            $doi = $this->getParam('doi');
            $this->processSingleDoi($doi);
            return;
        }

        // Handle single paper processing by Paper ID
        if ($this->getParam('paperid')) {
            $paperId = (int)$this->getParam('paperid');
            $this->processSinglePaper($paperId);
            return;
        }

        // Bulk processing of all papers
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID', 'STATUS'])
            ->order('REPOID DESC');

        $this->logger->info('Starting funding enrichment process');

        foreach ($db->fetchAll($select) as $value) {
            $this->processPaper($value);
        }

        $this->logger->info('Funding Data Enrichment completed. Good Bye ! =)');
    }

    /**
     * Clear OpenAIRE Research Graph and enrichment funding caches
     *
     * @return void
     */
    private function clearCache(): void
    {
        $this->logger->info('Starting cache clearing operation');

        try {
            $cacheDir = dirname(APPLICATION_PATH) . '/cache/';

            // Clear OpenAIRE Research Graph cache
            $cacheOARG = new FilesystemAdapter('openAireResearchGraph', 0, $cacheDir);
            $cacheOARG->clear();
            $this->logger->info('Cleared openAireResearchGraph cache');

            // Clear enrichment funding cache
            $cacheEnrichment = new FilesystemAdapter('enrichmentFunding', 0, $cacheDir);
            $cacheEnrichment->clear();
            $this->logger->info('Cleared enrichmentFunding cache');

            $this->logger->info('Cache clearing completed successfully');
        } catch (Exception $e) {
            $this->logger->error('Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Process a single paper by its DOI
     *
     * @param string $doi DOI identifier
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function processSingleDoi(string $doi): void
    {
        $this->logger->info("Processing single paper by DOI: {$doi}");

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID', 'STATUS'])
            ->where('DOI = ?', trim($doi));

        $result = $db->fetchRow($select);

        if (!$result) {
            $this->logger->error("Paper not found with DOI: {$doi}");
            return;
        }

        $this->processPaper($result);
        $this->logger->info("Processing completed for DOI: {$doi}");
    }

    /**
     * Process a single paper by its Paper ID
     *
     * @param int $paperId Paper ID
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function processSinglePaper(int $paperId): void
    {
        $this->logger->info("Processing single paper by ID: {$paperId}");

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID', 'STATUS'])
            ->where('PAPERID = ?', $paperId);

        $result = $db->fetchRow($select);

        if (!$result) {
            $this->logger->error("Paper not found with ID: {$paperId}");
            return;
        }

        $this->processPaper($result);
        $this->logger->info("Processing completed for paper ID: {$paperId}");
    }

    /**
     * Process a single paper's funding data
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function processPaper(array $paperData): void
    {
        $paperId = $paperData['PAPERID'];
        $status = $paperData['STATUS'];

        // Process OpenAIRE funding if paper has DOI and is published
        if (isset($paperData['DOI']) && $paperData['DOI'] !== '' && $status === (string)Episciences_Paper::STATUS_PUBLISHED) {
            $this->processOpenAireFunding($paperData);
        }

        // Process HAL funding if paper is from HAL repository
        if (($paperData['REPOID'] === Episciences_Repositories::HAL_REPO_ID) && !is_null(trim($paperData['IDENTIFIER']))) {
            $this->processHalFunding($paperData);
        }
    }

    /**
     * Process OpenAIRE funding data for a paper
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function processOpenAireFunding(array $paperData): void
    {
        $doiTrim = trim($paperData['DOI']);
        $paperId = $paperData['PAPERID'];

        $this->logger->info("Processing OpenAIRE funding for DOI: {$doiTrim}");

        // Check if global OpenAIRE Research Graph exists
        Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);

        // If no-cache mode is enabled, delete existing cache for this DOI
        if ($this->isNoCache()) {
            $this->deleteCacheForDoi($doiTrim);
        }

        // Use MD5 hash to avoid special characters in cache key
        $fileOpenAireGlobalResponse = md5($doiTrim) . ".json";
        $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $setsGlobalOARG = $cacheOARG->getItem($fileOpenAireGlobalResponse);

        // Cache system only for fundings
        list($cache, $pathOpenAireFunding, $setOAFunding) = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);

        if ($setsGlobalOARG->isHit() && !$setOAFunding->isHit()) {
            $this->logger->info("Processing funding cache: {$pathOpenAireFunding}");

            try {
                $decodeOpenAireResp = json_decode(
                    $setsGlobalOARG->get(),
                    true,
                    512,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
                Episciences_OpenAireResearchGraphTools::putFundingsInCache($decodeOpenAireResp, $doiTrim);
                $this->logger->info("Created funding cache from Global openAireResearchGraph for DOI: {$doiTrim}");
            } catch (JsonException $e) {
                // OpenAIRE can return malformed JSON
                $errorMsg = sprintf(
                    "JSON error: %s - URL: https://api.openaire.eu/search/publications/?doi=%s&format=json",
                    $e->getMessage(),
                    $doiTrim
                );
                $this->logger->error($errorMsg);
                $setOAFunding->set(json_encode([""]));
                $cache->save($setOAFunding);
                return;
            }
            sleep(1);
        }

        // Refresh cache to get the new file
        list($cache, $pathOpenAireFunding, $setOAFunding) = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);

        try {
            $fileFound = json_decode(
                $setOAFunding->get(),
                true,
                512,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $jsonException) {
            $errorMsg = sprintf('Error Code %s / Error Message %s', $jsonException->getCode(), $jsonException->getMessage());
            $this->logger->error($errorMsg);
            return;
        }

        $this->logger->debug("Retrieved OpenAIRE funding cache for DOI: {$doiTrim}");

        if (!empty($fileFound[0])) {
            $fundingArray = [];
            $globalfundingArray = [];
            $globalfundingArray = Episciences_Paper_ProjectsManager::formatFundingOAForDB($fileFound, $fundingArray, $globalfundingArray);
            $rowInDBGraph = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, Episciences_Repositories::GRAPH_OPENAIRE_ID);
            Episciences_Paper_ProjectsManager::insertOrUpdateFundingOA($globalfundingArray, $rowInDBGraph, (int)$paperId);
        }
    }

    /**
     * Process HAL funding data for a paper
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws JsonException
     */
    private function processHalFunding(array $paperData): void
    {
        $trimIdentifier = trim($paperData['IDENTIFIER']);
        $paperId = $paperData['PAPERID'];
        $version = $paperData['VERSION'];

        $this->logger->info("Processing HAL funding for identifier: {$trimIdentifier}");

        $arrayIdEuAnr = Episciences_Paper_ProjectsManager::CallHAlApiForIdEuAndAnrFunding($trimIdentifier, $version);
        $decodeHalIdsResp = json_decode($arrayIdEuAnr, true, 512, JSON_THROW_ON_ERROR);

        $globalArrayJson = [];
        if (!empty($decodeHalIdsResp['response']['docs'])) {
            $globalArrayJson = Episciences_Paper_ProjectsManager::FormatFundingANREuToArray(
                $decodeHalIdsResp['response']['docs'],
                $trimIdentifier,
                $globalArrayJson
            );
        }

        $mergeArrayANREU = [];
        if (!empty($globalArrayJson)) {
            foreach ($globalArrayJson as $globalPreJson) {
                $mergeArrayANREU[] = $globalPreJson[0];
            }

            $rowInDbHal = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, Episciences_Repositories::HAL_REPO_ID);
            Episciences_Paper_ProjectsManager::insertOrUpdateHalFunding($rowInDbHal, $mergeArrayANREU, $paperId);

            if (empty($mergeArrayANREU)) {
                $this->logger->info("No HAL funding info found for identifier: {$trimIdentifier}");
            }
        }
    }

    /**
     * Delete cache entries for a specific DOI
     *
     * @param string $doi The DOI to clear cache for
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function deleteCacheForDoi(string $doi): void
    {
        try {
            $cacheDir = dirname(APPLICATION_PATH) . '/cache/';

            // Delete OpenAIRE Research Graph cache for this DOI
            // Use MD5 hash to avoid special characters in cache key
            $fileOpenAireGlobalResponse = md5($doi) . ".json";
            $cacheOARG = new FilesystemAdapter('openAireResearchGraph', 0, $cacheDir);
            $cacheOARG->deleteItem($fileOpenAireGlobalResponse);

            // Delete enrichment funding cache for this DOI
            $pathOpenAireFunding = md5($doi) . "_funding.json";
            $cacheEnrichment = new FilesystemAdapter('enrichmentFunding', 0, $cacheDir);
            $cacheEnrichment->deleteItem($pathOpenAireFunding);

            $this->logger->info("Deleted cache entries for DOI: {$doi}");
        } catch (Exception $e) {
            $this->logger->error("Failed to delete cache for DOI {$doi}: " . $e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->_dryRun = $dryRun;
    }

    /**
     * Check if script should bypass cache
     *
     * @return bool
     */
    public function isNoCache(): bool
    {
        return $this->_noCache;
    }

    /**
     * Set no-cache mode
     *
     * @param bool $noCache
     * @return void
     */
    public function setNoCache(bool $noCache): void
    {
        $this->_noCache = $noCache;
    }
}

// Only run the script if executed directly (not when included for testing)
if (!defined('PHPUNIT_RUNNING') && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $script = new getFundingData($localopts);
    $script->run();
}
