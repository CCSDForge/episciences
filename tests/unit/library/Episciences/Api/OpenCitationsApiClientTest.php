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
    // fetchCitingDois() — cache behaviour
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
