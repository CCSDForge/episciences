<?php

declare(strict_types=1);

namespace unit\modules\portal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the account merging token check of the portal API.
 */
final class ApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/portal/controllers/ApiController.php';
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
}
