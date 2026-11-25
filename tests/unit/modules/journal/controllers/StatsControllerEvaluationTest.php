<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use StatsController;

/**
 * Query of the evaluation statistics of the statistics page (StatsController): the same filters as the dashboard.
 */
class StatsControllerEvaluationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    public function testAllTheDataWithoutFilter(): void
    {
        $this->assertSame([], StatsController::getEvaluationQuery(null, null));
    }

    public function testTheStartDateOfTheStatisticsIsSent(): void
    {
        $this->assertSame(['startAfterDate' => '2023-01-31'], StatsController::getEvaluationQuery(null, '2023-01-31'));
    }

    public function testTheYearAndTheStartDateAreSent(): void
    {
        $this->assertSame(['year' => 2024, 'startAfterDate' => '2023-01-31'], StatsController::getEvaluationQuery(2024, '2023-01-31'));
    }
}
