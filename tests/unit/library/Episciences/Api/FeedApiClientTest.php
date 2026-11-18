<?php

declare(strict_types=1);

namespace unit\library\Episciences\Api;

use Episciences\Api\FeedApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Papers feeds of a journal fetched from the Episciences API.
 *
 * @covers \Episciences\Api\FeedApiClient
 */
final class FeedApiClientTest extends TestCase
{
    private const API_URL = 'https://api.example.org/api/';
    private const RSS = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel/></rss>';
    private const ATOM = '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom"/>';

    /** @var list<array{request: Request}> */
    private array $history = [];

    private ArrayAdapter $cache;

    /** @var AbstractLogger&object{messages: list<string>} */
    private AbstractLogger $logger;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter(0, false);
    }

    /**
     * @param list<Response|\Throwable> $responses
     */
    private function client(array $responses): FeedApiClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $this->logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            /** @param array<mixed> $context */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
            }
        };

        return new FeedApiClient(new Client(['handler' => $stack]), $this->cache, $this->logger, self::API_URL);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'rss' => [FeedApiClient::FORMAT_RSS, self::RSS, 'https://api.example.org/api/feed/rss/jtcam'],
            'atom' => [FeedApiClient::FORMAT_ATOM, self::ATOM, 'https://api.example.org/api/feed/atom/jtcam'],
        ];
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFetchReturnsTheFeedOfTheApi(string $format, string $xml, string $url): void
    {
        $feed = $this->client([new Response(200, ['Content-Type' => 'text/xml'], $xml)])->fetch($format, 'jtcam');

        self::assertSame($xml, $feed);
        self::assertCount(1, $this->history);
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame($url, (string)$this->history[0]['request']->getUri());
        self::assertSame('application/xml', $this->history[0]['request']->getHeaderLine('Accept'));
    }

    public function testFetchServesTheCachedFeedWithoutAskingTheApiAgain(): void
    {
        $client = $this->client([new Response(200, [], self::RSS)]);

        $first = $client->fetch(FeedApiClient::FORMAT_RSS, 'jtcam');
        $second = $client->fetch(FeedApiClient::FORMAT_RSS, 'jtcam');

        self::assertSame(self::RSS, $first);
        self::assertSame(self::RSS, $second);
        self::assertCount(1, $this->history);
    }

    public function testFeedsAreCachedPerFormatAndJournal(): void
    {
        $client = $this->client([
            new Response(200, [], self::RSS),
            new Response(200, [], self::ATOM),
            new Response(200, [], '<rss>other</rss>'),
        ]);

        self::assertSame(self::RSS, $client->fetch(FeedApiClient::FORMAT_RSS, 'jtcam'));
        self::assertSame(self::ATOM, $client->fetch(FeedApiClient::FORMAT_ATOM, 'jtcam'));
        self::assertSame('<rss>other</rss>', $client->fetch(FeedApiClient::FORMAT_RSS, 'other'));
        self::assertCount(3, $this->history);
    }

    /**
     * @return array<string, array{Response|\Throwable}>
     */
    public static function failureProvider(): array
    {
        return [
            'unknown journal' => [new Response(404, [], '{"message":"not found"}')],
            'server error' => [new Response(500)],
            'unreachable' => [new ConnectException('timeout', new Request('GET', self::API_URL))],
            'empty answer' => [new Response(200, [], " \n")],
            'not a full answer' => [new Response(204)],
        ];
    }

    /**
     * @dataProvider failureProvider
     */
    public function testFetchReturnsNullAndLogsWhenTheApiFails(Response|\Throwable $answer): void
    {
        $client = $this->client([$answer, new Response(200, [], self::RSS)]);

        self::assertNull($client->fetch(FeedApiClient::FORMAT_RSS, 'jtcam'));
        self::assertCount(1, $this->logger->messages);
        // A failure is not cached: the next call asks the API again
        self::assertSame(self::RSS, $client->fetch(FeedApiClient::FORMAT_RSS, 'jtcam'));
    }

    public function testFetchRefusesAnUnknownFormat(): void
    {
        $client = $this->client([]);

        $this->expectException(InvalidArgumentException::class);
        $client->fetch('json', 'jtcam');
    }

    public function testJournalCodeIsEncodedInTheUrl(): void
    {
        $client = $this->client([]);

        self::assertSame('https://api.example.org/api/feed/rss/a%2Fb%3Fc', $client->feedUrl(FeedApiClient::FORMAT_RSS, 'a/b?c'));
    }

    public function testApiUrlWithoutTrailingSlash(): void
    {
        $client = new FeedApiClient(new Client(), $this->cache, new NullLogger(), 'https://api.example.org/api');

        self::assertSame('https://api.example.org/api/feed/atom/jtcam', $client->feedUrl(FeedApiClient::FORMAT_ATOM, 'jtcam'));
    }
}
