<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAlexApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for OpenAlexApiClient.
 */
class OpenAlexApiClientTest extends TestCase
{
    private function makeGuzzle(string $body, int $status = 200): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler([new Response($status, [], $body)]))]);
    }

    private function makeClient(?Client $guzzle = null, ?ArrayAdapter $cache = null): OpenAlexApiClient
    {
        return new OpenAlexApiClient(
            $guzzle ?? $this->makeGuzzle('{}'),
            $cache ?? new ArrayAdapter(),
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // formatAuthors()
    // -------------------------------------------------------------------------

    public function testFormatAuthors_Empty_ReturnsEmptyString(): void
    {
        $this->assertEquals('', $this->makeClient()->formatAuthors([]));
    }

    public function testFormatAuthors_SingleAuthorNoOrcid_ReturnsName(): void
    {
        $authors = [['raw_author_name' => 'Alice Bob']];
        $this->assertEquals('Alice Bob', $this->makeClient()->formatAuthors($authors));
    }

    public function testFormatAuthors_MultipleAuthors_SemicolonSeparated(): void
    {
        $authors = [
            ['raw_author_name' => 'Alice Bob'],
            ['raw_author_name' => 'Carol Dan'],
        ];
        $this->assertEquals('Alice Bob; Carol Dan', $this->makeClient()->formatAuthors($authors));
    }

    public function testFormatAuthors_WithOrcid_StripsPrefix(): void
    {
        $authors = [[
            'raw_author_name' => 'Alice Bob',
            'author' => ['orcid' => 'https://orcid.org/0000-0001-2345-6789'],
        ]];
        $result = $this->makeClient()->formatAuthors($authors);
        $this->assertEquals('Alice Bob, 0000-0001-2345-6789', $result);
    }

    /**
     * Last author must NOT have a trailing semicolon.
     */
    public function testBugFix_004_NoTrailingSemicolon(): void
    {
        $authors = [
            ['raw_author_name' => 'First Author'],
            ['raw_author_name' => 'Last Author'],
        ];
        $result = $this->makeClient()->formatAuthors($authors);
        $this->assertStringEndsWith('Last Author', $result, 'Last author must not have trailing semicolon');
        $this->assertStringNotContainsString('Last Author;', $result);
    }

    // -------------------------------------------------------------------------
    // formatPages()
    // -------------------------------------------------------------------------

    /**
     * Null last-page must produce "fp" not "fp-".
     */
    public function testBugFix_003_NullLastPage_NoTrailingDash(): void
    {
        $result = $this->makeClient()->formatPages('10', null);
        $this->assertEquals('10', $result, 'null lp should return just fp, not "fp-"');
        $this->assertStringNotContainsString('-', $result);
    }

    public function testFormatPages_BothNull_ReturnsEmpty(): void
    {
        $this->assertEquals('', $this->makeClient()->formatPages(null, null));
    }

    public function testFormatPages_SameFirstAndLast_ReturnsSingle(): void
    {
        $this->assertEquals('10', $this->makeClient()->formatPages('10', '10'));
    }

    public function testFormatPages_DifferentPages_ReturnsDashSeparated(): void
    {
        $this->assertEquals('10-20', $this->makeClient()->formatPages('10', '20'));
    }

    // -------------------------------------------------------------------------
    // removeHalString()
    // -------------------------------------------------------------------------

    public function testRemoveHalString_UnwantedHalString_ReturnsEmpty(): void
    {
        $unwanted = 'HAL (Le Centre pour la Communication Scientifique Directe)';
        $this->assertEquals('', $this->makeClient()->removeHalString($unwanted));
    }

    public function testRemoveHalString_OtherString_Unchanged(): void
    {
        $this->assertEquals('Nature', $this->makeClient()->removeHalString('Nature'));
    }

    // -------------------------------------------------------------------------
    // resolveBestOaInfo()
    // -------------------------------------------------------------------------

    public function testResolveBestOaInfo_BestOaLocation_ReturnsBestOa(): void
    {
        $bestOa = ['source' => ['display_name' => 'PLOS ONE'], 'landing_page_url' => 'https://example.com'];
        $result = $this->makeClient()->resolveBestOaInfo(null, [], $bestOa);

        $this->assertIsArray($result);
        $this->assertEquals('PLOS ONE', $result['source_title']);
    }

    public function testResolveBestOaInfo_NoLocations_ReturnsEmptyString(): void
    {
        $result = $this->makeClient()->resolveBestOaInfo(null, [], null);
        $this->assertEquals('', $result);
    }

    // -------------------------------------------------------------------------
    // fetchMetadata() cache behaviour
    // -------------------------------------------------------------------------

    public function testFetchMetadata_CacheMiss_CallsApiAndStoresResult(): void
    {
        $body  = json_encode(['type_crossref' => 'journal-article', 'title' => 'Test']);
        $cache = new ArrayAdapter();
        $api   = $this->makeClient($this->makeGuzzle($body), $cache);

        $result = $api->fetchMetadata('10.1234/test');
        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['title']);
    }

    public function testFetchMetadata_CacheHit_NoApiCall(): void
    {
        $body  = json_encode(['title' => 'Cached Title']);
        $cache = new ArrayAdapter();

        // Populate cache
        $api1 = $this->makeClient($this->makeGuzzle($body), $cache);
        $api1->fetchMetadata('10.1234/cached');

        // Second client with empty mock — would fail if API is called
        $api2 = $this->makeClient(new Client(['handler' => HandlerStack::create(new MockHandler([]))]), $cache);
        $result = $api2->fetchMetadata('10.1234/cached');

        $this->assertIsArray($result);
        $this->assertEquals('Cached Title', $result['title']);
    }
}
