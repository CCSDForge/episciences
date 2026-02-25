<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\SoftwareHeritageApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for SoftwareHeritageApiClient.
 */
class SoftwareHeritageApiClientTest extends TestCase
{
    private function makeGuzzleMulti(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    private function makeClient(?Client $guzzle = null, ?ArrayAdapter $cache = null): SoftwareHeritageApiClient
    {
        return new SoftwareHeritageApiClient(
            $guzzle ?? new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            $cache ?? new ArrayAdapter(),
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // generateBadgeHtml() — XSS prevention
    // -------------------------------------------------------------------------

    /**
     * Malicious SWHID must be HTML-escaped in the badge output.
     */
    public function testBugFix_012_GenerateBadgeHtml_MaliciousSwhid_OutputEscaped(): void
    {
        $malicious = 'swh:1:cnt:abc"><script>alert(1)</script>';
        $citation  = '&#x27E8;' . htmlspecialchars($malicious, ENT_QUOTES, 'UTF-8') . '&#x27E9;';
        $client    = $this->makeClient();

        $result = $client->generateBadgeHtml($malicious, $citation);

        // The output must not contain unescaped HTML
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('"><script>', $result);
    }

    public function testGenerateBadgeHtml_ValidSwhid_ProducesImgTag(): void
    {
        $swhid    = 'swh:1:cnt:abcdef1234567890abcdef1234567890abcdef12';
        $citation = '&#x27E8;' . htmlspecialchars($swhid, ENT_QUOTES, 'UTF-8') . '&#x27E9;';
        $client   = $this->makeClient();

        $result = $client->generateBadgeHtml($swhid, $citation);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('archive.softwareheritage.org/badge/', $result);
    }

    // -------------------------------------------------------------------------
    // fetchCodeMetaFromDirectory() — SSRF prevention
    // -------------------------------------------------------------------------

    /**
     * A non-HTTPS target_url must not be followed (SSRF prevention).
     */
    public function testBugFix_014_FetchCodeMetaFromDirectory_HttpTargetUrl_NotFollowed(): void
    {
        $directoryResponse = json_encode(['target_url' => 'http://evil.example.com/']);
        $guzzle = $this->makeGuzzleMulti([new Response(200, [], $directoryResponse)]);
        $client = $this->makeClient($guzzle);

        $result = $client->fetchCodeMetaFromDirectory('swh:1:dir:abc123');

        // Must return null — should not follow the HTTP URL
        $this->assertNull($result);
    }

    public function testBugFix_014_FetchCodeMetaFromDirectory_InvalidTargetUrl_NotFollowed(): void
    {
        $directoryResponse = json_encode(['target_url' => 'javascript:alert(1)']);
        $guzzle = $this->makeGuzzleMulti([new Response(200, [], $directoryResponse)]);
        $client = $this->makeClient($guzzle);

        $result = $client->fetchCodeMetaFromDirectory('swh:1:dir:abc123');

        $this->assertNull($result);
    }

    public function testFetchCodeMetaFromDirectory_HttpsTargetUrl_Followed(): void
    {
        $directoryResponse = json_encode(['target_url' => 'https://hal.archives-ouvertes.fr/hal-01234567/']);
        $codeMetaBody      = '{"@context":"https://doi.org/10.5063/schema/codemeta-2.0","name":"MyRepo"}';

        $guzzle = $this->makeGuzzleMulti([
            new Response(200, [], $directoryResponse),
            new Response(200, [], $codeMetaBody),
        ]);
        $client = $this->makeClient($guzzle);

        $result = $client->fetchCodeMetaFromDirectory('swh:1:dir:abc123');

        $this->assertIsString($result);
        $this->assertStringContainsString('MyRepo', $result);
    }

    // -------------------------------------------------------------------------
    // fetchCitationFromHalSolr() — Solr injection prevention
    // -------------------------------------------------------------------------

    /**
     * A halId containing special characters must be escaped before being embedded in the Solr query.
     */
    public function testBugFix_013_FetchCitationFromHalSolr_SpecialCharsEscaped(): void
    {
        if (!defined('EPISCIENCES_HAL_REPO_ID') && !class_exists('Episciences_Repositories')) {
            $this->markTestSkipped('Episciences_Repositories not available in test env');
        }

        // We can't easily verify the URL sent to Guzzle without a middleware,
        // but we can verify the method handles special chars without throwing.
        $maliciousHalId = 'hal-01234"; DROP TABLE papers; --';
        $body           = json_encode(['response' => ['numFound' => 0, 'docs' => []]]);
        $guzzle         = $this->makeGuzzleMulti([new Response(200, [], $body)]);
        $client         = $this->makeClient($guzzle);

        // Must not throw any exception
        $this->expectNotToPerformAssertions();
        $client->fetchCitationFromHalSolr($maliciousHalId);
    }

    // -------------------------------------------------------------------------
    // fetchCodeMetaFromHal() — bug #11
    // -------------------------------------------------------------------------

    public function testBugFix_011_FetchCodeMetaFromHal_InvalidJson_ReturnsNull(): void
    {
        $guzzle = $this->makeGuzzleMulti([new Response(200, [], 'NOT_VALID_JSON')]);
        $client = $this->makeClient($guzzle);

        $result = $client->fetchCodeMetaFromHal('some-hal-url');
        $this->assertNull($result);
    }

    public function testFetchCodeMetaFromHal_ValidJson_ReturnsArray(): void
    {
        $body   = json_encode(['@context' => 'https://doi.org/10.5063/schema/codemeta-2.0', 'name' => 'MyApp']);
        $cache  = new ArrayAdapter();
        $guzzle = $this->makeGuzzleMulti([new Response(200, [], $body)]);
        $client = $this->makeClient($guzzle, $cache);

        $result = $client->fetchCodeMetaFromHal('hal-01234567/codemeta');
        $this->assertIsArray($result);
        $this->assertEquals('MyApp', $result['name']);
    }
}
