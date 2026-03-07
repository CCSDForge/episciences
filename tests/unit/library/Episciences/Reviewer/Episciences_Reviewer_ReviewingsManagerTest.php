<?php

namespace unit\library\Episciences\Reviewer;

use Episciences_Reviewer_Reviewing;
use Episciences_Reviewer_ReviewingsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Reviewer_ReviewingsManager.
 *
 * Pure-logic: countByStatus, getReviewingsWith, sortReviewingsByRvid.
 * No DB or filesystem required.
 *
 * @covers Episciences_Reviewer_ReviewingsManager
 */
class Episciences_Reviewer_ReviewingsManagerTest extends TestCase
{
    // =========================================================================
    // countByStatus — single status
    // =========================================================================

    public function testCountByStatusWithSingleStatusNoMatch(): void
    {
        $r = $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_PENDING);
        $count = Episciences_Reviewer_ReviewingsManager::countByStatus([$r], Episciences_Reviewer_Reviewing::STATUS_WIP);
        self::assertSame(0, $count);
    }

    public function testCountByStatusWithSingleStatusMatch(): void
    {
        $r = $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        $count = Episciences_Reviewer_ReviewingsManager::countByStatus([$r], Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        self::assertSame(1, $count);
    }

    public function testCountByStatusWithMultipleReviewings(): void
    {
        $reviewings = [
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_COMPLETE),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_PENDING),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_COMPLETE),
        ];
        $count = Episciences_Reviewer_ReviewingsManager::countByStatus($reviewings, Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        self::assertSame(2, $count);
    }

    public function testCountByStatusWithEmptyArray(): void
    {
        $count = Episciences_Reviewer_ReviewingsManager::countByStatus([], Episciences_Reviewer_Reviewing::STATUS_PENDING);
        self::assertSame(0, $count);
    }

    // =========================================================================
    // countByStatus — array of statuses
    // =========================================================================

    public function testCountByStatusWithArrayOfStatuses(): void
    {
        $reviewings = [
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_PENDING),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_WIP),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_COMPLETE),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_DECLINED),
        ];

        $count = Episciences_Reviewer_ReviewingsManager::countByStatus(
            $reviewings,
            [Episciences_Reviewer_Reviewing::STATUS_PENDING, Episciences_Reviewer_Reviewing::STATUS_WIP]
        );

        self::assertSame(2, $count);
    }

    public function testCountByStatusWithArrayNoMatch(): void
    {
        $r = $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_OBSOLETE);
        $count = Episciences_Reviewer_ReviewingsManager::countByStatus(
            [$r],
            [Episciences_Reviewer_Reviewing::STATUS_PENDING, Episciences_Reviewer_Reviewing::STATUS_WIP]
        );
        self::assertSame(0, $count);
    }

    // =========================================================================
    // getReviewingsWith
    // =========================================================================

    public function testGetReviewingsWithFiltersCorrectly(): void
    {
        $reviewings = [
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_PENDING),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_COMPLETE),
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_PENDING),
        ];

        $result = Episciences_Reviewer_ReviewingsManager::getReviewingsWith(
            $reviewings,
            ['status' => Episciences_Reviewer_Reviewing::STATUS_PENDING]
        );

        self::assertCount(2, $result);
    }

    public function testGetReviewingsWithReturnsEmptyWhenNoMatch(): void
    {
        $reviewings = [
            $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_WIP),
        ];

        $result = Episciences_Reviewer_ReviewingsManager::getReviewingsWith(
            $reviewings,
            ['status' => Episciences_Reviewer_Reviewing::STATUS_COMPLETE]
        );

        self::assertEmpty($result);
    }

    public function testGetReviewingsWithEmptyInputReturnsEmpty(): void
    {
        $result = Episciences_Reviewer_ReviewingsManager::getReviewingsWith(
            [],
            ['status' => Episciences_Reviewer_Reviewing::STATUS_PENDING]
        );
        self::assertSame([], $result);
    }

    public function testGetReviewingsWithPreservesMatchingObjects(): void
    {
        $r1 = $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_DECLINED);
        $r2 = $this->makeReviewing(Episciences_Reviewer_Reviewing::STATUS_UNANSWERED);

        $result = Episciences_Reviewer_ReviewingsManager::getReviewingsWith(
            [$r1, $r2],
            ['status' => Episciences_Reviewer_Reviewing::STATUS_DECLINED]
        );

        self::assertCount(1, $result);
        self::assertSame($r1, $result[0]);
    }

    // =========================================================================
    // sortReviewingsByRvid
    // =========================================================================

    public function testSortReviewingsByRvidGroupsByRvid(): void
    {
        // getRvid() on Reviewing delegates to getAssignment() — use mocks with getRvid()
        $r1 = $this->makeReviewingMockWithRvid(10);
        $r2 = $this->makeReviewingMockWithRvid(20);
        $r3 = $this->makeReviewingMockWithRvid(10);

        $result = Episciences_Reviewer_ReviewingsManager::sortReviewingsByRvid([$r1, $r2, $r3]);

        self::assertArrayHasKey(10, $result);
        self::assertArrayHasKey(20, $result);
        self::assertCount(2, $result[10]);
        self::assertCount(1, $result[20]);
        self::assertSame($r1, $result[10][0]);
        self::assertSame($r3, $result[10][1]);
    }

    public function testSortReviewingsByRvidEmptyInputReturnsEmpty(): void
    {
        $result = Episciences_Reviewer_ReviewingsManager::sortReviewingsByRvid([]);
        self::assertSame([], $result);
    }

    public function testSortReviewingsByRvidSingleEntry(): void
    {
        $r = $this->makeReviewingMockWithRvid(5);
        $result = Episciences_Reviewer_ReviewingsManager::sortReviewingsByRvid([$r]);
        self::assertArrayHasKey(5, $result);
        self::assertCount(1, $result[5]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeReviewing(int $status): Episciences_Reviewer_Reviewing
    {
        $r = new Episciences_Reviewer_Reviewing();
        $r->setStatus($status);
        return $r;
    }

    /** Create a stub with a predictable getRvid() return value. */
    private function makeReviewingMockWithRvid(int $rvid): Episciences_Reviewer_Reviewing
    {
        $mock = $this->createPartialMock(Episciences_Reviewer_Reviewing::class, ['getRvid']);
        $mock->method('getRvid')->willReturn($rvid);
        return $mock;
    }
}
