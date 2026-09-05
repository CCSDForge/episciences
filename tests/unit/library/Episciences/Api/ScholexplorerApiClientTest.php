<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAireTokenProvider;
use Episciences\Api\ScholexplorerApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for ScholexplorerApiClient (Scholexplorer API v3 / Scholix 3.0 schema).
 */
class ScholexplorerApiClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // isAuthenticated()
    // -------------------------------------------------------------------------

    public function testIsAuthenticatedReturnsTrueWhenConfigured(): void
    {
        $client = $this->makeClient($this->makeGuzzle([]), $this->makeAuthenticatedTokenProvider());
        $this->assertTrue($client->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsFalseWhenUnconfigured(): void
    {
        $client = $this->makeClient($this->makeGuzzle([]), null);
        $this->assertFalse($client->isAuthenticated());

        $client = $this->makeClient($this->makeGuzzle([]), $this->makeUnconfiguredTokenProvider());
        $this->assertFalse($client->isAuthenticated());
    }

    // -------------------------------------------------------------------------
    // requestWithRetry() (exercised through fetchLinksForDoi()) — bi-mode auth
    // -------------------------------------------------------------------------

    public function testRequestSendsBearerTokenWhenAuthenticated(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], $this->scholixPage([], 1))], $history);

        $client = $this->makeClient($guzzle, $this->makeAuthenticatedTokenProvider('the-bearer-token'));
        $client->fetchLinksForDoi('10.1234/authed', 1, 'dataset', false);

        $this->assertCount(1, $history);
        $this->assertSame('Bearer the-bearer-token', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testRequestOmitsAuthorizationHeaderWhenUnauthenticated(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], $this->scholixPage([], 1))], $history);

        $client = $this->makeClient($guzzle, null);
        $client->fetchLinksForDoi('10.1234/anon', 1, 'dataset', false);

        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    // -------------------------------------------------------------------------
    // requestWithRetry() — HTTP 401 retry
    // -------------------------------------------------------------------------

    public function testRequestRetriesOn401AndRefreshesToken(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([
            new Response(401, [], 'Unauthorized'),
            new Response(200, [], $this->scholixPage([], 1)),
        ], $history);

        // Token cache pre-seeded with a stale token; clearTokenCache() + getAccessToken()
        // must yield a (mocked) fresh one from the same cache-hit path.
        $tokenCache = new ArrayAdapter();
        $tokenItem  = $tokenCache->getItem('openaire_access_token');
        $tokenItem->set('stale-token');
        $tokenItem->expiresAfter(3600);
        $tokenCache->save($tokenItem);

        $aaiClient = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'refreshed-token', 'expires_in' => 3600])),
        ]))]);
        $tokenProvider = new OpenAireTokenProvider($aaiClient, $tokenCache, new NullLogger(), 'id', 'secret');

        $client = $this->makeClient($guzzle, $tokenProvider);
        $client->fetchLinksForDoi('10.1234/expired-token', 1, 'dataset', false);

        $this->assertCount(2, $history);
        $this->assertSame('Bearer stale-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer refreshed-token', $history[1]['request']->getHeaderLine('Authorization'));
    }

    // -------------------------------------------------------------------------
    // requestWithRetry() — HTTP 429 backoff & retry
    // -------------------------------------------------------------------------

    public function testRequestRetriesOn429WithRetryAfterHeader(): void
    {
        $guzzle = $this->makeGuzzle([
            new Response(429, ['Retry-After' => '5'], 'Too Many Requests'),
            new Response(200, [], $this->scholixPage([], 1)),
        ]);

        $client = $this->makeClient($guzzle, $this->makeAuthenticatedTokenProvider());
        $links  = $client->fetchLinksForDoi('10.1234/ratelimited', 1, 'dataset', false);

        $this->assertSame([], $links);
        $this->assertSame(['auth:500000us', 'backoff:5s'], $client->sleepLog);
    }

    public function testRequestAbortsWithoutRetryOn429WhenNonCliContext(): void
    {
        $history = [];
        // Only one response queued: a retry attempt would exhaust the mock queue and error out.
        $guzzle = $this->makeGuzzle([new Response(429, ['Retry-After' => '5'], 'Too Many Requests')], $history);

        $client = $this->makeHttpContextClient($guzzle, $this->makeAuthenticatedTokenProvider());
        $links  = $client->fetchLinksForDoi('10.1234/ratelimited-http', 1, 'dataset', false);

        $this->assertSame([], $links);
        $this->assertCount(1, $history, 'must not retry outside CLI execution');
    }

    public function testRequestExhaustsRetriesOnRepeated429(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([
            new Response(429, [], 'Too Many Requests'),
            new Response(429, [], 'Too Many Requests'),
            new Response(429, [], 'Too Many Requests'),
        ]);

        $client = $this->makeClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $links  = $client->fetchLinksForDoi('10.1234/always-ratelimited', 1, 'dataset', false);

        $this->assertSame([], $links);
        $this->assertTrue($testHandler->hasCriticalThatContains('rate limit (429) exhausted'));
    }

    // -------------------------------------------------------------------------
    // fetchLinksForDoi() — pagination (0-indexed)
    // -------------------------------------------------------------------------

    public function testFetchLinksForDoiPaginatesFromPageZero(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([
            new Response(200, [], $this->scholixPage([$this->scholixResult('10.5061/dryad.aaa')], 2)),
            new Response(200, [], $this->scholixPage([$this->scholixResult('10.5061/dryad.bbb')], 2)),
        ], $history);

        $client = $this->makeClient($guzzle, null);
        $links  = $client->fetchLinksForDoi('10.1234/paginated', 1, 'dataset', false);

        $this->assertCount(2, $history);

        parse_str((string) $history[0]['request']->getUri()->getQuery(), $firstQuery);
        parse_str((string) $history[1]['request']->getUri()->getQuery(), $secondQuery);
        $this->assertSame('0', $firstQuery['page']);
        $this->assertSame('1', $secondQuery['page']);

        $this->assertCount(2, $links);
    }

    // -------------------------------------------------------------------------
    // extractCanonicalIdentifier()
    // -------------------------------------------------------------------------

    public function testExtractCanonicalIdentifierPrefersDoiOverOthers(): void
    {
        $client = $this->makeClient($this->makeGuzzle([]));

        $identifiers = [
            ['ID' => '50|doi_dedup___::abcdef', 'IDScheme' => 'openaireIdentifier', 'IDURL' => null],
            ['ID' => '20.500.12345/xyz', 'IDScheme' => 'handle', 'IDURL' => 'https://hdl.handle.net/20.500.12345/xyz'],
            ['ID' => '10.5061/dryad.j2c4g/4', 'IDScheme' => 'doi', 'IDURL' => 'https://dx.doi.org/10.5061/dryad.j2c4g/4'],
        ];

        $result = $client->extractCanonicalIdentifier($identifiers);

        $this->assertSame([
            'id'     => '10.5061/dryad.j2c4g/4',
            'scheme' => 'doi',
            'url'    => 'https://dx.doi.org/10.5061/dryad.j2c4g/4',
        ], $result);
    }

    public function testExtractCanonicalIdentifierFallsBackToHandleOrUrl(): void
    {
        $client = $this->makeClient($this->makeGuzzle([]));

        $handleOnly = [
            ['ID' => '20.500.12345/xyz', 'IDScheme' => 'handle', 'IDURL' => 'https://hdl.handle.net/20.500.12345/xyz'],
            ['ID' => '50|re3data_____::abc', 'IDScheme' => 'openaireIdentifier', 'IDURL' => null],
        ];
        $result = $client->extractCanonicalIdentifier($handleOnly);
        $this->assertSame('handle', $result['scheme']);
        $this->assertSame('20.500.12345/xyz', $result['id']);

        $urlOnly = [
            ['ID' => 'https://example.org/dataset/1', 'IDScheme' => 'url', 'IDURL' => 'https://example.org/dataset/1'],
        ];
        $result = $client->extractCanonicalIdentifier($urlOnly);
        $this->assertSame('url', $result['scheme']);

        $noPublicIdentifier = [
            ['ID' => '50|re3data_____::abc', 'IDScheme' => 'openaireIdentifier', 'IDURL' => null],
        ];
        $this->assertNull($client->extractCanonicalIdentifier($noPublicIdentifier));
    }

    // -------------------------------------------------------------------------
    // fetchLinksForDoi() — bidirectional deduplication
    // -------------------------------------------------------------------------

    public function testBidirectionalDeduplicationIgnoresReciprocalDuplicates(): void
    {
        $sameDatasetIdentifiers = [
            ['ID' => '10.5061/dryad.same', 'IDScheme' => 'doi', 'IDURL' => 'https://doi.org/10.5061/dryad.same'],
        ];

        // Article is the source, dataset is the target.
        $sourcePidResult = [
            'RelationshipType' => ['Name' => 'IsSupplementTo'],
            'target'           => [
                'Identifier' => $sameDatasetIdentifiers,
                'Type'       => 'dataset',
                'Title'      => 'Same dataset',
                'Publisher'  => [['name' => 'Zenodo']],
            ],
        ];

        // Same dataset, but declared the other way round: dataset is the source, article is the target.
        $targetPidResult = [
            'RelationshipType' => ['Name' => 'IsSupplementTo'],
            'source'           => [
                'Identifier' => $sameDatasetIdentifiers,
                'Type'       => 'dataset',
                'Title'      => 'Same dataset',
                'Publisher'  => [['name' => 'Zenodo']],
            ],
        ];

        $guzzle = $this->makeGuzzle([
            new Response(200, [], $this->scholixPage([$sourcePidResult], 1)),
            new Response(200, [], $this->scholixPage([$targetPidResult], 1)),
        ]);

        $client = $this->makeClient($guzzle, null);
        $links  = $client->fetchLinksForDoi('10.1234/bidirectional', 1, 'dataset', true);

        $this->assertCount(1, $links);
        $this->assertSame('10.5061/dryad.same', $links[0]['identifier']);
    }

    // -------------------------------------------------------------------------
    // buildFallbackCsl()
    // -------------------------------------------------------------------------

    public function testBuildFallbackCslProducesValidBiblioStructure(): void
    {
        $client = $this->makeClient($this->makeGuzzle([]));

        $entity = [
            'Title'           => 'Locations of Publicly Available Data for the Cohort',
            'Type'            => 'dataset',
            'PublicationDate' => '2021-01-01',
            'Creator'         => [
                [
                    'name'       => 'Piwowar, Heather A.',
                    'identifier' => [
                        ['ID' => '0000-0003-1613-5981', 'IDScheme' => 'orcid', 'IDURL' => null],
                    ],
                ],
            ],
            'Publisher'  => [['name' => 'DRYAD']],
            'Identifier' => [
                ['ID' => '10.5061/dryad.j2c4g/4', 'IDScheme' => 'doi', 'IDURL' => 'https://dx.doi.org/10.5061/dryad.j2c4g/4'],
            ],
        ];

        $csl = $client->buildFallbackCsl($entity, 'IsSupplementTo');

        $this->assertSame('dataset', $csl['type']);
        $this->assertSame('Locations of Publicly Available Data for the Cohort', $csl['title']);
        $this->assertSame(
            [['family' => 'Piwowar', 'given' => 'Heather A.', 'ORCID' => 'https://orcid.org/0000-0003-1613-5981']],
            $csl['author']
        );
        $this->assertSame(['date-parts' => [[2021]]], $csl['issued']);
        $this->assertSame('DRYAD', $csl['publisher']);
        $this->assertSame('10.5061/dryad.j2c4g/4', $csl['DOI']);
        $this->assertSame('https://dx.doi.org/10.5061/dryad.j2c4g/4', $csl['URL']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function scholixPage(array $results, int $totalPages): string
    {
        return json_encode([
            'currentPage' => 0,
            'totalLinks'  => count($results),
            'totalPages'  => $totalPages,
            'result'      => $results,
        ]);
    }

    /** @return array<string, mixed> */
    private function scholixResult(string $doi): array
    {
        return [
            'RelationshipType' => ['Name' => 'IsRelatedTo'],
            'target'           => [
                'Identifier' => [
                    ['ID' => $doi, 'IDScheme' => 'doi', 'IDURL' => 'https://doi.org/' . $doi],
                ],
                'Type'  => 'dataset',
                'Title' => 'Dataset ' . $doi,
            ],
        ];
    }

    private function makeGuzzle(array $responses, array &$history = []): Client
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));
        return new Client(['handler' => $handlerStack]);
    }

    private function makeAuthenticatedTokenProvider(string $token = 'test-access-token'): OpenAireTokenProvider
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set($token);
        $item->expiresAfter(3600);
        $cache->save($item);

        return new OpenAireTokenProvider(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]), // never called: cache hit
            $cache,
            new NullLogger(),
            'client-id',
            'client-secret'
        );
    }

    private function makeUnconfiguredTokenProvider(): OpenAireTokenProvider
    {
        return new OpenAireTokenProvider(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            new ArrayAdapter(),
            new NullLogger(),
            '',
            ''
        );
    }

    private function makeClient(
        Client $guzzle,
        ?OpenAireTokenProvider $tokenProvider = null,
        ?Logger $logger = null
    ): ScholexplorerApiClientNoSleep {
        return new ScholexplorerApiClientNoSleep(
            $guzzle,
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $tokenProvider
        );
    }

    private function makeHttpContextClient(
        Client $guzzle,
        ?OpenAireTokenProvider $tokenProvider = null,
        ?Logger $logger = null
    ): ScholexplorerApiClientHttpContext {
        return new ScholexplorerApiClientHttpContext(
            $guzzle,
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $tokenProvider
        );
    }
}

/**
 * Test double: records throttle/backoff calls instead of actually sleeping,
 * so 60s-anonymous-mode and 429-backoff tests run instantly.
 */
class ScholexplorerApiClientNoSleep extends ScholexplorerApiClient
{
    /** @var array<int, string> */
    public array $sleepLog = [];

    protected function throttleAuthenticated(): void
    {
        $this->sleepLog[] = 'auth:500000us';
    }

    protected function throttleUnauthenticated(): void
    {
        $this->sleepLog[] = 'unauth:60s';
    }

    protected function backoff(int $seconds): void
    {
        $this->sleepLog[] = "backoff:{$seconds}s";
    }
}

/**
 * Test double: simulates the HTTP request path (non-CLI SAPI) by overriding only
 * isCliContext(), while keeping the real throttle/backoff seams inert since the 429
 * guard returns before reaching them.
 */
class ScholexplorerApiClientHttpContext extends ScholexplorerApiClient
{
    protected function isCliContext(): bool
    {
        return false;
    }

    protected function throttleAuthenticated(): void
    {
        // no-op: keep tests fast, real skip-outside-CLI logic already covered by ScholexplorerApiClientTest via isCliContext()
    }

    protected function throttleUnauthenticated(): void
    {
        // no-op
    }
}
