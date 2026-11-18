<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Journal FeedController: the portal-style /feed URLs are permanently redirected
 * to the journal papers feed.
 */
final class FeedControllerTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FeedController.php';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actionProvider(): array
    {
        return [
            '/feed' => ['indexAction'],
            '/feed/rss' => ['rssAction'],
            '/feed/atom' => ['atomAction'],
        ];
    }

    /**
     * PREFIX_URL is always defined on this branch (journals are served behind a "/<journal code>/"
     * prefix), and the test bootstrap defines it before any test runs, so the redirect is checked
     * against its current value.
     *
     * @dataProvider actionProvider
     */
    public function testActionRedirectsToJournalPapersFeedWithUrlPrefix(string $action): void
    {
        self::assertTrue(defined('PREFIX_URL'));
        self::assertSame([301, PREFIX_URL . 'rss/papers'], $this->dispatch($action));
    }

    /**
     * @return array{int, string|null} HTTP response code, Location header
     */
    private function dispatch(string $action): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $response = new Zend_Controller_Response_HttpTestCase();
        $controller = new \FeedController($request, $response);

        $controller->{$action}();

        self::assertTrue($response->isRedirect());

        $location = null;
        foreach ($response->getHeaders() as $header) {
            if (strcasecmp($header['name'], 'Location') === 0) {
                $location = $header['value'];
            }
        }

        return [$response->getHttpResponseCode(), $location];
    }
}
