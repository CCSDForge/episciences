<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenCitationsApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for OpenCitationsApiClient.
 */
class OpenCitationsApiClientTest extends TestCase
{
    private function makeGuzzle(string $body, int $status = 200): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler([new Response($status, [], $body)]))]);
    }

    private function makeClient(?Client $guzzle = null, ?ArrayAdapter $cache = null): OpenCitationsApiClient
    {
        return new OpenCitationsApiClient(
            $guzzle ?? $this->makeGuzzle('[]'),
            $cache ?? new ArrayAdapter(),
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // fetchCitingDois() — cache behaviour & v2 URI formatting
    // -------------------------------------------------------------------------

    public function testFetchCitingDois_CacheMiss_CallsApi(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $body   = json_encode([['citing' => 'doi:10.1234/abc', 'cited' => 'doi:10.5678/xyz']]);
        $client = $this->makeClient($this->makeGuzzle($body));

        $result = $client->fetchCitingDois('10.5678/xyz');
        $this->assertIsArray($result);
    }

    public function testFetchCitingDois_PrefixesDoiWithScheme(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $handlerStack->push($history);
        $guzzle = new Client(['handler' => $handlerStack]);

        $client = new OpenCitationsApiClient($guzzle, new ArrayAdapter(), new NullLogger());
        $client->fetchCitingDois('10.46298/dmtcs.2450');

        $this->assertCount(1, $container);
        $requestUri = (string) $container[0]['request']->getUri();
        $this->assertStringEndsWith('doi:10.46298/dmtcs.2450', $requestUri);
    }

    public function testFetchCitingDois_PreservesExistingDoiPrefix(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $handlerStack->push($history);
        $guzzle = new Client(['handler' => $handlerStack]);

        $client = new OpenCitationsApiClient($guzzle, new ArrayAdapter(), new NullLogger());
        $client->fetchCitingDois('doi:10.46298/dmtcs.2450');

        $this->assertCount(1, $container);
        $requestUri = (string) $container[0]['request']->getUri();
        $this->assertStringEndsWith('doi:10.46298/dmtcs.2450', $requestUri);
        $this->assertStringNotContainsString('doi:doi:', $requestUri);
    }

    /**
     * An empty JSON array '[]' (0 citations) must be cached and returned as [],
     * not rejected as an empty/invalid response.
     */
    public function testBugFix_005_EmptyJsonArrayCached(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $cache  = new ArrayAdapter();
        $client = $this->makeClient($this->makeGuzzle('[]'), $cache);

        $result = $client->fetchCitingDois('10.1234/zero-citations');

        // Must return [], not null
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFetchCitingDois_CacheHit_NoApiCall(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $body  = json_encode([['citing' => 'doi:10.1234/abc', 'cited' => 'doi:10.5678/xyz']]);
        $cache = new ArrayAdapter();

        // Populate cache
        $client1 = $this->makeClient($this->makeGuzzle($body), $cache);
        $client1->fetchCitingDois('10.5678/xyz');

        // Empty mock — would fail if API called
        $client2 = $this->makeClient(new Client(['handler' => HandlerStack::create(new MockHandler([]))]), $cache);
        $result  = $client2->fetchCitingDois('10.5678/xyz');

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // Rate Limiting & 429 Retry
    // -------------------------------------------------------------------------

    public function testFetchCitingDois_429RetriesAndSucceeds(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(429, ['Retry-After' => '2']),
                new Response(200, [], json_encode([['citing' => 'doi:10.1234/abc']])),
            ])),
        ]);

        $client = new OpenCitationsApiClientNoSleep($guzzle, new ArrayAdapter(), new NullLogger());
        $result = $client->fetchCitingDois('10.5678/xyz');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(['throttle', 'backoff:2s', 'throttle'], $client->sleepLog);
    }

    public function testFetchCitingDois_429ExhaustedRetriesReturnsNull(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(429, []),
                new Response(429, []),
                new Response(429, []),
            ])),
        ]);

        $client = new OpenCitationsApiClientNoSleep($guzzle, new ArrayAdapter(), new NullLogger());
        $result = $client->fetchCitingDois('10.5678/xyz');

        $this->assertNull($result);
        $this->assertSame(['throttle', 'backoff:2s', 'throttle', 'backoff:4s', 'throttle'], $client->sleepLog);
    }

    public function testFetchCitingDois_429OutsideCliDoesNotRetry(): void
    {
        if (!defined('OPENCITATIONS_APIURL')) {
            $this->markTestSkipped('OPENCITATIONS_APIURL not defined in test env');
        }

        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(429, []),
            ])),
        ]);

        $client = new OpenCitationsApiClientHttpContext($guzzle, new ArrayAdapter(), new NullLogger());
        $result = $client->fetchCitingDois('10.5678/xyz');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // extractCitingDois()
    // -------------------------------------------------------------------------

    public function testExtractCitingDois_CleanDoi_Extracted(): void
    {
        $rows   = [['citing' => 'doi:10.1234/abc']];
        $client = $this->makeClient();

        $result = $client->extractCitingDois($rows);
        $this->assertEquals(['10.1234/abc'], $result);
    }

    public function testExtractCitingDois_WithCociPrefix_Stripped(): void
    {
        $rows   = [['citing' => 'coci => doi:10.1234/abc']];
        $client = $this->makeClient();

        $result = $client->extractCitingDois($rows);
        $this->assertEquals(['10.1234/abc'], $result);
    }

    /**
     * DOI with a semicolon suffix must be trimmed correctly.
     */
    public function testBugFix_006_SemicolonSuffixRemoved(): void
    {
        $rows   = [['citing' => 'doi:10.1234/abc; pmid:123456']];
        $client = $this->makeClient();

        $result = $client->extractCitingDois($rows);
        // The semicolon and everything after it should be stripped
        $this->assertEquals(['10.1234/abc'], $result);
    }

    /**
     * OpenCitations API v2 format with multiple space-separated identifiers
     * including omid, doi, and openalex.
     */
    public function testExtractCitingDois_ApiV2MultipleIdentifiers(): void
    {
        $rows = [
            ['citing' => 'omid:br/061102597764 doi:10.1007/978-3-319-23660-5_8 openalex:W2963950701'],
            ['citing' => 'omid:br/06023042492 arxiv:2411.03283'],
        ];
        $client = $this->makeClient();

        $result = $client->extractCitingDois($rows);
        $this->assertSame(['10.1007/978-3-319-23660-5_8', ''], $result);
    }

    public function testExtractCitingDois_NoDoi_ReturnsEmptyString(): void
    {
        $rows   = [['citing' => 'pmid:12345 pmcid:67890']];
        $client = $this->makeClient();

        $result = $client->extractCitingDois($rows);
        $this->assertEquals([''], $result);
    }

    public function testExtractCitingDois_EmptyArray_ReturnsEmpty(): void
    {
        $this->assertEquals([], $this->makeClient()->extractCitingDois([]));
    }
}

/**
 * Test double: records throttle and backoff calls instead of sleeping.
 */
class OpenCitationsApiClientNoSleep extends OpenCitationsApiClient
{
    /** @var array<int, string> */
    public array $sleepLog = [];

    protected function throttle(): void
    {
        $this->sleepLog[] = 'throttle';
    }

    protected function backoff(int $seconds): void
    {
        $this->sleepLog[] = "backoff:{$seconds}s";
    }
}

/**
 * Test double: simulates HTTP context (non-CLI).
 */
class OpenCitationsApiClientHttpContext extends OpenCitationsApiClient
{
    protected function isCliContext(): bool
    {
        return false;
    }

    protected function throttle(): void
    {
    }
}
