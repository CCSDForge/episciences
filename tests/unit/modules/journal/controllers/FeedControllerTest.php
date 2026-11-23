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
     * @dataProvider actionProvider
     */
    public function testActionPermanentlyRedirectsToJournalPapersFeed(string $action): void
    {
        self::assertFalse(defined('PREFIX_URL'), 'this case covers deployments without URL prefix');
        self::assertSame([301, '/rss/papers'], $this->dispatch($action));
    }

    /**
     * Journals served behind a "/<journal code>/" prefix (PREFIX_URL) keep it in the redirect.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRedirectKeepsJournalUrlPrefix(): void
    {
        define('PREFIX_URL', '/dev/');

        self::assertSame([301, '/dev/rss/papers'], $this->dispatch('indexAction'));
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
