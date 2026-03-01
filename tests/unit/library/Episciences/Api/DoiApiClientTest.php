<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\DoiApiClient;
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
 * Unit tests for DoiApiClient.
 */
class DoiApiClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeApiClient(string $body, int $status = 200): DoiApiClient
    {
        $mock = new MockHandler([new Response($status, [], $body)]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        return new DoiApiClient($guzzle, new NullAdapter(), new NullLogger());
    }

    private function makeApiClientWithError(\Throwable $error): DoiApiClient
    {
        $mock = new MockHandler([$error]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        return new DoiApiClient($guzzle, new NullAdapter(), new NullLogger());
    }

    // -------------------------------------------------------------------------
    // fetchCsl()
    // -------------------------------------------------------------------------

    public function testFetchCsl_ValidResponse_ReturnsBody(): void
    {
        $expected = json_encode(['type' => 'article', 'title' => 'Test paper']);
        $client = $this->makeApiClient((string) $expected);

        $result = $client->fetchCsl('https://doi.org/10.1234/test');

        $this->assertSame($expected, $result);
    }

    public function testFetchCsl_EmptyBody_ReturnsEmptyString(): void
    {
        $client = $this->makeApiClient('');

        $result = $client->fetchCsl('https://doi.org/10.1234/empty');

        $this->assertSame('', $result);
    }

    public function testFetchCsl_GuzzleException_ReturnsEmptyString(): void
    {
        $error = new ConnectException('Connection refused', new Request('GET', '/'));
        $client = $this->makeApiClientWithError($error);

        $result = $client->fetchCsl('https://doi.org/10.1234/fail');

        $this->assertSame('', $result);
    }

    public function testFetchCsl_ServerError_ReturnsEmptyString(): void
    {
        // Guzzle throws ServerException for 5xx (http_errors middleware is on by default)
        // — our catch block returns '' instead of propagating the exception
        $client = $this->makeApiClient('Internal Server Error', 500);

        $result = $client->fetchCsl('https://doi.org/10.1234/server-error');

        $this->assertSame('', $result);
    }
}
