<?php
declare(strict_types=1);

namespace unit\library\Episciences\Api;

use Episciences\Api\CrossrefSubmissionApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for CrossrefSubmissionApiClient.
 */
class CrossrefSubmissionApiClientTest extends TestCase
{
    private const DEPOSIT_URL      = 'https://deposit.crossref.org/servlet/deposit';
    private const DEPOSIT_TEST_URL = 'https://test.crossref.org/servlet/deposit';
    private const QUERY_URL        = 'https://doi.crossref.org/servlet/submissionDownload';
    private const QUERY_TEST_URL   = 'https://test.crossref.org/servlet/submissionDownload';
    private const LOGIN            = 'test_login';
    private const PASSWORD         = 'test_password';

    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'crossref_test_') . '.xml';
        file_put_contents($this->tmpFile, '<doi_batch><batch_data><success_count>1</success_count></batch_data></doi_batch>');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param Response[] $responses */
    private function makeClient(array $responses, array &$history = []): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        return new Client(['handler' => $stack]);
    }

    private function makeApiClient(Client $client): CrossrefSubmissionApiClient
    {
        return new CrossrefSubmissionApiClient(
            $client,
            new NullLogger(),
            self::DEPOSIT_URL,
            self::DEPOSIT_TEST_URL,
            self::QUERY_URL,
            self::QUERY_TEST_URL,
            self::LOGIN,
            self::PASSWORD,
        );
    }

    // -------------------------------------------------------------------------
    // postMetadata()
    // -------------------------------------------------------------------------

    public function testPostMetadata_Success_ReturnsResponse(): void
    {
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200, [], 'OK')]));

        $response = $apiClient->postMetadata($this->tmpFile, 'epiga-42.xml', false);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostMetadata_Production_PostsToProductionUrl(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200)], $history));

        $apiClient->postMetadata($this->tmpFile, 'epiga-42.xml', false);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertSame(self::DEPOSIT_URL, $uri);
    }

    public function testPostMetadata_DryRun_PostsToTestUrl(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200)], $history));

        $apiClient->postMetadata($this->tmpFile, 'epiga-42.xml', true);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertSame(self::DEPOSIT_TEST_URL, $uri);
    }

    public function testPostMetadata_SendsMultipartWithCredentials(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200)], $history));

        $apiClient->postMetadata($this->tmpFile, 'epiga-42.xml', false);

        $body = (string) $history[0]['request']->getBody();
        $this->assertStringContainsString('doMDUpload', $body);
        $this->assertStringContainsString(self::LOGIN, $body);
        $this->assertStringContainsString(self::PASSWORD, $body);
        $this->assertStringContainsString('epiga-42.xml', $body);
    }

    public function testPostMetadata_MissingFile_ThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200)]));
        $apiClient->postMetadata('/nonexistent/path/does-not-exist.xml', 'file.xml', false);
    }

    // -------------------------------------------------------------------------
    // fetchStatus()
    // -------------------------------------------------------------------------

    public function testFetchStatus_Success_ReturnsXmlString(): void
    {
        $xml       = '<doi_batch_diagnostic><batch_data><success_count>1</success_count></batch_data></doi_batch_diagnostic>';
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200, [], $xml)]));

        $result = $apiClient->fetchStatus('epiga-42.xml', false);

        $this->assertSame($xml, $result);
    }

    public function testFetchStatus_Production_UsesProductionQueryUrl(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200, [], '')], $history));

        $apiClient->fetchStatus('epiga-42.xml', false);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringStartsWith(self::QUERY_URL, $uri);
    }

    public function testFetchStatus_DryRun_UsesTestQueryUrl(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200, [], '')], $history));

        $apiClient->fetchStatus('epiga-42.xml', true);

        $uri = (string) $history[0]['request']->getUri();
        $this->assertStringStartsWith(self::QUERY_TEST_URL, $uri);
    }

    public function testFetchStatus_QueryContainsRequiredParams(): void
    {
        $history   = [];
        $apiClient = $this->makeApiClient($this->makeClient([new Response(200, [], '')], $history));

        $apiClient->fetchStatus('epiga-42.xml', false);

        $query = $history[0]['request']->getUri()->getQuery();
        $this->assertStringContainsString('usr=' . self::LOGIN, $query);
        $this->assertStringContainsString('pwd=' . self::PASSWORD, $query);
        $this->assertStringContainsString('file_name=epiga-42.xml', $query);
        $this->assertStringContainsString('type=result', $query);
    }

    public function testFetchStatus_NetworkError_ReturnsNull(): void
    {
        $guzzle = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new ConnectException('Connection refused', new Request('GET', self::QUERY_URL)),
            ])),
        ]);
        $apiClient = $this->makeApiClient($guzzle);

        $result = $apiClient->fetchStatus('epiga-42.xml', false);

        $this->assertNull($result);
    }
}
