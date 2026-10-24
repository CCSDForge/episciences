<?php

declare(strict_types=1);

namespace unit\modules\portal\controllers;

use Episciences\Security\AccessLog;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Behavioural tests for the account merging token check of the portal API.
 */
final class ApiControllerTest extends TestCase
{
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/portal/controllers/ApiController.php';
        $this->logHandler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->logHandler]));
        AccessLog::reset();
    }

    protected function tearDown(): void
    {
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
    }

    public function testMatchingTokenIsAccepted(): void
    {
        self::assertTrue(\ApiController::isValidMergeAuthToken('expected-token', 'expected-token'));
    }

    public function testWrongTokenIsRejected(): void
    {
        self::assertFalse(\ApiController::isValidMergeAuthToken('expected-token', 'other-token'));
        self::assertFalse(\ApiController::isValidMergeAuthToken('expected-token', ''));
    }

    public function testEmptyConfiguredTokenRejectsEveryRequest(): void
    {
        self::assertFalse(\ApiController::isValidMergeAuthToken('', ''));
        self::assertFalse(\ApiController::isValidMergeAuthToken('', 'anything'));
    }

    /**
     * A merge request with a wrong authentication token is refused before any account is read, and logged
     */
    public function testMergeRequestWithAWrongTokenIsRefusedAndLogged(): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams(['merger' => '100', 'keeper' => '200', 'authToken' => 'forged-token']);
        $response = new Zend_Controller_Response_HttpTestCase();
        // The security log reads the request of the front controller
        $front = \Zend_Controller_Front::getInstance();
        $previousRequest = $front->getRequest();
        $front->setRequest($request);

        try {
            (new \ApiController($request, $response))->mergeAction();
        } finally {
            // setRequest() does not accept null
            (new \ReflectionProperty(\Zend_Controller_Front::class, '_request'))->setValue($front, $previousRequest);
        }

        self::assertSame(\Episciences_Merge_MergingManager::HTTP_UNAUTHORIZED, $response->getHttpResponseCode());
        $records = $this->logHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame(AccessLog::TOKEN_INVALID, $records[0]->message);
        self::assertSame('merge_auth_token', $records[0]->context['reason']);
        self::assertSame('***', $records[0]->context['params']['authToken'], 'the token is never logged');
        self::assertStringNotContainsString('forged-token', (string)json_encode($records[0]->context));
    }
}
