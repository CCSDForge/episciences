<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewersstatsController;

/**
 * @covers \ReviewersstatsController
 */
final class ReviewersstatsController_BuildPageRangeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewersstatsController.php';
    }

    /**
     * @return list<int|null>
     */
    private function buildPageRange(int $current, int $totalPages, int $delta = 2): array
    {
        $method = new ReflectionMethod(ReviewersstatsController::class, 'buildPageRange');
        $method->setAccessible(true);
        return $method->invoke(null, $current, $totalPages, $delta);
    }

    public function testNoPagesReturnsEmptyRange(): void
    {
        self::assertSame([], $this->buildPageRange(1, 0));
    }

    public function testSinglePageReturnsOnlyThatPage(): void
    {
        self::assertSame([1], $this->buildPageRange(1, 1));
    }

    public function testFewPagesAreAllListedWithoutEllipsis(): void
    {
        self::assertSame([1, 2, 3, 4, 5], $this->buildPageRange(1, 5));
    }

    public function testManyPagesAreCollapsedAroundCurrentPage(): void
    {
        // This is the exact shape reported by the user: 71 pages, currently on page 1,
        // must NOT list all 71 page numbers.
        $range = $this->buildPageRange(1, 71);

        self::assertSame([1, 2, 3, null, 71], $range);
    }

    public function testCurrentPageInTheMiddleShowsEllipsisOnBothSides(): void
    {
        $range = $this->buildPageRange(40, 71);

        self::assertSame([1, null, 38, 39, 40, 41, 42, null, 71], $range);
    }

    public function testCurrentPageNearTheEndHasNoTrailingEllipsis(): void
    {
        $range = $this->buildPageRange(70, 71);

        self::assertSame([1, null, 68, 69, 70, 71], $range);
    }
}
