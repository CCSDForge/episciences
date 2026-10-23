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

    /**
     * @return array<string, array{string}>
     */
    public static function doiSpellingsProvider(): array
    {
        return [
            'bare'            => ['10.46298/dmtcs.2450'],
            'scheme'          => ['doi:10.46298/dmtcs.2450'],
            'upper scheme'    => ['DOI:10.46298/dmtcs.2450'],
            'padded'          => ['  10.46298/dmtcs.2450 '],
            'https url'       => ['https://doi.org/10.46298/dmtcs.2450'],
            'dx url'          => ['http://dx.doi.org/10.46298/dmtcs.2450'],
        ];
    }

    /**
     * @dataProvider doiSpellingsProvider
     */
    public function testFetchCitingDois_AllSpellingsShareOneRequestAndOneCacheEntry(string $spelling): void
    {
        $container = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $handlerStack->push(\GuzzleHttp\Middleware::history($container));
        $cache  = new ArrayAdapter();
        $client = new OpenCitationsApiClient(new Client(['handler' => $handlerStack]), $cache, new NullLogger());

        $client->fetchCitingDois($spelling);

        $this->assertStringEndsWith('/doi:10.46298/dmtcs.2450', (string) $container[0]['request']->getUri());
        $this->assertTrue($cache->getItem(sha1('10.46298/dmtcs.2450') . '_citations.json')->isHit());
    }

    public function testFetchCitingDois_EncodesReservedCharactersOfDoi(): void
    {
        $container = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $handlerStack->push(\GuzzleHttp\Middleware::history($container));
        $client = new OpenCitationsApiClient(new Client(['handler' => $handlerStack]), new ArrayAdapter(), new NullLogger());

        $client->fetchCitingDois('10.1002/(SICI)1097#a?b%c');

        $uri = (string) $container[0]['request']->getUri();
        $this->assertStringEndsWith('doi:10.1002/%28SICI%291097%23a%3Fb%25c', $uri);
        $this->assertSame('', $container[0]['request']->getUri()->getFragment());
        $this->assertSame('', $container[0]['request']->getUri()->getQuery());
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
    // Robustness: invalid payloads and corrupted cache
    // -------------------------------------------------------------------------

    public function testFetchCitingDois_ScalarJsonPayload_ReturnsNullAndIsNotCached(): void
    {
        $cache  = new ArrayAdapter();
        $client = $this->makeClient($this->makeGuzzle('42'), $cache);

        $this->assertNull($client->fetchCitingDois('10.5678/xyz'));
        $this->assertFalse($cache->getItem(sha1('10.5678/xyz') . '_citations.json')->isHit());
    }

    public function testFetchCitingDois_InvalidJson_ReturnsNullAndIsNotCached(): void
    {
        $cache  = new ArrayAdapter();
        $client = $this->makeClient($this->makeGuzzle('{not json'), $cache);

        $this->assertNull($client->fetchCitingDois('10.5678/xyz'));
        $this->assertFalse($cache->getItem(sha1('10.5678/xyz') . '_citations.json')->isHit());
    }

    public function testFetchCitingDois_CorruptedCacheEntry_RefetchesAndOverwrites(): void
    {
        $key   = sha1('10.5678/xyz') . '_citations.json';
        $cache = new ArrayAdapter();
        $item  = $cache->getItem($key);
        $item->set('{truncated');
        $cache->save($item);

        $body   = json_encode([['citing' => 'doi:10.1234/abc']]);
        $client = $this->makeClient($this->makeGuzzle($body), $cache);

        $result = $client->fetchCitingDois('10.5678/xyz');

        $this->assertSame([['citing' => 'doi:10.1234/abc']], $result);
        $this->assertSame($body, $cache->getItem($key)->get());
    }

    public function testFetchCitingDois_ScalarCacheEntry_RefetchesInsteadOfTypeError(): void
    {
        $key   = sha1('10.5678/xyz') . '_citations.json';
        $cache = new ArrayAdapter();
        $item  = $cache->getItem($key);
        $item->set('"oops"');
        $cache->save($item);

        $client = $this->makeClient($this->makeGuzzle('[]'), $cache);

        $this->assertSame([], $client->fetchCitingDois('10.5678/xyz'));
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

    public function testExtractCitingDois_RowWithoutDoi_LogsAndSkips(): void
    {
        $logger = new class extends NullLogger {
            /** @var array<int, string> */
            public array $info = [];

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->info[] = (string) $message;
            }
        };

        $client = new OpenCitationsApiClient($this->makeGuzzle('[]'), new ArrayAdapter(), $logger);

        $this->assertSame([''], $client->extractCitingDois([['citing' => 'omid:br/0612 openalex:W123']]));
        $this->assertCount(1, $logger->info);
        $this->assertStringContainsString('openalex:W123', $logger->info[0]);
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
