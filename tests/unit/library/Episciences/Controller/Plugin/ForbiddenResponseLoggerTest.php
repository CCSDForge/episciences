<?php

declare(strict_types=1);

namespace unit\library\Episciences\Controller\Plugin;

use Episciences\Controller\Plugin\ForbiddenResponseLogger;
use Episciences\Security\AccessLog;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;

/**
 * @covers \Episciences\Controller\Plugin\ForbiddenResponseLogger
 */
final class ForbiddenResponseLoggerTest extends TestCase
{
    private TestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->handler]));
        AccessLog::reset();
    }

    protected function tearDown(): void
    {
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
    }

    private function shutdownWith(int $code, string $controller = 'paper', string $action = 'view'): void
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName($controller)->setActionName($action);
        $response = new Zend_Controller_Response_Http();
        $response->setHttpResponseCode($code);

        $plugin = new ForbiddenResponseLogger();
        $plugin->setRequest($request);
        $plugin->setResponse($response);
        $plugin->dispatchLoopShutdown();
    }

    /**
     * @dataProvider deniedCodeProvider
     */
    public function testDeniedResponseNotYetLoggedIsRecorded(int $code): void
    {
        $this->shutdownWith($code);

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame(AccessLog::HTTP_FORBIDDEN, $records[0]->message);
        self::assertSame($code, $records[0]->context['status']);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function deniedCodeProvider(): array
    {
        return ['unauthorized' => [401], 'forbidden' => [403]];
    }

    public function testDeniedResponseAlreadyLoggedIsNotDuplicated(): void
    {
        AccessLog::denied(AccessLog::ACL_DENIED);

        $this->shutdownWith(403);

        self::assertCount(1, $this->handler->getRecords());
    }

    /**
     * The denial redirected to error/deny was logged by the previous request.
     */
    public function testDenyLandingPageIsNotRecorded(): void
    {
        $this->shutdownWith(403, 'error', 'deny');

        self::assertSame([], $this->handler->getRecords());
    }

    /**
     * @dataProvider otherCodeProvider
     */
    public function testOtherResponsesAreNotRecorded(int $code): void
    {
        $this->shutdownWith($code);

        self::assertSame([], $this->handler->getRecords());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function otherCodeProvider(): array
    {
        return ['ok' => [200], 'redirect' => [302], 'not found' => [404], 'server error' => [500]];
    }
}
