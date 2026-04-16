<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * OpenAire Research Graph REST API client.
 *
 * Cache namespaces:
 *  - openAireResearchGraph : md5($doi) . '.json'
 *  - enrichmentAuthors     : md5($doi) . '_creator.json'
 *  - enrichmentFunding     : md5($doi) . '_funding.json'
 *
 * Three separate PSR-6 pools are injected (one per namespace).
 *
 */
class OpenAireApiClient extends AbstractApiClient
{
    private const API_BASE_URL  = 'https://api.openaire.eu/search/publications';
    private const MAX_RESPONSE_SIZE = 5242880; // 5 MB

    private CacheItemPoolInterface $globalCache;
    private CacheItemPoolInterface $authorsCache;
    private CacheItemPoolInterface $fundingCache;

    public function __construct(
        Client               $client,
        CacheItemPoolInterface $globalCache,
        CacheItemPoolInterface $authorsCache,
        CacheItemPoolInterface $fundingCache,
        LoggerInterface      $logger
    ) {
        // Parent constructor requires a single CacheItemPoolInterface; pass globalCache as primary.
        parent::__construct($client, $globalCache, $logger);
        $this->globalCache  = $globalCache;
        $this->authorsCache = $authorsCache;
        $this->fundingCache = $fundingCache;
    }

    // -------------------------------------------------------------------------
    // Publication fetch
    // -------------------------------------------------------------------------

    /**
     * Fetch OpenAire publication data for a DOI (cached in globalCache pool).
     *
     * Returns null on API error; returns [] on empty/malformed response.
     *
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function fetchPublication(string $doi, int $paperId): ?array
    {
        $key  = md5($doi) . '.json';
        $item = $this->globalCache->getItem($key);

        if ($item->isHit()) {
            $data = json_decode((string) $item->get(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            return $data;
        }

        $url = self::API_BASE_URL . '/?doi=' . urlencode($doi) . '&format=json';
        $this->logger->info("Fetching OpenAIRE data for DOI {$doi}");

        try {
            $response = $this->client->get($url, [
                'headers'         => $this->defaultHeaders(),
                'timeout'         => 30,
                'allow_redirects' => [
                    'max'       => 2,
                    'strict'    => true,
                    'referer'   => true,
                    'protocols' => ['https'],
                ],
                'verify'          => true,
            ]);
            $body = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf(
                'OpenAIRE API error for paper %d: %s',
                $paperId,
                $e->getMessage()
            ));
            return null;
        }

        if (strlen($body) > self::MAX_RESPONSE_SIZE) {
            $this->logger->error(sprintf(
                'OpenAIRE response too large for DOI %s (%d bytes)',
                $doi,
                strlen($body)
            ));
            return null;
        }

        // Rate limiting
        sleep(1);

        try {
            $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error(sprintf(
                'JSON decode error for paper %d (OpenAIRE): %s',
                $paperId, $e->getMessage()
            ));
            $item->set(json_encode(['']));
            $item->expiresAfter(self::ONE_MONTH);
            $this->globalCache->save($item);
            return [];
        }

        $item->set(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $item->expiresAfter(self::ONE_MONTH);
        $this->globalCache->save($item);

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Creator extraction
    // -------------------------------------------------------------------------

    /**
     * Extract creator array from OpenAire response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractCreators(array $response): ?array
    {
        if (empty($response['response']['results']['result'][0])) {
            return null;
        }
        $result = $response['response']['results']['result'][0];
        return $result['metadata']['oaf:entity']['oaf:result']['creator'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Funding extraction
    // -------------------------------------------------------------------------

    /**
     * Extract funding array from OpenAire response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractFunding(array $response): ?array
    {
        if (empty($response['response']['results']['result'][0])) {
            return null;
        }
        $rels = $response['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['rels'] ?? null;
        if ($rels === null || !array_key_exists('rel', $rels)) {
            return null;
        }
        return $rels['rel'];
    }

    // -------------------------------------------------------------------------
    // JEL classification extraction
    // -------------------------------------------------------------------------

    /**
     * Extract JEL classification codes from an OpenAire publication response.
     *
     * @param array<string, mixed> $response
     * @return array<string> unique JEL codes (e.g. "A10", "B23")
     */
    public function extractJelCodes(array $response): array
    {
        $codes = [];
        if (!isset($response['response']['results']['result'])) {
            return $codes;
        }
        foreach ($response['response']['results']['result'] as $result) {
            $subjects = $result['metadata']['oaf:entity']['oaf:result']['subject'] ?? null;
            if ($subjects === null) {
                continue;
            }
            // subject may be a single object or an array of objects
            if (!is_array($subjects) || isset($subjects['@classid'])) {
                $subjects = [$subjects];
            }
            foreach ($subjects as $subject) {
                if (($subject['@classid'] ?? '') !== 'jel' || !isset($subject['$'])) {
                    continue;
                }
                $value = $subject['$'];
                if (str_starts_with($value, 'jel:')) {
                    $code = substr($value, 4); // fix: ltrim('jel:') strips chars, not the prefix string
                    if ($code !== '') {
                        $codes[] = $code;
                    }
                }
            }
        }
        return array_unique($codes);
    }

    // -------------------------------------------------------------------------
    // Cache helpers (author / funding pools)
    // -------------------------------------------------------------------------

    /**
     * Return [cache, cacheKey, cacheItem] for the creators pool.
     *
     * @return array{0: CacheItemPoolInterface, 1: string, 2: \Psr\Cache\CacheItemInterface}
     * @throws InvalidArgumentException
     */
    public function getCreatorCacheItem(string $doi): array
    {
        $key  = md5($doi) . '_creator.json';
        $item = $this->authorsCache->getItem($key);
        return [$this->authorsCache, $key, $item];
    }

    /**
     * Return [cache, cacheKey, cacheItem] for the funding pool.
     *
     * @return array{0: CacheItemPoolInterface, 1: string, 2: \Psr\Cache\CacheItemInterface}
     * @throws InvalidArgumentException
     */
    public function getFundingCacheItem(string $doi): array
    {
        $key  = md5($doi) . '_funding.json';
        $item = $this->fundingCache->getItem($key);
        $item->expiresAfter(self::ONE_MONTH);
        return [$this->fundingCache, $key, $item];
    }

    /**
     * Return the global cache item for a DOI.
     *
     * @throws InvalidArgumentException
     */
    public function getGlobalCacheItem(string $doi): \Psr\Cache\CacheItemInterface
    {
        return $this->globalCache->getItem(md5($doi) . '.json');
    }

    // -------------------------------------------------------------------------
    // Static factory
    // -------------------------------------------------------------------------

    /**
     * Build a production-ready instance using FilesystemAdapter caches and a file logger.
     *
     * Constants APPLICATION_PATH and EPISCIENCES_LOG_PATH must be defined by the bootstrap.
     */
    public static function create(): self
    {
        $cacheDir = dirname(APPLICATION_PATH) . '/cache/';

        $logger = new Logger('openaire_api_client');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'openAireResearchGraph_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));

        return new self(
            new Client(),
            new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentAuthors',     self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentFunding',     self::ONE_MONTH, $cacheDir),
            $logger
        );
    }

    // -------------------------------------------------------------------------
    // Derived-cache writers
    // -------------------------------------------------------------------------

    /**
     * Extract creators from an OpenAire response and persist them in the authors cache.
     *
     * Stores an empty marker ([""] JSON) when the response contains no creator data.
     *
     * @param array<string, mixed>|null $response Decoded OpenAire API response, or null on error.
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    public function putCreatorInCache(?array $response, string $doi): void
    {
        $key  = md5($doi) . '_creator.json';
        $item = $this->authorsCache->getItem($key);
        $item->expiresAfter(self::ONE_MONTH);

        $creators = ($response !== null) ? $this->extractCreators($response) : null;

        $item->set($creators !== null
            ? json_encode($creators, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : json_encode([''], JSON_THROW_ON_ERROR)
        );
        $this->authorsCache->save($item);
    }

    /**
     * Extract funding from an OpenAire response and persist it in the funding cache.
     *
     * Stores an empty marker ([""] JSON) when the response contains no funding data.
     *
     * @param array<string, mixed>|null $response Decoded OpenAire API response, or null on error.
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    public function putFundingInCache(?array $response, string $doi): void
    {
        $key  = md5($doi) . '_funding.json';
        $item = $this->fundingCache->getItem($key);
        $item->expiresAfter(self::ONE_MONTH);

        $funding = ($response !== null) ? $this->extractFunding($response) : null;

        $item->set($funding !== null
            ? json_encode($funding, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : json_encode([''], JSON_THROW_ON_ERROR)
        );
        $this->fundingCache->save($item);
    }

    // -------------------------------------------------------------------------
    // ORCID DB enrichment
    // -------------------------------------------------------------------------

    /**
     * Read creator data from a cache item and update paper author records with ORCID values.
     *
     * Returns the number of author DB records that were updated.
     * Returns 0 immediately on cache miss or empty/malformed cache content.
     *
     * Note: the DB-write branch (Episciences_Paper_AuthorsManager calls) is covered by
     * integration tests; unit tests cover the cache-miss and empty-marker paths.
     *
     * @throws \JsonException
     */
    public function insertOrcidAuthorFromCache(CacheItemInterface $creatorItem, int $paperId): int
    {
        if (!$creatorItem->isHit()) {
            return 0;
        }

        try {
            $fileFound = json_decode(
                (string) $creatorItem->get(),
                true,
                self::JSON_MAX_DEPTH,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $this->logger->error('JSON decode error in insertOrcidAuthorFromCache: ' . $e->getMessage());
            return 0;
        }

        // Empty result marker: [""]
        if (!is_array($fileFound) || $fileFound === ['']) {
            return 0;
        }

        // Normalize: API sometimes returns a single associative object instead of a list.
        $apiData = array_key_exists(0, $fileFound) ? $fileFound : [$fileFound];

        $authorRecords = \Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
        $affectedRows  = 0;

        foreach ($authorRecords as $recordKey => $authorInfo) {
            try {
                $decodeAuthor = json_decode(
                    $authorInfo['authors'],
                    true,
                    self::JSON_MAX_DEPTH,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                $this->logger->error('JSON decode error for author record: ' . $e->getMessage());
                continue;
            }

            $originalAuthors = $decodeAuthor;
            $recordUpdated   = false;

            foreach ($decodeAuthor as $idx => $singleAuthor) {
                $fullname = $singleAuthor['fullname'] ?? '';
                if (empty($fullname) || !empty($singleAuthor['orcid'])) {
                    continue;
                }

                $orcid = $this->findOrcidForAuthor($fullname, $apiData);
                if ($orcid !== null) {
                    $decodeAuthor[$idx]['orcid'] = $orcid; // already cleaned by findOrcidForAuthor
                    $this->logger->info("Added ORCID $orcid for author $fullname (paper $paperId)");
                    $recordUpdated = true;
                }
            }

            if ($recordUpdated && $decodeAuthor !== $originalAuthors) {
                $newAuthorInfos = new \Episciences_Paper_Authors();
                $newAuthorInfos->setAuthors(json_encode(
                    $decodeAuthor,
                    JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ));
                $newAuthorInfos->setPaperId($paperId);
                $newAuthorInfos->setAuthorsId($recordKey);
                \Episciences_Paper_AuthorsManager::update($newAuthorInfos);
                $affectedRows++;
            }
        }

        return $affectedRows;
    }

    // -------------------------------------------------------------------------
    // ORCID author matching
    // -------------------------------------------------------------------------

    /**
     * Search API data for a matching ORCID for the given author full name.
     *
     * @param array<int, array<string, mixed>> $apiData
     */
    public function findOrcidForAuthor(string $fullName, array $apiData): ?string
    {
        foreach ($apiData as $authorInfoFromApi) {
            $isMatch = false;

            if (array_search($fullName, $authorInfoFromApi, true) !== false
                || array_search(\Episciences_Tools::replaceAccents($fullName), $authorInfoFromApi, true) !== false
            ) {
                $isMatch = true;
            } elseif (isset($authorInfoFromApi['$'])
                && \Episciences_Tools::replaceAccents($fullName) === \Episciences_Tools::replaceAccents($authorInfoFromApi['$'])
            ) {
                $isMatch = true;
            }

            if ($isMatch && array_key_exists('@orcid', $authorInfoFromApi)) {
                return \Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
            }
        }
        return null;
    }
}
