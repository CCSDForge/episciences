<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewersstatsController;

/**
 * @covers \ReviewersstatsController
 */
final class ReviewersstatsController_LoadReviewerProfileTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewersstatsController.php';
    }

    public function testNoVisibleRowMeansNoProfile(): void
    {
        // The detail page is reached with a uid/email taken from the URL: when none of that
        // person's assignments are visible to the viewer (restriction, period, conflicts of
        // interest, or never a reviewer here), no account must be loaded from the URL alone.
        $method = new ReflectionMethod(ReviewersstatsController::class, 'loadReviewerProfile');
        $method->setAccessible(true);

        self::assertSame(1, $method->getNumberOfParameters(), 'the profile is only resolved from the visible rows');
        self::assertNull($method->invoke(null, []));
    }
}
