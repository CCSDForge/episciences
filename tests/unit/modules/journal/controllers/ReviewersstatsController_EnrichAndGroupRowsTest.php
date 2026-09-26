<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Reviewer_Reviewing;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewersstatsController;

/**
 * @covers \ReviewersstatsController
 */
final class ReviewersstatsController_EnrichAndGroupRowsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewersstatsController.php';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, FakeVersionedPaper> $papers
     * @return array<int, array<string, mixed>>
     */
    private function enrichAndGroupRows(array $rows, array $papers): array
    {
        $method = new ReflectionMethod(ReviewersstatsController::class, 'enrichAndGroupRows');
        $method->setAccessible(true);
        return $method->invoke(null, $rows, $papers);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides): array
    {
        return array_merge([
            'docid' => 1,
            'assignment_status' => 'pending',
            'assignment_date' => '2026-01-01 00:00:00',
            'deadline' => null,
            'invitation_status' => 'pending',
            'sending_date' => null,
            'answer' => null,
            'answer_date' => null,
            'review_status' => null,
            'review_update_date' => null,
        ], $overrides);
    }

    public function testOnlyTheMostRecentAttemptPerPaperIsNotSuperseded(): void
    {
        // Regression guard: the same reviewer, same paper version, invited/reassigned
        // several times over as many weeks — each attempt is its own
        // USER_ASSIGNMENT row. Only the latest should read as "current".
        $rows = [
            $this->row(['docid' => 90103, 'assignment_date' => '2026-05-22 09:35:42']),
            $this->row(['docid' => 90103, 'assignment_date' => '2026-06-05 07:20:44']),
            $this->row(['docid' => 90103, 'assignment_date' => '2026-05-22 09:52:32']),
        ];
        $papers = [90103 => new FakeVersionedPaper(8.0, 'concept-A')];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        $superseded = array_column($grouped, 'is_superseded', 'assignment_date');
        self::assertFalse($superseded['2026-06-05 07:20:44']);
        self::assertTrue($superseded['2026-05-22 09:35:42']);
        self::assertTrue($superseded['2026-05-22 09:52:32']);
    }

    public function testACompletedReportOnlyShowsOnItsOwningAttemptNotEveryOne(): void
    {
        // Regression guard: REVIEWER_REPORT has no foreign key to a specific assignment
        // attempt, only (UID, DOCID) — joining it therefore attaches the *same* single
        // completed report to every USER_ASSIGNMENT row for that reviewer/paper: a paper
        // re-invited 3 times showed "completed review" 3 times even though only one report
        // was ever submitted (one REVIEWER_REPORT row, three assignment attempts).
        $rows = [
            $this->row(['docid' => 90101, 'assignment_date' => '2026-01-07 10:30:23', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
            $this->row(['docid' => 90101, 'assignment_date' => '2026-04-07 12:30:32', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
            $this->row(['docid' => 90101, 'assignment_date' => '2026-01-07 10:30:23', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
        ];
        $papers = [90101 => new FakeVersionedPaper(2.0, 'concept-dichotomy')];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        // Only the row whose own assignment_date is the latest one at-or-before the report's
        // completion date is allowed to show it — here that's the 2026-04-07 attempt.
        $completeRows = array_filter($grouped, static fn(array $r) => $r['status_code'] === Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        self::assertCount(1, $completeRows);
        self::assertSame('2026-04-07 12:30:32', reset($completeRows)['assignment_date']);
    }

    public function testACompletedReportIsNotAttachedToALaterUnrelatedUnassignment(): void
    {
        // Regression guard for a second, subtler bug on the same feature: the *most recent*
        // assignment attempt is not necessarily the one the report belongs to. The report
        // was completed while an earlier assignment was active, but a later, unrelated event
        // (a new paper version requested) auto-unassigned the reviewer, creating one more USER_ASSIGNMENT row with no
        // INVITATION_ID and a *later* WHEN than the completion date. Naively picking "the
        // most recent attempt" attached the completed report to that unassignment row
        // instead of the attempt that was actually current when it was submitted.
        $rows = [
            $this->row(['docid' => 90101, 'assignment_date' => '2026-01-07 10:30:23', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
            $this->row(['docid' => 90101, 'assignment_date' => '2026-04-07 12:30:32', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
            // The auto-unassignment row: created after the report was completed, no invitation.
            $this->row(['docid' => 90101, 'assignment_date' => '2026-06-18 10:55:02', 'review_status' => 2, 'review_update_date' => '2026-05-06 12:05:21']),
        ];
        $papers = [90101 => new FakeVersionedPaper(2.0, 'concept-dichotomy')];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        $statusByDate = array_column($grouped, 'status_code', 'assignment_date');
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $statusByDate['2026-04-07 12:30:32']);
        self::assertNotSame(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $statusByDate['2026-06-18 10:55:02']);
        self::assertNotSame(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $statusByDate['2026-01-07 10:30:23']);

        // is_superseded (the visual "greyed out" marker) stays purely chronological: the
        // unassignment row is still the most recent attempt, even though it doesn't own
        // the report.
        $supersededByDate = array_column($grouped, 'is_superseded', 'assignment_date');
        self::assertFalse($supersededByDate['2026-06-18 10:55:02']);
        self::assertTrue($supersededByDate['2026-04-07 12:30:32']);
    }

    public function testSupersededAttemptWithNoOwnAnswerShowsAsReplacedNotObsolete(): void
    {
        // Regression guard: a first invitation that was never individually resolved,
        // superseded two days later by a second invitation that was accepted and completed.
        // The paper is now STATUS_WAITING_FOR_MAJOR_REVISION — canBeReviewed()
        // is false because the review process concluded *successfully*, not because the
        // paper went stale. Showing "obsolete review invitation" on the first attempt right
        // next to "completed review" on the second reads as a contradiction; it should read
        // as a neutral "replaced" marker instead.
        $rows = [
            $this->row(['docid' => 90102, 'assignment_date' => '2026-01-08 17:06:27']),
            $this->row(['docid' => 90102, 'assignment_date' => '2026-01-10 14:35:55', 'review_status' => 2, 'review_update_date' => '2026-04-06 12:09:46']),
        ];
        $papers = [90102 => new FakeVersionedPaper(2.0, 'concept-justact')];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        $statusByDate = array_column($grouped, 'status_code', 'assignment_date');
        self::assertSame(ReviewersstatsController::STATUS_SUPERSEDED_REPLACED, $statusByDate['2026-01-08 17:06:27']);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $statusByDate['2026-01-10 14:35:55']);
    }

    public function testSupersededDeclinedAttemptStaysDeclined(): void
    {
        // A real, individually-known fact about a superseded attempt (it was declined) must
        // not be papered over by the neutral "replaced" marker — only an attempt with no
        // terminal answer of its own gets relabelled.
        $rows = [
            $this->row(['docid' => 90102, 'assignment_date' => '2026-01-08 17:06:27', 'assignment_status' => 'declined']),
            $this->row(['docid' => 90102, 'assignment_date' => '2026-01-10 14:35:55', 'review_status' => 2, 'review_update_date' => '2026-04-06 12:09:46']),
        ];
        $papers = [90102 => new FakeVersionedPaper(2.0, 'concept-justact')];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        $statusByDate = array_column($grouped, 'status_code', 'assignment_date');
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_DECLINED, $statusByDate['2026-01-08 17:06:27']);
    }

    public function testUnansweredLatestAttemptWithNoLaterSuccessStaysObsolete(): void
    {
        // The *current* (non-superseded) attempt keeps the real "obsolete" status when there
        // genuinely is no later, successful attempt to make that label misleading.
        $rows = [$this->row(['docid' => 90102, 'assignment_date' => '2026-01-08 17:06:27'])];
        $papers = [90102 => new FakeVersionedPaper(2.0, 'concept-justact', reviewable: false)];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_OBSOLETE, $grouped[0]['status_code']);
    }

    public function testRowsAreGroupedByConceptIdentifierNotByDocid(): void
    {
        // Two different DOCIDs (two versions of the same paper) must land in one group;
        // a third, unrelated paper must start its own group.
        $rows = [
            $this->row(['docid' => 100, 'assignment_date' => '2026-04-01 00:00:00']),
            $this->row(['docid' => 101, 'assignment_date' => '2026-05-01 00:00:00']),
            $this->row(['docid' => 200, 'assignment_date' => '2026-03-01 00:00:00']),
        ];
        $papers = [
            100 => new FakeVersionedPaper(1.0, 'concept-A'),
            101 => new FakeVersionedPaper(2.0, 'concept-A'),
            200 => new FakeVersionedPaper(1.0, 'concept-B'),
        ];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        $newGroupFlags = array_column($grouped, 'is_new_paper_group', 'docid');
        self::assertCount(2, array_filter($newGroupFlags));
        // concept-A is the more recently active group overall, so it sorts first; within it,
        // the newest version (101) sorts before the older one (100).
        self::assertSame([101, 100, 200], array_column($grouped, 'docid'));
    }

    public function testPaperGroupsAreOrderedByMostRecentActivityFirst(): void
    {
        $rows = [
            $this->row(['docid' => 1, 'assignment_date' => '2020-01-01 00:00:00']),
            $this->row(['docid' => 2, 'assignment_date' => '2026-01-01 00:00:00']),
        ];
        $papers = [
            1 => new FakeVersionedPaper(1.0, 'old-paper'),
            2 => new FakeVersionedPaper(1.0, 'recent-paper'),
        ];

        $grouped = $this->enrichAndGroupRows($rows, $papers);

        self::assertSame([2, 1], array_column($grouped, 'docid'));
    }

    public function testMissingPaperFallsBackToPerDocidGrouping(): void
    {
        $rows = [$this->row(['docid' => 42])];

        $grouped = $this->enrichAndGroupRows($rows, []);

        self::assertSame('docid:42', $grouped[0]['concept_identifier']);
        self::assertNull($grouped[0]['version']);
        self::assertTrue($grouped[0]['is_new_paper_group']);
        self::assertFalse($grouped[0]['is_superseded']);
    }
}

/**
 * Minimal stand-in for Episciences_Paper exercising only the accessors
 * enrichAndGroupRows() reads.
 */
final class FakeVersionedPaper extends \Episciences_Paper
{
    public function __construct(
        private readonly float $version,
        private readonly ?string $conceptIdentifier,
        private readonly ?bool $reviewable = null
    ) {
    }

    public function getVersion(): float
    {
        return $this->version;
    }

    public function getConcept_identifier(): ?string
    {
        return $this->conceptIdentifier;
    }

    public function canBeReviewed(): bool
    {
        return $this->reviewable ?? parent::canBeReviewed();
    }
}
