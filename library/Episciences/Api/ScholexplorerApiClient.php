<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Scholexplorer API v3 client (OpenAIRE Scholix 3.0 schema).
 *
 * @see https://graph.openaire.eu/docs/apis/scholexplorer/v3/version
 *
 * Cache namespaces:
 *  - scholexplorerLinkData  : md5($doi) . '_v3_links.json'
 *  - scholexplorerAuthToken : dedicated FilesystemAdapter pool for OpenAireTokenProvider
 *                             (same 'openaire_access_token' cache key as the OpenAIRE Graph
 *                             client, but isolated in its own pool — no collision).
 */
class ScholexplorerApiClient extends AbstractApiClient
{
    private const DEFAULT_API_URL = 'https://api.scholexplorer.openaire.eu/v3/Links';
    private const MAX_RESPONSE_SIZE = 5242880; // 5 MB
    private const PAGE_SIZE = 50; // max 100

    // Rate limits per https://graph.openaire.eu/docs/apis/terms#authentication--limits
    private const AUTH_THROTTLE_MICROSECONDS = 500000; // 500ms (~2 req/s, quota 7200 req/h, authenticated)
    private const UNAUTH_THROTTLE_SECONDS    = 60;      // 60s (1 req/min, quota 60 req/h, anonymous)
    private const MAX_RETRIES = 3;

    // Priority order for canonical identifier selection (Identifier[].IDScheme).
    private const IDENTIFIER_SCHEME_PRIORITY = ['doi', 'handle', 'swhid', 'url'];

    // Reciprocal relationship mapping applied when the connected entity was found via
    // a targetPid query (i.e. it declared our DOI as its target): the relationship name
    // carried by the Scholix record describes "entity -> our article", so it must be
    // flipped to describe "our article -> entity" before being stored.
    private const RELATIONSHIP_INVERSES = [
        'IsSupplementTo'     => 'IsSupplementedBy',
        'IsSupplementedBy'   => 'IsSupplementTo',
        'References'         => 'IsReferencedBy',
        'IsReferencedBy'     => 'References',
        'IsRelatedTo'        => 'IsRelatedTo',
    ];

    private ?OpenAireTokenProvider $tokenProvider;

    public function __construct(
        Client $client,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        ?OpenAireTokenProvider $tokenProvider = null
    ) {
        parent::__construct($client, $cache, $logger);
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
    // Links fetch (bidirectional, paginated, cached, deduplicated)
    // -------------------------------------------------------------------------

    /**
     * Fetch Scholix links for a DOI, cached in the scholexplorerLinkData pool.
     *
     * Queries sourcePid={doi}&targetType={targetType} and, unless $bidirectional is
     * false, also targetPid={doi}&sourceType={targetType}, merges both directions,
     * and deduplicates by canonical identifier (see extractCanonicalIdentifier()).
     *
     * Returns an array of link items, each shaped as:
     *   ['identifier', 'scheme', 'link', 'relationship', 'publisher', 'type', 'raw']
     *
     * @return array<int, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    public function fetchLinksForDoi(
        string $doi,
        int $docId,
        string $targetType = 'dataset',
        bool $bidirectional = true,
        bool $forceRefresh = false
    ): array {
        $cacheKey = md5($doi) . '_v3_links.json';

        if (!$forceRefresh) {
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $this->logger->info("Scholexplorer links from cache for DOI {$doi}");
                try {
                    $decoded = json_decode($cached, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $this->logger->error("Scholexplorer cache JSON decode error for DOI {$doi}: " . $e->getMessage());
                    $decoded = [];
                }
                return is_array($decoded) ? $decoded : [];
            }
        }

        $this->logger->info("Fetching Scholexplorer links for DOI {$doi}");

        $directed = array_map(
            static fn(array $item): array => ['direction' => 'sourcePid', 'item' => $item],
            $this->fetchDirection($doi, $docId, 'sourcePid', 'targetType', $targetType)
        );

        if ($bidirectional) {
            $directed = array_merge($directed, array_map(
                static fn(array $item): array => ['direction' => 'targetPid', 'item' => $item],
                $this->fetchDirection($doi, $docId, 'targetPid', 'sourceType', $targetType)
            ));
        }

        $unique = $this->deduplicateLinks($directed);

        $this->saveToCache($cacheKey, json_encode($unique, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $unique;
    }

    /**
     * Fetch every page of Scholix "result" records for one query direction.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchDirection(
        string $doi,
        int $docId,
        string $pidParam,
        string $typeParam,
        string $entityType
    ): array {
        $items = [];
        $page = 0;
        $totalPages = 1;

        do {
            $url  = $this->buildUrl($doi, $pidParam, $typeParam, $entityType, $page);
            $body = $this->requestWithRetry($url, $docId);

            if ($body === null) {
                break;
            }

            if (strlen($body) > self::MAX_RESPONSE_SIZE) {
                $this->logger->error(sprintf(
                    'Scholexplorer response too large for DOI %s (%d bytes)',
                    $doi,
                    strlen($body)
                ));
                break;
            }

            try {
                $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->error(sprintf(
                    'JSON decode error for paper %d (Scholexplorer): %s',
                    $docId,
                    $e->getMessage()
                ));
                break;
            }

            $pageItems = $decoded['result'] ?? [];
            if (!is_array($pageItems)) {
                break;
            }

            $items = array_merge($items, $pageItems);
            $totalPages = max(1, (int) ($decoded['totalPages'] ?? 1));
            $page++;
        } while ($page < $totalPages);

        return $items;
    }

    private function buildUrl(string $doi, string $pidParam, string $typeParam, string $entityType, int $page): string
    {
        // @phpstan-ignore notIdentical.alwaysTrue
        $apiBaseUrl = (defined('SCHOLEXPLORER_API_URL') && (string) constant('SCHOLEXPLORER_API_URL') !== '')
            ? (string) constant('SCHOLEXPLORER_API_URL')
            : self::DEFAULT_API_URL;

        $query = http_build_query([
            $pidParam  => $doi,
            $typeParam => $entityType,
            'page'     => $page,
            'size'     => self::PAGE_SIZE,
        ]);

        return rtrim($apiBaseUrl, '/') . '?' . $query;
    }

    /**
     * Merge the sourcePid/targetPid result sets, keeping a single entry per connected
     * entity (keyed by "{type}:{canonical identifier}") and harmonizing the relationship
     * name to always describe "our article -> connected entity".
     *
     * @param array<int, array{direction: string, item: array<string, mixed>}> $directedItems
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateLinks(array $directedItems): array
    {
        $unique = [];

        foreach ($directedItems as $directed) {
            $direction = $directed['direction'];
            $item      = $directed['item'];

            $connectedEntity = $direction === 'sourcePid' ? ($item['target'] ?? null) : ($item['source'] ?? null);
            if (!is_array($connectedEntity)) {
                continue;
            }

            $identifiers = $connectedEntity['Identifier'] ?? [];
            $canonical   = $this->extractCanonicalIdentifier(is_array($identifiers) ? $identifiers : []);
            if ($canonical === null) {
                continue;
            }

            $entityType = strtolower((string) ($connectedEntity['Type'] ?? 'dataset'));
            $uniqueKey  = $entityType . ':' . $canonical['id'];

            if (isset($unique[$uniqueKey])) {
                continue;
            }

            $relationshipName = $item['RelationshipType']['Name'] ?? 'IsRelatedTo';
            if ($direction === 'targetPid') {
                $relationshipName = self::RELATIONSHIP_INVERSES[$relationshipName] ?? $relationshipName;
            }

            $publisher = $connectedEntity['Publisher'][0]['name'] ?? null;

            $unique[$uniqueKey] = [
                'identifier'   => $canonical['id'],
                'scheme'       => $canonical['scheme'],
                'link'         => $canonical['url'],
                'relationship' => $relationshipName,
                'publisher'    => is_string($publisher) ? $publisher : null,
                'type'         => $entityType,
                'raw'          => $connectedEntity,
            ];
        }

        return array_values($unique);
    }

    // -------------------------------------------------------------------------
    // Canonical identifier extraction & normalization
    // -------------------------------------------------------------------------

    /**
     * Select the canonical persistent identifier for a Scholix entity, following the
     * priority order doi > handle > swhid > url. OpenAIRE-internal identifiers (any
     * other IDScheme) are never eligible.
     *
     * @param array<int, mixed> $identifiers Decoded JSON from an external API: element shape is not guaranteed.
     * @return array{id: string, scheme: string, url: ?string}|null
     */
    public function extractCanonicalIdentifier(array $identifiers): ?array
    {
        foreach (self::IDENTIFIER_SCHEME_PRIORITY as $priorityScheme) {
            foreach ($identifiers as $identifier) {
                if (!is_array($identifier)) {
                    continue;
                }

                $scheme = strtolower((string) ($identifier['IDScheme'] ?? ''));
                $id     = $identifier['ID'] ?? null;

                if ($scheme !== $priorityScheme || !is_string($id) || $id === '') {
                    continue;
                }

                $url = $identifier['IDURL'] ?? null;

                return [
                    'id'     => $this->normalizeIdentifier($id),
                    'scheme' => $priorityScheme,
                    'url'    => is_string($url) && $url !== '' ? $url : null,
                ];
            }
        }

        return null;
    }

    private function normalizeIdentifier(string $id): string
    {
        $id = trim($id);
        foreach (['https://doi.org/', 'http://dx.doi.org/', 'https://dx.doi.org/'] as $prefix) {
            if (stripos($id, $prefix) === 0) {
                $id = substr($id, strlen($prefix));
                break;
            }
        }
        return strtolower($id);
    }

    // -------------------------------------------------------------------------
    // CSL-JSON fallback (used when Crossref/DataCite metadata is unavailable)
    // -------------------------------------------------------------------------

    /**
     * Build a synthetic CSL-JSON structure directly from a Scholix entity (source or
     * target), used as a resilient fallback when Episciences_DoiTools::getMetadataFromDoi()
     * fails (network error, rate limit, non-DOI identifier).
     *
     * @param array<string, mixed> $entity
     * @return array<string, mixed>
     */
    public function buildFallbackCsl(array $entity, string $relationshipName): array
    {
        $csl = [
            'type' => strtolower((string) ($entity['Type'] ?? 'dataset')),
        ];

        $title = $entity['Title'] ?? null;
        if (is_string($title) && $title !== '') {
            $csl['title'] = $title;
        }

        $authors = $this->buildCslAuthors(is_array($entity['Creator'] ?? null) ? $entity['Creator'] : []);
        if (!empty($authors)) {
            $csl['author'] = $authors;
        }

        $year = $this->extractYear((string) ($entity['PublicationDate'] ?? ''));
        if ($year !== null) {
            $csl['issued'] = ['date-parts' => [[$year]]];
        }

        $publisherName = $entity['Publisher'][0]['name'] ?? null;
        if (is_string($publisherName) && $publisherName !== '') {
            $csl['publisher'] = $publisherName;
        }

        $identifiers = is_array($entity['Identifier'] ?? null) ? $entity['Identifier'] : [];
        $canonical   = $this->extractCanonicalIdentifier($identifiers);
        if ($canonical !== null) {
            if ($canonical['scheme'] === 'doi') {
                $csl['DOI'] = $canonical['id'];
            }
            if ($canonical['url'] !== null) {
                $csl['URL'] = $canonical['url'];
            }
        }

        return $csl;
    }

    /**
     * @param array<int, mixed> $creators
     * @return array<int, array<string, string>>
     */
    private function buildCslAuthors(array $creators): array
    {
        $authors = [];

        foreach ($creators as $creator) {
            if (!is_array($creator) || empty($creator['name']) || !is_string($creator['name'])) {
                continue;
            }

            $author = $this->splitCreatorName($creator['name']);

            foreach ((array) ($creator['identifier'] ?? []) as $creatorIdentifier) {
                if (!is_array($creatorIdentifier)) {
                    continue;
                }
                $idScheme = strtolower((string) ($creatorIdentifier['IDScheme'] ?? ''));
                $idValue  = $creatorIdentifier['ID'] ?? null;
                if ($idScheme === 'orcid' && is_string($idValue) && $idValue !== '') {
                    $author['ORCID'] = str_starts_with($idValue, 'http')
                        ? $idValue
                        : 'https://orcid.org/' . $idValue;
                }
            }

            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * @return array<string, string>
     */
    private function splitCreatorName(string $name): array
    {
        if (str_contains($name, ',')) {
            [$family, $given] = array_map('trim', explode(',', $name, 2));
            return $given !== '' ? ['family' => $family, 'given' => $given] : ['family' => $family];
        }

        return ['literal' => $name];
    }

    private function extractYear(string $date): ?int
    {
        if (preg_match('/(\d{4})/', $date, $matches) === 1) {
            return (int) $matches[1];
        }
        return null;
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
                'Scholexplorer unauthenticated mode active: throttling for ' . self::UNAUTH_THROTTLE_SECONDS . 's (rate limit: 60 req/h)'
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
                    $this->logger->warning('Scholexplorer 401 Unauthorized received, refreshing token...');
                    $this->tokenProvider->clearTokenCache();
                    $token = $this->tokenProvider->getAccessToken();
                    if ($token !== null) {
                        $headers['Authorization'] = 'Bearer ' . $token;
                        continue;
                    }
                }

                // 429 Too Many Requests: back off and retry, up to MAX_RETRIES attempts.
                if ($statusCode === 429) {
                    // Never block the request thread: enrichment:links only runs in CLI,
                    // but this seam matches OpenAireApiClient's guard for consistency and safety.
                    if (!$this->isCliContext()) {
                        $this->logger->warning(
                            "Scholexplorer 429 Too Many Requests for paper {$paperId}; not retrying outside CLI execution (would block the request)"
                        );
                        return null;
                    }

                    if ($attempt >= self::MAX_RETRIES) {
                        $this->logger->critical(
                            "Scholexplorer API: rate limit (429) exhausted after {$attempt} attempts for paper {$paperId}, giving up"
                        );
                        return null;
                    }

                    $retryAfter   = (int) $e->getResponse()->getHeaderLine('Retry-After');
                    $sleepSeconds = $token !== null
                        ? ($retryAfter > 0 ? $retryAfter : ($attempt * 3))
                        : max(self::UNAUTH_THROTTLE_SECONDS, $retryAfter);

                    $this->logger->warning(
                        "Scholexplorer 429 Too Many Requests for paper {$paperId} (attempt {$attempt}/" . self::MAX_RETRIES . "). Waiting {$sleepSeconds}s..."
                    );
                    $this->backoff($sleepSeconds);
                    continue;
                }

                $this->logger->error("Scholexplorer API error for paper {$paperId} (HTTP {$statusCode}): " . $e->getMessage());
                return null;
            } catch (GuzzleException $e) {
                $this->logger->error("Scholexplorer API connection error for paper {$paperId}: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Log Scholexplorer rate-limit response headers (x-ratelimit-used / x-ratelimit-limit), if present.
     */
    private function logRateLimitStatus(ResponseInterface $response): void
    {
        $used  = $response->getHeaderLine('x-ratelimit-used');
        $limit = $response->getHeaderLine('x-ratelimit-limit');
        if ($used === '' || $limit === '') {
            return;
        }

        $this->logger->debug("Scholexplorer Rate Limit status: {$used}/{$limit}");
        if ((int) $limit > 0 && (int) $used > ((int) $limit * 0.85)) {
            $this->logger->warning("Scholexplorer Rate Limit threshold > 85%: {$used}/{$limit}");
        }
    }

    /**
     * Proactive throttle before an authenticated request (~2 req/s, quota 7200 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleAuthenticated(): void
    {
        if (!$this->isCliContext()) {
            $this->logger->debug('Scholexplorer authenticated throttle skipped outside CLI execution');
            return;
        }
        usleep(self::AUTH_THROTTLE_MICROSECONDS);
    }

    /**
     * Proactive throttle before an anonymous request (1 req/min, quota 60 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleUnauthenticated(): void
    {
        if (!$this->isCliContext()) {
            $this->logger->debug('Scholexplorer unauthenticated throttle skipped outside CLI execution');
            return;
        }
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

    /**
     * True when running under the CLI SAPI (batch commands), false for an HTTP request.
     * Extracted as an overridable seam so tests can simulate the HTTP path.
     */
    protected function isCliContext(): bool
    {
        return PHP_SAPI === 'cli';
    }

    // -------------------------------------------------------------------------
    // Static factory
    // -------------------------------------------------------------------------

    /**
     * Build a production-ready instance using FilesystemAdapter caches and a file logger.
     *
     * A Scholexplorer-specific client ID/secret/auth URL (config/pwd.json "SCHOLEXPLORER")
     * is used when configured, so that batch enrichment does not consume the OpenAIRE
     * Research Graph quota; otherwise falls back to the OPENAIRE_* credentials, and
     * finally to unauthenticated mode.
     *
     * Constants APPLICATION_PATH must be defined by the bootstrap.
     */
    public static function create(?OpenAireTokenProvider $tokenProvider = null, ?LoggerInterface $logger = null): self
    {
        $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
        $logger   = $logger ?? new Logger('scholexplorer_api_client');

        if ($tokenProvider === null) {
            // @phpstan-ignore notIdentical.alwaysTrue
            $clientId = defined('SCHOLEXPLORER_CLIENT_ID') && (string) constant('SCHOLEXPLORER_CLIENT_ID') !== ''
                ? (string) constant('SCHOLEXPLORER_CLIENT_ID')
                // @phpstan-ignore notIdentical.alwaysTrue
                : (defined('OPENAIRE_CLIENT_ID') && (string) constant('OPENAIRE_CLIENT_ID') !== '' ? (string) constant('OPENAIRE_CLIENT_ID') : null);

            // @phpstan-ignore notIdentical.alwaysTrue
            $clientSecret = defined('SCHOLEXPLORER_CLIENT_SECRET') && (string) constant('SCHOLEXPLORER_CLIENT_SECRET') !== ''
                ? (string) constant('SCHOLEXPLORER_CLIENT_SECRET')
                // @phpstan-ignore notIdentical.alwaysTrue
                : (defined('OPENAIRE_CLIENT_SECRET') && (string) constant('OPENAIRE_CLIENT_SECRET') !== '' ? (string) constant('OPENAIRE_CLIENT_SECRET') : null);

            // @phpstan-ignore notIdentical.alwaysTrue
            $authUrl = defined('SCHOLEXPLORER_AUTH_URL') && (string) constant('SCHOLEXPLORER_AUTH_URL') !== ''
                ? (string) constant('SCHOLEXPLORER_AUTH_URL')
                // @phpstan-ignore notIdentical.alwaysTrue
                : (defined('OPENAIRE_AUTH_URL') && (string) constant('OPENAIRE_AUTH_URL') !== '' ? (string) constant('OPENAIRE_AUTH_URL') : 'https://aai.openaire.eu/oidc/token');

            $tokenProvider = new OpenAireTokenProvider(
                new Client(),
                new FilesystemAdapter('scholexplorerAuthToken', self::ONE_MONTH, $cacheDir),
                $logger,
                $clientId,
                $clientSecret,
                $authUrl
            );
        }

        return new self(
            new Client(),
            new FilesystemAdapter('scholexplorerLinkData', self::ONE_MONTH, $cacheDir),
            $logger,
            $tokenProvider
        );
    }
}
