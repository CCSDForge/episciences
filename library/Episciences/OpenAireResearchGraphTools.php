<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_OpenAireResearchGraphTools
{
    // Cache TTL
    public const ONE_MONTH = 3600 * 24 * 31;

    // API Configuration
    private const API_BASE_URL = 'https://api.openaire.eu/search/publications';
    private const API_USER_AGENT = 'CCSD Episciences support@episciences.org';
    private const API_TIMEOUT_SECONDS = 30;
    private const API_MAX_REDIRECTS = 2;

    // Cache Configuration
    private const CACHE_POOL_OARG = 'openAireResearchGraph';
    private const CACHE_POOL_AUTHORS = 'enrichmentAuthors';
    private const CACHE_POOL_FUNDING = 'enrichmentFunding';
    private const CACHE_FILE_SUFFIX_CREATOR = '_creator.json';
    private const CACHE_FILE_SUFFIX_FUNDING = '_funding.json';

    // Security Limits
    private const MAX_DOI_LENGTH = 200;
    private const JSON_MAX_DEPTH = 50; // Reduced from 512 to prevent DoS
    private const MAX_RESPONSE_SIZE = 5242880; // 5MB

    // JSON Configuration
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR; // Only meaningful flags for json_decode
    private const EMPTY_RESULT_MARKER = [""];

    // Array Keys for API and Database Data
    private const ARRAY_KEY_ORCID = '@orcid';              // ORCID key in API response
    private const ARRAY_KEY_FULLNAME = 'fullname';         // Author fullname in DB
    private const ARRAY_KEY_AUTHORS = 'authors';           // Authors array in DB
    private const ARRAY_KEY_API_NAME = '$';                // Author name in API response
    private const ARRAY_KEY_ORCID_DB = 'orcid';           // ORCID key in database

    // Singleton instances
    private static ?Logger $logger = null;
    /** @var array<string, FilesystemAdapter> */
    private static array $cacheAdapters = [];

    /**
     * Validate and sanitize DOI using existing Episciences_Tools
     *
     * @param string $doi The DOI to validate
     * @return string The validated and trimmed DOI
     * @throws InvalidArgumentException If DOI is invalid
     */
    private static function validateDoi(string $doi): string
    {
        return Episciences_Tools::validateDoi($doi, self::MAX_DOI_LENGTH);
    }

    /**
     * Generate secure cache key from DOI
     *
     * @param string $doi The DOI (must be validated first)
     * @param string $suffix Optional suffix for the cache key
     * @return string MD5 hash cache key with optional suffix
     */
    private static function generateCacheKey(string $doi, string $suffix = '.json'): string
    {
        return md5($doi) . $suffix;
    }

    /**
     * Validate ORCID format
     *
     * @param string $orcid The ORCID identifier to validate
     * @return bool True if valid ORCID format
     */
    private static function validateOrcid(string $orcid): bool
    {
        return Episciences_Tools::isValidOrcid($orcid);
    }

    /**
     * Get cache directory path
     *
     * @return string The cache directory path
     */
    private static function getCacheDirectory(): string
    {
        return dirname(APPLICATION_PATH) . '/cache/';
    }

    /**
     * Get or create singleton cache adapter for a specific pool
     *
     * @param string $poolName The cache pool name (use class constants)
     * @return FilesystemAdapter The cache adapter instance
     */
    private static function getCacheAdapter(string $poolName): FilesystemAdapter
    {
        if (!isset(self::$cacheAdapters[$poolName])) {
            self::$cacheAdapters[$poolName] = new FilesystemAdapter(
                $poolName,
                self::ONE_MONTH,
                self::getCacheDirectory()
            );
        }

        return self::$cacheAdapters[$poolName];
    }

    /**
     * Get or create singleton logger instance
     *
     * @return Logger The logger instance
     * @throws Exception
     */
    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('openaire_researchgraph_tools');
            self::$logger->pushHandler(
                new StreamHandler(
                    EPISCIENCES_LOG_PATH . 'openAireResearchGraph_' . date('Y-m-d') . '.log',
                    Logger::INFO
                )
            );
        }

        return self::$logger;
    }

    /**
     * Log message to file and optionally to console (if CLI).
     *
     * Adds a stdout handler once if not already present when running in CLI.
     * A single log call is made so the message is never duplicated in the file.
     *
     * @param string $msg Message to log
     * @param string $level Log level: 'debug', 'info', 'warning', 'error', etc.
     * @param bool $alsoToConsole Whether to also output to stdout in CLI context
     * @return void
     */
    private static function log(string $msg, string $level = 'info', bool $alsoToConsole = true): void
    {
        try {
            $logger = self::getLogger();

            if ($alsoToConsole && PHP_SAPI === 'cli') {
                $hasConsoleHandler = false;
                foreach ($logger->getHandlers() as $handler) {
                    if ($handler instanceof StreamHandler && $handler->getUrl() === 'php://stdout') {
                        $hasConsoleHandler = true;
                        break;
                    }
                }
                if (!$hasConsoleHandler) {
                    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
                }
            }

            $logger->$level($msg);
        } catch (Exception $e) {
            error_log('Monolog failed: ' . $e->getMessage() . ' | Original message: ' . $msg);
        }
    }

    /**
     * Decode JSON string into an associative array (or object).
     *
     * Uses json_decode directly with JSON_THROW_ON_ERROR — no pre-validation needed.
     *
     * @param string $json The JSON string to decode
     * @param bool $associative Return associative array instead of object
     * @return mixed The decoded JSON data
     * @throws JsonException If JSON is invalid
     */
    private static function decodeJson(string $json, bool $associative = true): mixed
    {
        return json_decode($json, $associative, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS);
    }

    /**
     * Encode data to JSON using class constants
     *
     * @param mixed $data The data to encode
     * @return string The JSON string
     */
    private static function encodeJson(mixed $data): string
    {
        return json_encode($data, self::JSON_ENCODE_FLAGS);
    }

    /**
     * Build OpenAIRE API URL with validated DOI
     *
     * @param string $doi The DOI (will be validated)
     * @return string The complete API URL
     * @throws InvalidArgumentException If DOI is invalid
     */
    private static function buildApiUrl(string $doi): string
    {
        $validatedDoi = self::validateDoi($doi);
        return self::API_BASE_URL . '/?doi=' . urlencode($validatedDoi) . '&format=json';
    }

    /**
     * Create empty result marker for cache
     *
     * @return string JSON encoded empty result marker
     */
    private static function createEmptyResultMarker(): string
    {
        return self::encodeJson(self::EMPTY_RESULT_MARKER);
    }

    /**
     * Check if cached result is empty marker
     *
     * @param mixed $cachedData The cached data to check
     * @return bool True if the data is an empty result marker
     */
    private static function isEmptyResult(mixed $cachedData): bool
    {
        return is_array($cachedData) && $cachedData === self::EMPTY_RESULT_MARKER;
    }

    /**
     * Check and cache OpenAIRE global info for a DOI
     *
     * Fetches data from OpenAIRE API if not already cached. This avoids multiple
     * API calls for the same DOI and caches all related info (licenses, creators, etc.)
     *
     * @param string $doi The DOI to check
     * @param int $paperId The paper ID for logging purposes
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function checkOpenAireGlobalInfoByDoi(string $doi, int $paperId): void
    {
        // Validate DOI first
        $validatedDoi = self::validateDoi($doi);

        // Ensure metadata directory exists
        $dir = CACHE_PATH_METADATA . 'openAireResearchGraph/';
        if (!file_exists($dir)) {
            $result = Episciences_Tools::recursiveMkdir($dir);
            if (!$result) {
                throw new RuntimeException('Failed to create directory: ' . $dir);
            }
        }

        $cacheKey = self::generateCacheKey($validatedDoi, '.json');
        $cache = self::getCacheAdapter(self::CACHE_POOL_OARG);
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::ONE_MONTH);

        if (!$cacheItem->isHit()) {
            $client = new Client();
            $apiUrl = self::buildApiUrl($validatedDoi);

            self::log("Calling OpenAIRE API: {$apiUrl}", 'info');

            $openAireResponse = self::callOpenAireApi($client, $validatedDoi);

            try {
                $decodedResponse = self::decodeJson($openAireResponse);
                $cacheItem->set(self::encodeJson($decodedResponse));
                $cache->save($cacheItem);
            } catch (JsonException $e) {
                $errorMsg = sprintf(
                    'JSON decode error for PAPER %d - URL: %s - Error: %s',
                    $paperId,
                    $apiUrl,
                    $e->getMessage()
                );

                self::log($errorMsg, 'error');

                // OpenAIRE can return malformed JSON, cache empty result to avoid repeated failures
                $cacheItem->set(self::createEmptyResultMarker());
                $cache->save($cacheItem);
            }
        }
    }

    /**
     * Call OpenAIRE API with security configurations
     *
     * @param Client $client The Guzzle HTTP client
     * @param string $doi The DOI to query (should already be validated)
     * @return string The API response body
     * @throws InvalidArgumentException If DOI is invalid
     */
    public static function callOpenAireApi(Client $client, string $doi): string
    {
        $apiUrl = self::buildApiUrl($doi);
        $responseBody = '';

        try {
            $response = $client->get($apiUrl, [
                'headers' => [
                    'User-Agent'   => self::API_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout'         => self::API_TIMEOUT_SECONDS,
                'allow_redirects' => [
                    'max'       => self::API_MAX_REDIRECTS,
                    'strict'    => true,
                    'referer'   => true,
                    'protocols' => ['https'], // Security: only allow HTTPS redirects
                ],
                'verify' => true, // Security: verify SSL certificates
            ]);

            $responseBody = $response->getBody()->getContents();

            // Security: check response size to prevent memory exhaustion
            if (strlen($responseBody) > self::MAX_RESPONSE_SIZE) {
                self::logErrorMsg(sprintf(
                    'API response exceeds maximum size limit (%d bytes) for DOI: %s',
                    self::MAX_RESPONSE_SIZE,
                    $doi
                ));
                return '';
            }
        } catch (GuzzleException $e) {
            $errorMsg = 'OpenAIRE API error: ' . $e->getMessage();
            self::logErrorMsg($errorMsg);
        }

        // Rate limiting: wait 1 second between API calls
        sleep(1);

        return $responseBody;
    }

    /**
     * Log a message at info level using the singleton logger instance.
     *
     * @param string $msg The message to log
     * @return void
     * @throws Exception
     */
    public static function logErrorMsg(string $msg): void
    {
        self::getLogger()->info($msg);
    }

    /**
     * Get global OpenAIRE Research Graph cache item for a DOI
     *
     * @param string $doi The DOI to get cache for
     * @return CacheItemInterface The cache item
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws InvalidArgumentException If DOI is invalid
     */
    public static function getsGlobalOARGCache(string $doi): CacheItemInterface
    {
        $validatedDoi = self::validateDoi($doi);
        $cacheKey = self::generateCacheKey($validatedDoi, '.json');
        $cache = self::getCacheAdapter(self::CACHE_POOL_OARG);
        return $cache->getItem($cacheKey);
    }

    /**
     * Get creator cache data for a DOI
     *
     * @param string $doi The DOI to get cache for
     * @return array{0: FilesystemAdapter, 1: string, 2: CacheItemInterface}
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws InvalidArgumentException If DOI is invalid
     */
    public static function getCreatorCacheOA(string $doi): array
    {
        $validatedDoi = self::validateDoi($doi);
        $cacheKey = self::generateCacheKey($validatedDoi, self::CACHE_FILE_SUFFIX_CREATOR);
        $cache = self::getCacheAdapter(self::CACHE_POOL_AUTHORS);
        $cacheItem = $cache->getItem($cacheKey);
        return [$cache, $cacheKey, $cacheItem];
    }

    /**
     * Normalize API data to always return an array
     *
     * The API sometimes returns a single object instead of an array.
     * This method ensures we always work with an array format.
     *
     * @param array<mixed> $fileFound The decoded API response
     * @return array<mixed> Normalized array of author data
     */
    private static function normalizeApiData(array $fileFound): array
    {
        if (!array_key_exists(0, $fileFound)) {
            return [$fileFound];
        }
        return $fileFound;
    }

    /**
     * Process a single author to find and update ORCID
     *
     * @param array<string, mixed> $author Author data by reference (will be modified if ORCID found)
     * @param string $fullname Author's full name from database
     * @param array<mixed> $apiData Normalized API data array
     * @param int $paperId Paper ID for logging
     * @param int|string $recordKey Database record key for logging
     * @return bool True if ORCID was added, false otherwise
     * @throws Exception
     */
    private static function processAuthorOrcid(array &$author, string $fullname, array $apiData, int $paperId, int|string $recordKey): bool
    {
        if (empty($fullname) || !empty($author[self::ARRAY_KEY_ORCID_DB])) {
            return false;
        }

        $foundOrcid = self::findOrcidForAuthor($fullname, $apiData, 0);

        if ($foundOrcid && self::validateOrcid($foundOrcid)) {
            $author[self::ARRAY_KEY_ORCID_DB] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($foundOrcid);
            self::log("Added ORCID $foundOrcid for author $fullname (record $recordKey, paper $paperId)", 'info');
            return true;
        } elseif ($foundOrcid) {
            self::logErrorMsg("Invalid ORCID format: $foundOrcid for author: $fullname");
        }

        return false;
    }

    /**
     * Update all author records for a paper with ORCID data from API
     *
     * @param array<int|string, mixed> $authorRecords Array of author records from database
     * @param array<mixed> $apiData Normalized API data array
     * @param int $paperId Paper ID
     * @return int Number of affected rows/authors updated
     * @throws JsonException
     */
    private static function updatePaperAuthors(array $authorRecords, array $apiData, int $paperId): int
    {
        $affectedRow = 0;

        foreach ($authorRecords as $key => $authorInfo) {
            $decodeAuthor = self::decodeJson($authorInfo[self::ARRAY_KEY_AUTHORS]);
            $originalAuthorsArray = $decodeAuthor;
            $recordUpdated = false;

            foreach ($decodeAuthor as $authorIndex => $singleAuthor) {
                $authorFullName = $singleAuthor[self::ARRAY_KEY_FULLNAME] ?? '';

                if (self::processAuthorOrcid($decodeAuthor[$authorIndex], $authorFullName, $apiData, $paperId, $key)) {
                    $recordUpdated = true;
                }
            }

            if ($recordUpdated && $decodeAuthor !== $originalAuthorsArray) {
                self::insertAuthors($decodeAuthor, $paperId, $key);
                $affectedRow++;
            }
        }

        return $affectedRow;
    }

    /**
     * Insert ORCID data from OpenAIRE Research Graph cache
     *
     * CRITICAL FIX: Original had inverted cache logic - was returning early on cache HIT
     *
     * @param CacheItemInterface $setsOpenAireCreator The cache item containing creator data
     * @param int $paperId The paper ID to update
     * @return int Number of affected rows/authors updated
     * @throws JsonException|Exception
     */
    public static function insertOrcidAuthorFromOARG(CacheItemInterface $setsOpenAireCreator, int $paperId): int
    {
        if (!$setsOpenAireCreator->isHit()) {
            return 0;
        }

        try {
            $fileFound = self::decodeJson($setsOpenAireCreator->get());

            if (self::isEmptyResult($fileFound)) {
                return 0;
            }

            $apiData = self::normalizeApiData($fileFound);
            $authorRecords = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);

            return self::updatePaperAuthors($authorRecords, $apiData, $paperId);
        } catch (JsonException $e) {
            self::logErrorMsg("JSON decode error in insertOrcidAuthorFromOARG: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Find ORCID for a specific author from API data
     *
     * Searches through the OpenAire Research Graph API data to find a matching ORCID
     * for the given author name.
     *
     * @param string $needleFullName The author's full name from database
     * @param array<mixed> $reformatFileFound The formatted API data array
     * @param int $authorIndex The index of the author being processed
     * @return string|null The ORCID if found, null otherwise
     * @throws Exception
     */
    private static function findOrcidForAuthor(string $needleFullName, array $reformatFileFound, int $authorIndex): ?string
    {
        foreach ($reformatFileFound as $authorInfoFromApi) {
            $isMatch = false;

            if (array_search($needleFullName, $authorInfoFromApi, true) !== false ||
                array_search(Episciences_Tools::replaceAccents($needleFullName), $authorInfoFromApi, true) !== false) {
                $isMatch = true;
            } elseif (isset($authorInfoFromApi[self::ARRAY_KEY_API_NAME]) &&
                Episciences_Tools::replaceAccents($needleFullName) === Episciences_Tools::replaceAccents($authorInfoFromApi[self::ARRAY_KEY_API_NAME])) {
                $isMatch = true;
            }

            if ($isMatch) {
                if (array_key_exists(self::ARRAY_KEY_ORCID, $authorInfoFromApi)) {
                    $orcid = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi[self::ARRAY_KEY_ORCID]);

                    $apiName = $authorInfoFromApi[self::ARRAY_KEY_API_NAME] ?? 'UNKNOWN';
                    self::logErrorMsg("ORCID match: $orcid for author '$needleFullName' matched with API: '$apiName'");

                    return $orcid;
                }
            }
        }

        return null;
    }

    /**
     * Insert or update author information in database
     *
     * @param array<mixed> $decodeAuthor The decoded author data array
     * @param int $paperId The paper ID
     * @param int|string $key The authors record ID
     * @return int Number of affected rows
     */
    public static function insertAuthors(array $decodeAuthor, int $paperId, int|string $key): int
    {
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(self::encodeJson($decodeAuthor));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        return Episciences_Paper_AuthorsManager::update($newAuthorInfos);
    }

    /**
     * Put creator data into cache
     *
     * @param array<string, mixed>|null $decodeOpenAireResp The decoded OpenAIRE API response
     * @param string $doi The DOI identifier
     * @return void
     * @throws JsonException If JSON encoding fails
     * @throws InvalidArgumentException|\Psr\Cache\InvalidArgumentException
     */
    public static function putCreatorInCache(?array $decodeOpenAireResp, string $doi): void
    {
        $validatedDoi = self::validateDoi($doi);
        $cacheKey = self::generateCacheKey($validatedDoi, self::CACHE_FILE_SUFFIX_CREATOR);
        $cache = self::getCacheAdapter(self::CACHE_POOL_AUTHORS);
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::ONE_MONTH);

        if (!self::isEmptyResult($decodeOpenAireResp) &&
            $decodeOpenAireResp !== null &&
            !empty($decodeOpenAireResp['response']['results'])) {

            if (array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                $creatorArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
                $cacheItem->set(self::encodeJson($creatorArrayOpenAire));
                $cache->save($cacheItem);
                return;
            }
        }

        $cacheItem->set(self::createEmptyResultMarker());
        $cache->save($cacheItem);
    }

    /**
     * @param string $needleFullName The author's full name to search for
     * @param array<string, mixed> $authorInfoFromApi The author data from API
     * @param array<mixed> $decodeAuthor The decoded author array from database
     * @param int|string $keyDbJson The key/index in the author array
     * @param int $flagNewOrcid Flag indicating if new ORCID was found
     * @return array{0: array<mixed>, 1: int}
     * @throws Exception
     * @deprecated Use findOrcidForAuthor() instead, which provides better ORCID validation
     *             and reduced logging noise.
     */
    public static function getOrcidApiForDb(string $needleFullName, array $authorInfoFromApi, array $decodeAuthor, int|string $keyDbJson, int $flagNewOrcid): array
    {
        trigger_error(
            'getOrcidApiForDb() is deprecated. Use findOrcidForAuthor() instead.',
            E_USER_DEPRECATED
        );

        $msgLogAuthorFound = "Author Found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true);

        if (array_search($needleFullName, $authorInfoFromApi, true) !== false ||
            array_search(Episciences_Tools::replaceAccents($needleFullName), $authorInfoFromApi, true) !== false) {

            self::logErrorMsg($msgLogAuthorFound);

            if (array_key_exists(self::ARRAY_KEY_ORCID, $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson][self::ARRAY_KEY_ORCID_DB])) {
                $decodeAuthor[$keyDbJson][self::ARRAY_KEY_ORCID_DB] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi[self::ARRAY_KEY_ORCID]);
                $flagNewOrcid = 1;
            }
        } elseif (isset($authorInfoFromApi[self::ARRAY_KEY_API_NAME]) &&
                  Episciences_Tools::replaceAccents($needleFullName) === Episciences_Tools::replaceAccents($authorInfoFromApi[self::ARRAY_KEY_API_NAME])) {

            self::logErrorMsg($msgLogAuthorFound);

            if (array_key_exists(self::ARRAY_KEY_ORCID, $authorInfoFromApi)) {
                $decodeAuthor[$keyDbJson][self::ARRAY_KEY_ORCID_DB] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi[self::ARRAY_KEY_ORCID]);
                $flagNewOrcid = 1;
            }
        } else {
            $apiName = $authorInfoFromApi[self::ARRAY_KEY_API_NAME] ?? 'UNKNOWN';
            self::logErrorMsg("No matching : API " . $apiName . " #DB# " . $needleFullName);
        }

        if (!isset($decodeAuthor[$keyDbJson][self::ARRAY_KEY_ORCID_DB])) {
            self::logErrorMsg("ORCID not found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true));
        }

        if ($flagNewOrcid === 1) {
            self::logErrorMsg("ORCID found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true));
        }

        return [$decodeAuthor, $flagNewOrcid];
    }

    /**
     * Put funding data into cache
     *
     * @param array<string, mixed>|null $decodeOpenAireResp The decoded OpenAIRE API response
     * @param string $doi The DOI identifier
     * @return void
     * @throws JsonException If JSON encoding fails
     * @throws InvalidArgumentException|\Psr\Cache\InvalidArgumentException
     */
    public static function putFundingsInCache(?array $decodeOpenAireResp, string $doi): void
    {
        $validatedDoi = self::validateDoi($doi);
        $cacheKey = self::generateCacheKey($validatedDoi, self::CACHE_FILE_SUFFIX_FUNDING);
        $cache = self::getCacheAdapter(self::CACHE_POOL_FUNDING);
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::ONE_MONTH);

        if (!self::isEmptyResult($decodeOpenAireResp) &&
            $decodeOpenAireResp !== null &&
            !is_null($decodeOpenAireResp['response']['results'])) {

            if (array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                $preFundingArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result'];

                if (array_key_exists('rels', $preFundingArrayOpenAire)) {
                    if (!empty($preFundingArrayOpenAire['rels']) && array_key_exists('rel', $preFundingArrayOpenAire['rels'])) {
                        $arrayFunding = $preFundingArrayOpenAire['rels']['rel'];
                        $cacheItem->set(self::encodeJson($arrayFunding));
                        $cache->save($cacheItem);
                        return;
                    }
                }
            }
        }

        $cacheItem->set(self::createEmptyResultMarker());
        $cache->save($cacheItem);
    }

    /**
     * Get funding cache data for a DOI
     *
     * @param string $doi The DOI to get cache for
     * @return array{0: FilesystemAdapter, 1: string, 2: CacheItemInterface}
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws InvalidArgumentException If DOI is invalid
     */
    public static function getFundingCacheOA(string $doi): array
    {
        $validatedDoi = self::validateDoi($doi);
        $cacheKey = self::generateCacheKey($validatedDoi, self::CACHE_FILE_SUFFIX_FUNDING);
        $cache = self::getCacheAdapter(self::CACHE_POOL_FUNDING);
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::ONE_MONTH);
        return [$cache, $cacheKey, $cacheItem];
    }
}
