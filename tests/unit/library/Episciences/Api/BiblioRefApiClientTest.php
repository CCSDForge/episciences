<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\BiblioRefApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * Unit tests for BiblioRefApiClient.
 */
class BiblioRefApiClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeApiClient(string $body, int $status = 200, string $baseUrl = 'https://biblioref.example.com'): BiblioRefApiClient
    {
        $mock = new MockHandler([new Response($status, [], $body)]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        return new BiblioRefApiClient($guzzle, new NullAdapter(), new NullLogger(), $baseUrl);
    }

    private function makeApiClientWithError(\Throwable $error, string $baseUrl = 'https://biblioref.example.com'): BiblioRefApiClient
    {
        $mock = new MockHandler([$error]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        return new BiblioRefApiClient($guzzle, new NullAdapter(), new NullLogger(), $baseUrl);
    }

    private function makeCitationBody(array $refs): string
    {
        $citations = array_map(
            static fn(array $ref): array => ['ref' => json_encode($ref)],
            $refs
        );
        return (string) json_encode($citations);
    }

    // -------------------------------------------------------------------------
    // fetchBibRef() — URL validation (SSRF prevention)
    // -------------------------------------------------------------------------

    public function testFetchBibRef_NonUrlString_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->fetchBibRef('not-a-url'));
    }

    public function testFetchBibRef_FileScheme_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->fetchBibRef('file:///etc/passwd'));
    }

    public function testFetchBibRef_FtpScheme_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->fetchBibRef('ftp://internal.host/secret'));
    }

    public function testFetchBibRef_EmptyString_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->fetchBibRef(''));
    }

    // -------------------------------------------------------------------------
    // fetchBibRef() — missing configuration
    // -------------------------------------------------------------------------

    public function testFetchBibRef_EmptyBaseUrl_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('', 200, '');
        $this->assertSame([], $client->fetchBibRef('https://example.com/doc/1/pdf'));
    }

    // -------------------------------------------------------------------------
    // fetchBibRef() — HTTP layer
    // -------------------------------------------------------------------------

    public function testFetchBibRef_GuzzleException_ReturnsEmpty(): void
    {
        $error = new ConnectException('Connection refused', new Request('GET', '/'));
        $client = $this->makeApiClientWithError($error);
        $this->assertSame([], $client->fetchBibRef('https://example.com/doc/1/pdf'));
    }

    public function testFetchBibRef_EmptyBody_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->fetchBibRef('https://example.com/doc/1/pdf'));
    }

    public function testFetchBibRef_ValidResponse_ReturnsParsedCitations(): void
    {
        $body = $this->makeCitationBody([
            ['raw_reference' => 'Smith et al. (2022)', 'doi' => '10.1234/abc'],
        ]);
        $client = $this->makeApiClient($body);
        $result = $client->fetchBibRef('https://example.com/doc/1/pdf');

        $this->assertCount(1, $result);
        $this->assertSame('Smith et al. (2022)', $result[0]['unstructured_citation']);
        $this->assertSame('10.1234/abc', $result[0]['doi']);
    }

    public function testFetchBibRef_ApiErrorResponse_ReturnsEmpty(): void
    {
        $body = (string) json_encode(['message' => 'Internal error']);
        $client = $this->makeApiClient($body);
        $this->assertSame([], $client->fetchBibRef('https://example.com/doc/1/pdf'));
    }

    // -------------------------------------------------------------------------
    // parseResponse() — JSON parsing errors
    // -------------------------------------------------------------------------

    public function testParseResponse_InvalidJson_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->parseResponse('{invalid json'));
    }

    public function testParseResponse_NonArrayJson_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->parseResponse('"just a string"'));
    }

    // -------------------------------------------------------------------------
    // parseResponse() — API-level error guard
    // -------------------------------------------------------------------------

    public function testParseResponse_MessageKey_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $body = (string) json_encode(['message' => 'some error']);
        $this->assertSame([], $client->parseResponse($body));
    }

    public function testParseResponse_EmptyArray_ReturnsEmpty(): void
    {
        $client = $this->makeApiClient('');
        $this->assertSame([], $client->parseResponse('[]'));
    }

    // -------------------------------------------------------------------------
    // parseResponse() — citation field mapping
    // -------------------------------------------------------------------------

    public function testParseResponse_RawReference_MapsToUnstructuredCitation(): void
    {
        $client = $this->makeApiClient('');
        $body = $this->makeCitationBody([['raw_reference' => 'Smith et al. (2022)']]);
        $result = $client->parseResponse($body);

        $this->assertCount(1, $result);
        $this->assertSame('Smith et al. (2022)', $result[0]['unstructured_citation']);
    }

    public function testParseResponse_DoiPresent_IncludesDoi(): void
    {
        $client = $this->makeApiClient('');
        $body = $this->makeCitationBody([['raw_reference' => 'Title', 'doi' => '10.1234/abc']]);
        $result = $client->parseResponse($body);

        $this->assertSame('10.1234/abc', $result[0]['doi']);
    }

    public function testParseResponse_DoiAbsent_KeyMissing(): void
    {
        $client = $this->makeApiClient('');
        $body = $this->makeCitationBody([['raw_reference' => 'Title without DOI']]);
        $result = $client->parseResponse($body);

        $this->assertArrayNotHasKey('doi', $result[0]);
    }

    public function testParseResponse_CslPresent_IncludesCsl(): void
    {
        $client = $this->makeApiClient('');
        $csl = ['type' => 'article', 'title' => 'Test Article'];
        $body = (string) json_encode([
            ['ref' => json_encode(['raw_reference' => 'Title']), 'csl' => $csl],
        ]);
        $result = $client->parseResponse($body);

        $this->assertSame($csl, $result[0]['csl']);
    }

    // -------------------------------------------------------------------------
    // parseResponse() — resilience / skip-on-error
    // -------------------------------------------------------------------------

    public function testParseResponse_MissingRefField_CitationSkipped(): void
    {
        $client = $this->makeApiClient('');
        $body = (string) json_encode([
            ['other_field' => 'data'],
            ['ref' => json_encode(['raw_reference' => 'Valid citation'])],
        ]);
        $result = $client->parseResponse($body);

        $this->assertCount(1, $result);
        $this->assertSame('Valid citation', $result[0]['unstructured_citation']);
    }

    public function testParseResponse_InvalidRefJson_CitationSkipped(): void
    {
        $client = $this->makeApiClient('');
        $body = (string) json_encode([
            ['ref' => '{broken json'],
            ['ref' => json_encode(['raw_reference' => 'Valid citation'])],
        ]);
        $result = $client->parseResponse($body);

        $this->assertCount(1, $result);
        $this->assertSame('Valid citation', $result[0]['unstructured_citation']);
    }

    public function testParseResponse_NonArrayRef_CitationSkipped(): void
    {
        $client = $this->makeApiClient('');
        $body = (string) json_encode([
            ['ref' => '"a plain string"'],
            ['ref' => json_encode(['raw_reference' => 'Valid'])],
        ]);
        $result = $client->parseResponse($body);

        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // parseResponse() — multiple citations
    // -------------------------------------------------------------------------

    public function testParseResponse_MultipleCitations_ReturnsAll(): void
    {
        $client = $this->makeApiClient('');
        $body = $this->makeCitationBody([
            ['raw_reference' => 'First'],
            ['raw_reference' => 'Second', 'doi' => '10.1/x'],
            ['raw_reference' => 'Third'],
        ]);
        $result = $client->parseResponse($body);

        $this->assertCount(3, $result);
        $this->assertSame('First', $result[0]['unstructured_citation']);
        $this->assertSame('10.1/x', $result[1]['doi']);
        $this->assertSame('Third', $result[2]['unstructured_citation']);
    }

    public function testParseResponse_MixedValidAndInvalid_SkipsInvalid(): void
    {
        $client = $this->makeApiClient('');
        $body = (string) json_encode([
            ['ref' => '{bad'],
            ['ref' => json_encode(['raw_reference' => 'Good 1'])],
            ['other' => 'no ref key'],
            ['ref' => json_encode(['raw_reference' => 'Good 2', 'doi' => '10.2/y'])],
        ]);
        $result = $client->parseResponse($body);

        $this->assertCount(2, $result);
        $this->assertSame('Good 1', $result[0]['unstructured_citation']);
        $this->assertSame('10.2/y', $result[1]['doi']);
    }
}
