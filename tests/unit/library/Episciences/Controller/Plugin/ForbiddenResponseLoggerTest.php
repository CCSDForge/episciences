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

    private function plugin(int $code, string $controller = 'paper', string $action = 'view'): ForbiddenResponseLogger
    {
        $request = new Zend_Controller_Request_Http();
        $request->setControllerName($controller)->setActionName($action);
        $response = new Zend_Controller_Response_Http();
        $response->setHttpResponseCode($code);

        $plugin = new ForbiddenResponseLogger();
        $plugin->setRequest($request);
        $plugin->setResponse($response);

        return $plugin;
    }

    private function shutdownWith(int $code, string $controller = 'paper', string $action = 'view'): void
    {
        $this->plugin($code, $controller, $action)->dispatchLoopShutdown();
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
     * A redirection or a 404 logged earlier in the request does not explain a 403 sent afterwards
     */
    public function testDeniedResponseAfterAnEventAnsweredOtherwiseIsRecorded(): void
    {
        AccessLog::denied(AccessLog::UNSAFE_PATH, 'unsafe_file_name');

        $this->shutdownWith(403);

        self::assertSame(
            [[AccessLog::UNSAFE_PATH, 'unsafe_file_name'], [AccessLog::HTTP_FORBIDDEN, '']],
            $this->loggedEvents()
        );
    }

    /**
     * A 403 sent with header() then exit() never reaches dispatchLoopShutdown(): checked at the end of the script
     *
     * @dataProvider deniedCodeProvider
     */
    public function testDeniedResponseOfAnExitedRequestIsRecorded(int $code): void
    {
        $this->plugin(200)->checkExitedResponse($code);

        $records = $this->securityLogRecords();
        self::assertCount(1, $records);
        self::assertSame(AccessLog::HTTP_FORBIDDEN, $records[0]->message);
        self::assertSame($code, $records[0]->context['status']);
    }

    /**
     * The end of the script of a request that went through the dispatch loop adds nothing
     */
    public function testEndOfScriptAfterTheDispatchLoopIsNotRecordedTwice(): void
    {
        $plugin = $this->plugin(403);

        $plugin->dispatchLoopShutdown();
        $plugin->checkExitedResponse(403);

        self::assertCount(1, $this->securityLogRecords());
    }

    public function testEndOfScriptWithoutStatusCodeIsNotRecorded(): void
    {
        $this->plugin(200)->checkExitedResponse(false);

        self::assertSame([], $this->securityLogRecords());
    }

    public function testExitedDenyLandingPageIsNotRecorded(): void
    {
        $this->plugin(200, 'error', 'deny')->checkExitedResponse(403);

        self::assertSame([], $this->securityLogRecords());
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
