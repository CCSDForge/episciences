<?php

namespace unit\library\Episciences;

use Episciences\Api\DoiApiClient;
use Episciences_DoiTools;
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
    protected function tearDown(): void
    {
        // Reset the static client singleton between tests
        Episciences_DoiTools::setClient(null);
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
