<?php

declare(strict_types=1);

namespace unit\library\Episciences\Controller\Plugin;

use Episciences\Controller\Plugin\ForbiddenResponseLogger;
use Episciences\Security\AccessLog;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 4) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

/**
 * @covers \Episciences\Controller\Plugin\ForbiddenResponseLogger
 */
final class ForbiddenResponseLoggerTest extends TestCase
{
    use SecurityLogCaptureTrait;

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

        $records = $this->securityLogRecords();
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

        self::assertCount(1, $this->securityLogRecords());
    }

    /**
     * The denial redirected to error/deny was logged by the previous request.
     */
    public function testDenyLandingPageIsNotRecorded(): void
    {
        $this->shutdownWith(403, 'error', 'deny');

        self::assertSame([], $this->securityLogRecords());
    }

    /**
     * @dataProvider otherCodeProvider
     */
    public function testOtherResponsesAreNotRecorded(int $code): void
    {
        $this->shutdownWith($code);

        self::assertSame([], $this->securityLogRecords());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function otherCodeProvider(): array
    {
        return ['ok' => [200], 'redirect' => [302], 'not found' => [404], 'server error' => [500]];
    }
}
