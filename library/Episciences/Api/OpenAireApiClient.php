<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * OpenAire Research Graph v3 REST API client.
 *
 * @see https://graph.openaire.eu/docs/apis/search-api
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
    private const API_BASE_URL  = 'https://api.openaire.eu/graph/v3/research-products';
    private const MAX_RESPONSE_SIZE = 5242880; // 5 MB
    private const ORCID_SCHEMES = ['orcid', 'orcid_pending'];

    // Rate limits per https://graph.openaire.eu/docs/apis/terms#authentication--limits
    private const AUTH_THROTTLE_MICROSECONDS = 500000; // 500ms (~2 req/s, quota 7200 req/h, authenticated)
    private const UNAUTH_THROTTLE_SECONDS    = 60;      // 60s (1 req/min, quota 60 req/h, anonymous)
    private const MAX_RETRIES = 3;

    private CacheItemPoolInterface $globalCache;
    private CacheItemPoolInterface $authorsCache;
    private CacheItemPoolInterface $fundingCache;
    private ?OpenAireTokenProvider $tokenProvider;

    public function __construct(
        Client               $client,
        CacheItemPoolInterface $globalCache,
        CacheItemPoolInterface $authorsCache,
        CacheItemPoolInterface $fundingCache,
        LoggerInterface      $logger,
        ?OpenAireTokenProvider $tokenProvider = null
    ) {
        // Parent constructor requires a single CacheItemPoolInterface; pass globalCache as primary.
        parent::__construct($client, $globalCache, $logger);
        $this->globalCache   = $globalCache;
        $this->authorsCache  = $authorsCache;
        $this->fundingCache  = $fundingCache;
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * True when a token provider is set up with client credentials (authenticated mode).
     */
    public function isAuthenticated(): bool
    {
        return $this->tokenProvider?->isConfigured() ?? false;
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
            $this->logger->info("OpenAIRE data from cache for DOI {$doi}");
            return json_decode((string) $item->get(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        }

        // @phpstan-ignore notIdentical.alwaysTrue
        $apiBaseUrl = (defined('OPENAIRE_API_URL') && (string) constant('OPENAIRE_API_URL') !== '')
            ? (string) constant('OPENAIRE_API_URL')
            : self::API_BASE_URL;
        $url = $apiBaseUrl . '?pid=' . urlencode($doi);
        $this->logger->info("Fetching OpenAIRE data for DOI {$doi}");

        $body = $this->requestWithRetry($url, $paperId);
        if ($body === null) {
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
    // HTTP request with bi-mode auth, adaptive throttling, and 401/429 retry
    // -------------------------------------------------------------------------

    /**
     * Issue the GET request, handling authentication, throttling, and 401/429 retry.
     * Returns the raw response body, or null on unrecoverable error / exhausted retries.
     */
    private function requestWithRetry(string $url, int $paperId): ?string
    {
        $token   = $this->tokenProvider?->getAccessToken();
        $headers = $this->defaultHeaders();

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
            $this->throttleAuthenticated();
        } else {
            $this->logger->warning(
                'OpenAIRE unauthenticated mode active: throttling for ' . self::UNAUTH_THROTTLE_SECONDS . 's (rate limit: 60 req/h)'
            );
            $this->throttleUnauthenticated();
        }

        $attempt = 0;
        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            try {
                $response = $this->client->get($url, [
                    'headers'         => $headers,
                    'timeout'         => 30,
                    'allow_redirects' => [
                        'max'       => 2,
                        'strict'    => true,
                        'referer'   => true,
                        'protocols' => ['https'],
                    ],
                    'verify'          => true,
                ]);

                $this->logRateLimitStatus($response);
                return $response->getBody()->getContents();
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                // 401 Unauthorized: token revoked/expired in authenticated mode -> refresh and retry once.
                if ($statusCode === 401 && $token !== null && $this->tokenProvider !== null && $attempt === 1) {
                    $this->logger->warning('OpenAIRE 401 Unauthorized received, refreshing token...');
                    $this->tokenProvider->clearTokenCache();
                    $token = $this->tokenProvider->getAccessToken();
                    if ($token !== null) {
                        $headers['Authorization'] = 'Bearer ' . $token;
                        continue;
                    }
                }

                // 429 Too Many Requests: back off and retry, up to MAX_RETRIES attempts.
                if ($statusCode === 429) {
                    if ($attempt >= self::MAX_RETRIES) {
                        $this->logger->critical(
                            "OpenAIRE API: rate limit (429) exhausted after {$attempt} attempts for paper {$paperId}, giving up"
                        );
                        return null;
                    }

                    $retryAfter   = (int) $e->getResponse()->getHeaderLine('Retry-After');
                    $sleepSeconds = $token !== null
                        ? ($retryAfter > 0 ? $retryAfter : ($attempt * 3))
                        : max(self::UNAUTH_THROTTLE_SECONDS, $retryAfter);

                    $this->logger->warning(
                        "OpenAIRE 429 Too Many Requests for paper {$paperId} (attempt {$attempt}/" . self::MAX_RETRIES . "). Waiting {$sleepSeconds}s..."
                    );
                    $this->backoff($sleepSeconds);
                    continue;
                }

                $this->logger->error("OpenAIRE API error for paper {$paperId} (HTTP {$statusCode}): " . $e->getMessage());
                return null;
            } catch (GuzzleException $e) {
                $this->logger->error("OpenAIRE API connection error for paper {$paperId}: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Log OpenAIRE rate-limit response headers (x-ratelimit-used / x-ratelimit-limit), if present.
     */
    private function logRateLimitStatus(ResponseInterface $response): void
    {
        $used  = $response->getHeaderLine('x-ratelimit-used');
        $limit = $response->getHeaderLine('x-ratelimit-limit');
        if ($used === '' || $limit === '') {
            return;
        }

        $this->logger->debug("OpenAIRE Rate Limit status: {$used}/{$limit}");
        if ((int) $limit > 0 && (int) $used > ((int) $limit * 0.85)) {
            $this->logger->warning("OpenAIRE Rate Limit threshold > 85%: {$used}/{$limit}");
        }
    }

    /**
     * Proactive throttle before an authenticated request (~2 req/s, quota 7200 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleAuthenticated(): void
    {
        usleep(self::AUTH_THROTTLE_MICROSECONDS);
    }

    /**
     * Proactive throttle before an anonymous request (1 req/min, quota 60 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleUnauthenticated(): void
    {
        sleep(self::UNAUTH_THROTTLE_SECONDS);
    }

    /**
     * Reactive backoff wait after an HTTP 429 response.
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function backoff(int $seconds): void
    {
        sleep($seconds);
    }

    // -------------------------------------------------------------------------
    // Creator extraction
    // -------------------------------------------------------------------------

    /**
     * Extract author array from an OpenAire Graph v3 response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractCreators(array $response): ?array
    {
        if (empty($response['results'][0])) {
            return null;
        }
        $authors = $response['results'][0]['authors'] ?? null;
        return !empty($authors) ? $authors : null;
    }

    // -------------------------------------------------------------------------
    // Funding extraction
    // -------------------------------------------------------------------------

    /**
     * Extract project array from an OpenAire Graph v3 response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractFunding(array $response): ?array
    {
        if (empty($response['results'][0])) {
            return null;
        }
        $projects = $response['results'][0]['projects'] ?? null;
        return !empty($projects) ? $projects : null;
    }

    // -------------------------------------------------------------------------
    // JEL classification extraction
    // -------------------------------------------------------------------------

    /**
     * Extract JEL classification codes from an OpenAire Graph v3 publication response.
     *
     * A subject is retained as a JEL code when its scheme is 'jel', or when its value
     * is prefixed with 'jel:' (some sources tag JEL values under a different scheme,
     * e.g. 'keyword').
     *
     * @param array<string, mixed> $response
     * @return array<string> unique JEL codes (e.g. "A10", "B23")
     */
    public function extractJelCodes(array $response): array
    {
        $codes = [];
        $subjects = $response['results'][0]['subjects'] ?? [];

        foreach ($subjects as $item) {
            $scheme = $item['subject']['scheme'] ?? null;
            $value  = $item['subject']['value'] ?? null;

            if ($value === null || ($scheme !== 'jel' && !str_starts_with($value, 'jel:'))) {
                continue;
            }

            $code = str_starts_with($value, 'jel:') ? substr($value, 4) : $value;
            $code = trim($code);
            if ($code !== '') {
                $codes[] = $code;
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
     * The token provider falls back to unauthenticated mode when OPENAIRE_CLIENT_ID /
     * OPENAIRE_CLIENT_SECRET are not configured.
     */
    public static function create(): self
    {
        $cacheDir = dirname(APPLICATION_PATH) . '/cache/';

        $logger = new Logger('openaire_api_client');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'openAireResearchGraph_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));

        $tokenProvider = new OpenAireTokenProvider(
            new Client(),
            new FilesystemAdapter('openAireAuthToken', self::ONE_MONTH, $cacheDir),
            $logger,
            defined('OPENAIRE_CLIENT_ID') ? (string) constant('OPENAIRE_CLIENT_ID') : null,
            defined('OPENAIRE_CLIENT_SECRET') ? (string) constant('OPENAIRE_CLIENT_SECRET') : null,
            defined('OPENAIRE_AUTH_URL') ? (string) constant('OPENAIRE_AUTH_URL') : null
        );

        return new self(
            new Client(),
            new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentAuthors',     self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentFunding',     self::ONE_MONTH, $cacheDir),
            $logger,
            $tokenProvider
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

        $authorRecords = \Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId);
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
     * Search OpenAire Graph v3 author data for a matching ORCID for the given full name.
     *
     * Matching is case-insensitive and accent-insensitive against `fullName`. The ORCID
     * is read exclusively from `pid.id.value` when `pid.id.scheme` is 'orcid' or
     * 'orcid_pending' — `id` (an OpenAire-internal hash) must never be used as an ORCID.
     *
     * @param array<int, array<string, mixed>> $apiData
     */
    public function findOrcidForAuthor(string $fullName, array $apiData): ?string
    {
        $needle = \Episciences_Tools::replaceAccents(mb_strtolower($fullName));

        foreach ($apiData as $authorInfoFromApi) {
            $candidate = \Episciences_Tools::replaceAccents(mb_strtolower($authorInfoFromApi['fullName'] ?? ''));
            if ($candidate === '' || $candidate !== $needle) {
                continue;
            }

            $scheme = $authorInfoFromApi['pid']['id']['scheme'] ?? null;
            $value  = $authorInfoFromApi['pid']['id']['value'] ?? null;

            if ($value !== null && in_array($scheme, self::ORCID_SCHEMES, true)) {
                return \Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($value);
            }
        }
        return null;
    }
}
