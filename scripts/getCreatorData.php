<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


$localopts = [
    'doi=s' => 'Process a single paper by DOI',
    'paperid=i' => 'Process a single paper by Paper ID',
    'clear-cache' => 'Clear OpenAIRE and enrichment caches',
    'no-cache' => 'Bypass cache and fetch fresh data from OpenAIRE',
    'dry-run' => 'Enable dry-run mode'
];

if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";


class GetCreatorData extends JournalScript
{
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
     * GetCreatorData constructor.
     * @param array $localopts
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
        $this->logger = new Logger('creatorEnrichment');
        $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'creatorEnrichment_' . date('Y-m-d') . '.log', Logger::DEBUG));

        // Add console output handler if verbose mode is enabled
        if ($this->isVerbose()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
    }


    /**
     * Main execution method for author data enrichment
     *
     * @return void
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
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
            ->distinct('PAPERID')
            ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
            ->where("DOI != ''")
            ->order('DOCID DESC');


        $this->logger->info('Starting author enrichment process');

        foreach ($db->fetchAll($select) as $value) {
            $this->processPaper($value);
        }

        $this->logger->info('Author enrichment completed successfully');
    }

    /**
     * Clear OpenAIRE Research Graph and enrichment author caches
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

            // Clear enrichment authors cache
            $cacheEnrichment = new FilesystemAdapter('enrichmentAuthors', 0, $cacheDir);
            $cacheEnrichment->clear();
            $this->logger->info('Cleared enrichmentAuthors cache');

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
     * @throws JsonException
     */
    private function processSingleDoi(string $doi): void
    {
        $this->logger->info("Processing single paper by DOI: {$doi}");

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
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
     * Process a single paper's author data
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws JsonException|\Psr\Cache\InvalidArgumentException
     */
    private function processPaper(array $paperData): void
    {
        $paperId = $paperData['PAPERID'];
        $docId = $paperData['DOCID'];
        $doiTrim = trim($paperData['DOI']);

        $this->logger->info("Processing paper", [
            'paperId' => $paperId,
            'docId' => $docId,
            'doi' => $doiTrim ?: 'empty'
        ]);

        if (empty($doiTrim)) {
            $this->processEmptyDoi($paperData);
        } else {
            $this->processPaperWithDoi($paperData);
        }

        // Process HAL repository specific enrichment
        if ($paperData['REPOID'] === Episciences_Repositories::HAL_REPO_ID) {
            $this->processHalRepository($paperData);
        }
    }

    /**
     * Process papers without DOI - copy authors from paper metadata
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws Zend_Db_Statement_Exception
     */
    private function processEmptyDoi(array $paperData): void
    {
        $this->logger->debug('Paper has no DOI - copying authors from paper metadata to author table', [
            'paperId' => $paperData['PAPERID']
        ]);

        $paper = Episciences_PapersManager::get($paperData['DOCID'], false);
        Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
    }

    /**
     * Process papers with DOI - enrich with OpenAIRE data
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException|Zend_Db_Statement_Exception|JsonException
     */
    private function processPaperWithDoi(array $paperData): void
    {
        $paperId = $paperData['PAPERID'];
        $docId = $paperData['DOCID'];
        $doiTrim = trim($paperData['DOI']);

        $this->logger->info("Processing paper with DOI: {$doiTrim}", ['paperId' => $paperId]);

        // Check if authors already exist
        if (!empty(Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId))) {
            $this->logger->info("Authors already exist for paper {$paperId}");
        }

        // Copy authors from paper metadata to author table
        $this->logger->debug('Copying authors from paper metadata to author table');
        $paper = Episciences_PapersManager::get($docId, false);
        Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);

        // If no-cache mode is enabled, delete existing cache for this DOI
        if ($this->isNoCache()) {
            $this->deleteCacheForDoi($doiTrim);
        }

        // Check OpenAIRE cache and fetch if needed
        Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);

        $setsGlobalOARG = Episciences_OpenAireResearchGraphTools::getsGlobalOARGCache($doiTrim);
        [$cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

        if ($setsGlobalOARG->isHit() && !$setsOpenAireCreator->isHit()) {
            // Create creator cache from global OpenAIRE Research Graph cache
            try {
                $decodeOpenAireResp = json_decode(
                    $setsGlobalOARG->get(),
                    true,
                    512,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
                Episciences_OpenAireResearchGraphTools::putCreatorInCache($decodeOpenAireResp, $doiTrim);
                $this->logger->info("Created creator cache from OpenAIRE Research Graph data for DOI: {$doiTrim}");
            } catch (JsonException $e) {
                $errorMsg = sprintf(
                    "JSON decode error for paper %d - URL: https://api.openaire.eu/search/publications/?doi=%s&format=json - Error: %s",
                    $paperId,
                    $doiTrim,
                    $e->getMessage()
                );
                $this->logger->error($errorMsg);

                // OpenAIRE can return malformed JSON, store empty result to avoid repeated failures
                $setsOpenAireCreator->set(json_encode([""]));
                $cacheCreator->save($setsOpenAireCreator);
                return;
            }
            sleep(1);
        }

        // Refresh creator cache to get the new file
        [$cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

        // Insert ORCID author data from OpenAIRE Research Graph
        Episciences_OpenAireResearchGraphTools::insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId);
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

            // Delete enrichment authors cache for this DOI
            $pathOpenAireCreator = md5($doi) . "_creator.json";
            $cacheEnrichment = new FilesystemAdapter('enrichmentAuthors', 0, $cacheDir);
            $cacheEnrichment->deleteItem($pathOpenAireCreator);

            $this->logger->info("Deleted cache entries for DOI: {$doi}");
        } catch (Exception $e) {
            $this->logger->error("Failed to delete cache for DOI {$doi}: " . $e->getMessage());
        }
    }

    /**
     * Process HAL repository specific enrichment with TEI metadata
     *
     * @param array $paperData Paper data from database
     * @return void
     * @throws JsonException|\Psr\Cache\InvalidArgumentException
     */
    private function processHalRepository(array $paperData): void
    {
        $paperId = $paperData['PAPERID'];
        $identifier = trim($paperData['IDENTIFIER']);
        $version = (int)trim($paperData['VERSION']);

        $this->logger->info("Processing HAL repository metadata for identifier: {$identifier}");

        $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
        $decodeAuthor = [];
        foreach ($selectAuthor as $authorsDb) {
            $decodeAuthor = json_decode($authorsDb['authors'], true, 512, JSON_THROW_ON_ERROR);
        }

        // Fetch and cache HAL TEI metadata
        $insertCacheTei = Episciences_Paper_AuthorsManager::getHalTei($identifier, $version);
        if ($insertCacheTei === true) {
            $this->logger->info("Fetched HAL TEI metadata and cached for identifier: {$identifier}");
        }

        $this->logger->debug("Retrieving HAL TEI metadata from cache for identifier: {$identifier}");
        $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($identifier, $version);

        if ($cacheTeiHal === '') {
            return;
        }

        $xmlString = simplexml_load_string($cacheTeiHal);
        if (!is_object($xmlString) || $xmlString->count() === 0) {
            return;
        }

        $authorTei = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($xmlString);
        if (empty($authorTei)) {
            $this->logger->error("No authors found in TEI metadata for identifier {$identifier} (version {$version} may not be the latest)");
            return;
        }

        $this->logger->debug("Extracted authors from TEI metadata");

        $affiInfo = Episciences_Paper_AuthorsManager::getAffiFromHalTei($xmlString);
        $this->logger->debug("Extracted affiliations from TEI metadata");

        $authorTei = Episciences_Paper_AuthorsManager::mergeAuthorInfoAndAffiTei($authorTei, $affiInfo);
        $this->logger->debug("Formatted TEI information for database merge");

        $this->logger->info("Merging TEI metadata with database information for identifier: {$identifier}");
        $formattedAuthorsForDb = Episciences_Paper_AuthorsManager::mergeInfoDbAndInfoTei($decodeAuthor, $authorTei);
        $this->insertAuthors($formattedAuthorsForDb, $paperId, array_key_first($selectAuthor));
    }

    /**
     * Insert or update authors in the database
     *
     * @param array $decodeAuthor Decoded author information
     * @param int $paperId Paper ID
     * @param int $key Author ID key
     * @return void
     */
    private function insertAuthors(array $decodeAuthor, int $paperId, int $key): void
    {
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($decodeAuthor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
    }

    /**
     * Process a single paper by its Paper ID
     *
     * @param int $paperId Paper ID
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function processSinglePaper(int $paperId): void
    {
        $this->logger->info("Processing single paper by ID: {$paperId}");

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
            ->where('PAPERID = ?', $paperId)
            ->where("DOI != ''")
            ->order('DOCID DESC');

        $result = $db->fetchRow($select);

        if (!$result) {
            $this->logger->error("Paper not found with ID: {$paperId}");
            return;
        }

        $this->processPaper($result);
        $this->logger->info("Processing completed for paper ID: {$paperId}");
    }

    /**
     * Check if script is running in dry-run mode
     *
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * Set dry-run mode
     *
     * @param bool $dryRun
     * @return void
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->_dryRun = $dryRun;
    }


}

// Only run the script if executed directly (not when included for testing)
if (!defined('PHPUNIT_RUNNING') && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $script = new GetCreatorData($localopts);
    $script->run();
}