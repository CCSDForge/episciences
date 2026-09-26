<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Reviewer_Reviewing;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewersstatsController;

/**
 * Reproduces Episciences_Reviewer_Reviewing::loadStatus() from a raw SQL row instead of a
 * hydrated Reviewing object, so the /reviewersstats detail page uses the same vocabulary as
 * the "Mes relectures" dashboard panel.
 *
 * @covers \ReviewersstatsController
 */
final class ReviewersstatsController_ReviewingStatusCodeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewersstatsController.php';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function statusCode(array $row, ?FakeReviewableStatePaper $paper): int
    {
        $method = new ReflectionMethod(ReviewersstatsController::class, 'reviewingStatusCode');
        $method->setAccessible(true);
        return $method->invoke(null, $row, $paper);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(?int $reviewStatus, string $assignmentStatus = 'pending'): array
    {
        return [
            'review_status' => $reviewStatus,
            'assignment_status' => $assignmentStatus,
        ];
    }

    public function testCompletedReviewIsAlwaysComplete(): void
    {
        // Even for a paper that has since become obsolete: a review that was actually
        // finished stays "completed", it doesn't retroactively become "not needed".
        $paper = new FakeReviewableStatePaper(false);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $this->statusCode($this->row(2), $paper));
    }

    public function testInProgressReviewOnAnObsoletePaperIsNotNeedReviewing(): void
    {
        $paper = new FakeReviewableStatePaper(false);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_NOT_NEED_REVIEWING, $this->statusCode($this->row(1), $paper));
    }

    public function testPendingReviewOnAReviewablePaperIsPending(): void
    {
        $paper = new FakeReviewableStatePaper(true);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_PENDING, $this->statusCode($this->row(0), $paper));
    }

    public function testInProgressReviewOnAReviewablePaperIsWip(): void
    {
        $paper = new FakeReviewableStatePaper(true);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_WIP, $this->statusCode($this->row(1), $paper));
    }

    public function testNoReportAndObsoletePaperIsObsolete(): void
    {
        // Regression guard for the bug that started this feature: a never-answered
        // invitation for a paper that has moved past active review is "obsolete", not a
        // genuinely pending action item.
        $paper = new FakeReviewableStatePaper(false);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_OBSOLETE, $this->statusCode($this->row(null), $paper));
    }

    public function testNoReportAndDeclinedAssignmentIsDeclined(): void
    {
        $paper = new FakeReviewableStatePaper(true);
        self::assertSame(
            Episciences_Reviewer_Reviewing::STATUS_DECLINED,
            $this->statusCode($this->row(null, 'declined'), $paper)
        );
    }

    public function testNoReportAndReviewablePaperIsUnanswered(): void
    {
        $paper = new FakeReviewableStatePaper(true);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_UNANSWERED, $this->statusCode($this->row(null), $paper));
    }

    public function testMissingPaperIsTreatedAsNotReviewable(): void
    {
        // If the paper row itself is gone (deleted), there's nothing to say it's still
        // reviewable, so it degrades the same way as an explicitly obsolete paper.
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_OBSOLETE, $this->statusCode($this->row(null), null));
    }
}

/**
 * Minimal stand-in for Episciences_Paper: only canBeReviewed() is exercised here, and the
 * real class requires a fully hydrated DB row to construct.
 */
final class FakeReviewableStatePaper extends \Episciences_Paper
{
    public function __construct(private readonly bool $reviewable)
    {
    }

    public function canBeReviewed(): bool
    {
        return $this->reviewable;
    }
}
