<?php

namespace unit\library\Episciences;

use Episciences\Api\DoiApiClient;
use Episciences_DoiTools;
use Episciences_Repositories;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * Unit tests for Episciences_DoiTools pure utility methods and facade.
 */
class Episciences_DoiToolsTest extends TestCase
{
    protected function setUp(): void
    {
        // Pre-populate the Repositories cache with the arXiv prefix so that
        // normalizeArxivDoi() does not require a database connection.
        $ref = new \ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $ref->setAccessible(true);
        $ref->setValue(null, [
            Episciences_Repositories::ARXIV_REPO_ID => [
                Episciences_Repositories::REPO_DOI_PREFIX => '10.48550',
                Episciences_Repositories::REPO_TYPE       => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_LABEL      => 'ArXiv',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Reset the static client singleton between tests
        Episciences_DoiTools::setClient(null);

        // Reset the Repositories cache
        $ref = new \ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    // -------------------------------------------------------------------------
    // checkIfDomainExist()
    // -------------------------------------------------------------------------

    public function testCheckIfDomainExist_DoiOrgPrefix_ReturnsTrue(): void
    {
        $this->assertTrue(Episciences_DoiTools::checkIfDomainExist('https://doi.org/10.1234/abc'));
    }

    public function testCheckIfDomainExist_DxDoiOrgPrefix_ReturnsTrue(): void
    {
        $this->assertTrue(Episciences_DoiTools::checkIfDomainExist('https://dx.doi.org/10.1234/abc'));
    }

    public function testCheckIfDomainExist_BareDoiNoPrefix_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_DoiTools::checkIfDomainExist('10.1234/abc'));
    }

    public function testCheckIfDomainExist_EmptyString_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_DoiTools::checkIfDomainExist(''));
    }

    public function testCheckIfDomainExist_HttpNotHttps_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_DoiTools::checkIfDomainExist('http://doi.org/10.1234/abc'));
    }

    public function testCheckIfDomainExist_OtherUrl_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_DoiTools::checkIfDomainExist('https://example.com/10.1234/abc'));
    }

    // -------------------------------------------------------------------------
    // cleanDoi()
    // -------------------------------------------------------------------------

    public function testCleanDoi_DoiOrgPrefix_StripsPrefix(): void
    {
        $this->assertSame('10.1234/abc', Episciences_DoiTools::cleanDoi('https://doi.org/10.1234/abc'));
    }

    public function testCleanDoi_DxDoiOrgPrefix_StripsPrefix(): void
    {
        $this->assertSame('10.1234/abc', Episciences_DoiTools::cleanDoi('https://dx.doi.org/10.1234/abc'));
    }

    public function testCleanDoi_NoPrefix_ReturnsUnchanged(): void
    {
        $this->assertSame('10.1234/abc', Episciences_DoiTools::cleanDoi('10.1234/abc'));
    }

    public function testCleanDoi_EmptyString_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_DoiTools::cleanDoi(''));
    }

    public function testCleanDoi_DefaultParam_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_DoiTools::cleanDoi());
    }

    // -------------------------------------------------------------------------
    // normalizeArxivDoi()
    // -------------------------------------------------------------------------

    public function testNormalizeArxivDoi_SingleDigitVersion_Stripped(): void
    {
        $result = Episciences_DoiTools::normalizeArxivDoi('2301.12345v2');
        $this->assertSame('10.48550/arxiv.2301.12345', $result);
    }

    public function testNormalizeArxivDoi_MultiDigitVersion_Stripped(): void
    {
        // Regression: the old regex ~v[\d{1,100}]~ was a character class that matched
        // exactly one character, so "v10" was only partially stripped (left "0" behind).
        // The new regex ~v\d+$~i correctly strips the entire multi-digit version suffix.
        $result = Episciences_DoiTools::normalizeArxivDoi('2301.12345v10');
        $this->assertSame('10.48550/arxiv.2301.12345', $result);
    }

    public function testNormalizeArxivDoi_NoVersionSuffix_NotModified(): void
    {
        $result = Episciences_DoiTools::normalizeArxivDoi('2301.12345');
        $this->assertSame('10.48550/arxiv.2301.12345', $result);
    }

    public function testNormalizeArxivDoi_ArxivColonPrefixed_VersionStripped(): void
    {
        $result = Episciences_DoiTools::normalizeArxivDoi('arxiv:2301.12345v3');
        $this->assertSame('10.48550arxiv:2301.12345', $result);
    }

    public function testNormalizeArxivDoi_ArxivColonPrefixed_NoVersion(): void
    {
        $result = Episciences_DoiTools::normalizeArxivDoi('arxiv:2301.12345');
        $this->assertSame('10.48550arxiv:2301.12345', $result);
    }

    // -------------------------------------------------------------------------
    // getMetadataFromDoi() — non-arXiv DOI, via injected mock client
    // -------------------------------------------------------------------------

    public function testGetMetadataFromDoi_BareDoi_PrependsPrefixAndFetches(): void
    {
        $expected = json_encode(['type' => 'article', 'DOI' => '10.1234/test']);
        $mock = new MockHandler([new Response(200, [], (string) $expected)]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        Episciences_DoiTools::setClient(new DoiApiClient($guzzle, new NullAdapter(), new NullLogger()));

        $result = Episciences_DoiTools::getMetadataFromDoi('10.1234/test');

        $this->assertSame($expected, $result);
    }

    public function testGetMetadataFromDoi_AlreadyFullUrl_FetchesDirectly(): void
    {
        $expected = json_encode(['type' => 'article']);
        $mock = new MockHandler([new Response(200, [], (string) $expected)]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        Episciences_DoiTools::setClient(new DoiApiClient($guzzle, new NullAdapter(), new NullLogger()));

        $result = Episciences_DoiTools::getMetadataFromDoi('https://doi.org/10.1234/test');

        $this->assertSame($expected, $result);
    }

    public function testGetMetadataFromDoi_NetworkError_ReturnsEmptyString(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Refused', new \GuzzleHttp\Psr7\Request('GET', '/')),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        Episciences_DoiTools::setClient(new DoiApiClient($guzzle, new NullAdapter(), new NullLogger()));

        $result = Episciences_DoiTools::getMetadataFromDoi('10.1234/test');

        $this->assertSame('', $result);
    }
}
