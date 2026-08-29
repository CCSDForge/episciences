<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Guards ActivityController's hand-maintained constant lists (RECAP_CATEGORY_PRIORITY,
 * RECAP_CATEGORY_ALIASES, REVIEW_EVENT_COLUMN_ORDER) against drifting out of sync with
 * Episciences_Paper_Logger's category map. Without this test, the same drift is only
 * caught by ActivityController::assertConstantsAreConsistent() throwing a LogicException
 * on the first production request to /activity/view after the mistake ships — this test
 * catches it at merge time instead.
 *
 * @covers ActivityController
 */
class ActivityControllerConstantsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ActivityController.php';
    }

    public function testConstantsAreConsistent(): void
    {
        $method = new ReflectionMethod(\ActivityController::class, 'assertConstantsAreConsistent');
        $method->setAccessible(true);

        $method->invoke(null);

        $this->addToAssertionCount(1);
    }
}
