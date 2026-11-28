<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Api\FeedApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use RssController;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use unit\modules\ControllerTestCase;

// Loaded before ApiFeedRssController is declared, at the end of this file
require_once APPLICATION_PATH . '/modules/journal/controllers/RssController.php';

/**
 * The papers feeds of a journal site (/rss/papers, /rss/atom) are those of the Episciences API,
 * served unchanged under the journal URLs.
 */
final class RssControllerTest extends ControllerTestCase
{
    public const API_URL = 'https://api.example.org/api/';

    /** @var list<array{request: Request}> */
    public static array $history = [];

    /** @var list<Response|\Throwable> */
    public static array $answers = [];

    protected function setUp(): void
    {
        self::$history = [];
        self::$answers = [];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function papersFeedProvider(): array
    {
        return [
            'rss' => ['papers', '<rss version="2.0"><channel/></rss>', 'rss'],
            'atom' => ['atom', '<feed xmlns="http://www.w3.org/2005/Atom"/>', 'atom'],
        ];
    }

    /**
     * @dataProvider papersFeedProvider
     */
    public function testPapersFeedIsTheFeedOfTheApi(string $action, string $xml, string $format): void
    {
        self::$answers = [new Response(200, ['Content-Type' => 'text/xml'], $xml)];

        $result = $this->dispatch(ApiFeedRssController::class, $action, $this->getRequest());

        self::assertSame(200, $result->statusCode());
        self::assertSame($xml, $result->body());
        self::assertSame('text/xml; charset=UTF-8', $result->header('Content-Type'));
        self::assertCount(1, self::$history);
        self::assertSame(
            self::API_URL . 'feed/' . $format . '/' . rawurlencode((string)RVCODE),
            (string)self::$history[0]['request']->getUri()
        );
    }

    /**
     * @dataProvider papersFeedProvider
     */
    public function testPapersFeedIsUnavailableWhenTheApiFails(string $action): void
    {
        self::$answers = [new Response(500)];

        $result = $this->dispatch(ApiFeedRssController::class, $action, $this->getRequest());

        self::assertSame(503, $result->statusCode());
        self::assertSame((string)RssController::RETRY_AFTER, $result->header('Retry-After'));
        self::assertStringNotContainsString('<rss', $result->body());
    }
}

/**
 * RssController asking a fake Episciences API.
 */
final class ApiFeedRssController extends RssController
{
    protected function getFeedApiClient(): FeedApiClient
    {
        $stack = HandlerStack::create(new MockHandler(RssControllerTest::$answers));
        $stack->push(Middleware::history(RssControllerTest::$history));

        return new FeedApiClient(new Client(['handler' => $stack]), new ArrayAdapter(0, false), new NullLogger(), RssControllerTest::API_URL);
    }
}
