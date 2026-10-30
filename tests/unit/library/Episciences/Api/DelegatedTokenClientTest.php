<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\DelegatedTokenClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * Unit tests for DelegatedTokenClient.
 */
class DelegatedTokenClientTest extends TestCase
{
    private const API_URL = 'https://api.example.org/api/';
    private const SECRET = '0123456789abcdef0123456789abcdef';

    /** @var array<int, array{request: Request}> */
    private array $history = [];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function makeClient(array $responses, string $secret = self::SECRET, string $apiUrl = self::API_URL, ?\Psr\Log\LoggerInterface $logger = null): DelegatedTokenClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new DelegatedTokenClient(new Client(['handler' => $stack]), $logger ?? new NullLogger(), $apiUrl, $secret);
    }

    // -------------------------------------------------------------------------
    // buildRequest()
    // -------------------------------------------------------------------------

    public function testBuildRequest_SignsUidCodeIatNonce(): void
    {
        $nonce = str_repeat('a', 32);
        $request = $this->makeClient([])->buildRequest(42, 'journal', 1700000000, $nonce);

        $this->assertSame(42, $request['uid']);
        $this->assertSame('journal', $request['code']);
        $this->assertSame(1700000000, $request['iat']);
        $this->assertSame($nonce, $request['nonce']);
        $this->assertSame(hash_hmac('sha256', '42|journal|1700000000|' . $nonce, self::SECRET), $request['signature']);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function testGet_ValidAnswer_ReturnsTokenAndPostsSignedRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"jwt"}')]);

        $before = time();
        $this->assertSame('jwt', $client->get(42, 'journal'));

        $this->assertCount(1, $this->history);
        $sent = $this->history[0]['request'];
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame(self::API_URL . 'token/delegated', (string)$sent->getUri());

        $body = json_decode((string)$sent->getBody(), true);
        $this->assertSame(42, $body['uid']);
        $this->assertSame('journal', $body['code']);
        $this->assertGreaterThanOrEqual($before, $body['iat']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['nonce']);
        $this->assertSame(
            hash_hmac('sha256', sprintf('42|journal|%d|%s', $body['iat'], $body['nonce']), self::SECRET),
            $body['signature']
        );
    }

    public function testGet_NewNonceForEachRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"a"}'), new Response(200, [], '{"token":"b"}')]);
        $client->get(42, 'journal');
        $client->get(42, 'journal');

        $first = json_decode((string)$this->history[0]['request']->getBody(), true);
        $second = json_decode((string)$this->history[1]['request']->getBody(), true);
        $this->assertNotSame($first['nonce'], $second['nonce']);
    }

    public function testGet_NoSecret_ReturnsNullWithoutRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"jwt"}')], '');

        $this->assertFalse($client->isEnabled());
        $this->assertNull($client->get(42, 'journal'));
        $this->assertCount(0, $this->history);
    }

    public function testGet_ShortSecret_ReturnsNullWithoutRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"jwt"}')], 'short');

        $this->assertNull($client->get(42, 'journal'));
        $this->assertCount(0, $this->history);
    }

    public function testGet_NoApiUrl_ReturnsNullWithoutRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"jwt"}')], self::SECRET, '');

        $this->assertNull($client->get(42, 'journal'));
        $this->assertCount(0, $this->history);
    }

    public function testGet_InvalidUser_ReturnsNullWithoutRequest(): void
    {
        $client = $this->makeClient([new Response(200, [], '{"token":"jwt"}')]);

        $this->assertNull($client->get(0, 'journal'));
        $this->assertNull($client->get(42, ''));
        $this->assertCount(0, $this->history);
    }

    /**
     * @dataProvider httpErrorProvider
     */
    public function testGet_HttpError_ReturnsNull(int $status): void
    {
        $client = $this->makeClient([new Response($status, [], '{"message":"Invalid delegation"}')]);

        $this->assertNull($client->get(42, 'journal'));
    }

    public static function httpErrorProvider(): array
    {
        return ['disabled on the API' => [404], 'refused' => [401], 'server error' => [500]];
    }

    public function testGet_NetworkError_ReturnsNull(): void
    {
        $client = $this->makeClient([new ConnectException('Connection refused', new Request('POST', '/'))]);

        $this->assertNull($client->get(42, 'journal'));
    }

    /**
     * @dataProvider unexpectedAnswerProvider
     */
    public function testGet_UnexpectedAnswer_ReturnsNull(string $body): void
    {
        $client = $this->makeClient([new Response(200, [], $body)]);

        $this->assertNull($client->get(42, 'journal'));
    }

    public static function unexpectedAnswerProvider(): array
    {
        return [
            'not JSON' => ['<html></html>'],
            'no token' => ['{"foo":"bar"}'],
            'empty token' => ['{"token":""}'],
            'token not a string' => ['{"token":123}'],
            'scalar' => ['"jwt"'],
        ];
    }

    public function testGet_Failure_LogsWithoutSecret(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = $message . ' ' . json_encode($context);
            }
        };

        $client = $this->makeClient([new Response(401, [], '{"message":"Invalid delegation"}')], self::SECRET, self::API_URL, $logger);
        $client->get(42, 'journal');

        $this->assertCount(1, $logger->records);
        $this->assertStringNotContainsString(self::SECRET, $logger->records[0]);
    }
}
