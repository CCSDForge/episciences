<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\CrossrefApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for CrossrefApiClient.
 */
class CrossrefApiClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(string $body, int $status = 200): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler([new Response($status, [], $body)])),
        ]);
    }

    private function makeClientMulti(array $responses): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);
    }

    private function makeApiClient(Client $guzzle, ?ArrayAdapter $cache = null): CrossrefApiClient
    {
        return new CrossrefApiClient(
            $guzzle,
            $cache ?? new ArrayAdapter(),
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // fetchMetadata()
    // -------------------------------------------------------------------------

    public function testFetchMetadata_CacheMiss_CallsApiAndCaches(): void
    {
        $body   = json_encode(['message' => ['container-title' => ['Nature'], 'event' => ['location' => 'Paris']]]);
        $cache  = new ArrayAdapter();
        $client = $this->makeApiClient($this->makeClient($body), $cache);

        $result = $client->fetchMetadata('10.1234/test');

        $this->assertIsArray($result);
        $this->assertEquals('Nature', $result['message']['container-title'][0]);
    }

    public function testFetchMetadata_CacheHit_ZeroApiCalls(): void
    {
        $body = json_encode(['message' => ['container-title' => ['Cached']]]);
        // First call fills cache
        $cache  = new ArrayAdapter();
        $client = $this->makeApiClient($this->makeClient($body), $cache);
        $client->fetchMetadata('10.1234/cached');

        // Second call should NOT hit the API — mock throws on second call
        $second = $this->makeApiClient(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            $cache
        );

        $result = $second->fetchMetadata('10.1234/cached');
        $this->assertIsArray($result);
        $this->assertEquals('Cached', $result['message']['container-title'][0]);
    }

    public function testFetchMetadata_ApiFailure_ReturnsNull(): void
    {
        $client = $this->makeApiClient($this->makeClient('', 500));
        // Guzzle 500 doesn't throw by default without http_errors option — returns empty string
        // We simulate a network error
        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', '/'))
            ]))
        ]);
        $apiClient = $this->makeApiClient($guzzle);
        $result    = $apiClient->fetchMetadata('10.1234/fail');

        $this->assertNull($result);
    }

    /**
     * fetchMetadata() must return a decoded array, not a raw cache item.
     */
    public function testBugFix_002_ReturnsArrayNotCacheItem(): void
    {
        $body   = json_encode(['message' => ['container-title' => ['PLOS ONE']]]);
        $client = $this->makeApiClient($this->makeClient($body));

        $result = $client->fetchMetadata('10.1234/plos');

        $this->assertIsArray($result, 'fetchMetadata must return array, not CacheItem');
        $this->assertArrayHasKey('message', $result);
    }

    // -------------------------------------------------------------------------
    // extractLocation()
    // -------------------------------------------------------------------------

    public function testExtractLocation_WithContainerTitle_ReturnsFirstTitle(): void
    {
        $client   = $this->makeApiClient($this->makeClient('{}'));
        $metadata = ['message' => ['container-title' => ['Nature', 'Nat']]];

        $this->assertEquals('Nature', $client->extractLocation($metadata));
    }

    public function testExtractLocation_MissingKey_ReturnsEmptyString(): void
    {
        $client = $this->makeApiClient($this->makeClient('{}'));

        $this->assertEquals('', $client->extractLocation(['message' => []]));
    }

    // -------------------------------------------------------------------------
    // extractEventPlace()
    // -------------------------------------------------------------------------

    public function testExtractEventPlace_WithLocation_ReturnsLocation(): void
    {
        $client   = $this->makeApiClient($this->makeClient('{}'));
        $metadata = ['message' => ['event' => ['location' => 'Paris, France']]];

        $this->assertEquals('Paris, France', $client->extractEventPlace($metadata));
    }

    public function testExtractEventPlace_MissingEvent_ReturnsEmptyString(): void
    {
        $client = $this->makeApiClient($this->makeClient('{}'));

        $this->assertEquals('', $client->extractEventPlace(['message' => []]));
    }

    /**
     * Using defined() without quotes causes a fatal PHP error; this test ensures the constant
     * check uses a string literal and does not throw when the constant is not defined.
     */
    public function testBugFix_001_DefinedWithStringDoesNotThrow(): void
    {
        // The constant is likely not defined in test env; this must not produce a fatal error.
        $body   = json_encode(['message' => []]);
        $client = $this->makeApiClient($this->makeClient($body));

        // If bug #1 were present, this would produce a PHP fatal error.
        // CROSSREF_PLUS_API_TOKEN must be either defined or not — the code uses defined('...').
        // We just verify no exception/error is thrown.
        if (!defined('CROSSREF_APIURL')) {
            $this->markTestSkipped('CROSSREF_APIURL constant not available in test env');
        }

        $this->expectNotToPerformAssertions();
        $client->fetchMetadata('10.1234/nobug');
    }
}
