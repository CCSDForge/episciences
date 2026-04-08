<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Rating_Report;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::filterReportsByReviewer() and
 * Episciences_Paper::filterReportsByStatus().
 *
 * Both methods are pure array-filtering functions: they accept an array of
 * Episciences_Rating_Report objects and return a filtered subset.
 * No DB, no auth, no global state involved.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_FilterReportsTest extends TestCase
{
    private Episciences_Paper $paper;

    protected function setUp(): void
    {
        $this->paper = new Episciences_Paper();
    }

    // -----------------------------------------------------------------------
    // filterReportsByReviewer()
    // -----------------------------------------------------------------------

    public function testFilterReportsByReviewerReturnsMatchingReports(): void
    {
        $r1 = $this->buildReport(uid: 10, status: Episciences_Rating_Report::STATUS_COMPLETED);
        $r2 = $this->buildReport(uid: 20, status: Episciences_Rating_Report::STATUS_COMPLETED);
        $r3 = $this->buildReport(uid: 10, status: Episciences_Rating_Report::STATUS_WIP);

        $result = $this->paper->filterReportsByReviewer([$r1, $r2, $r3], 10);

        self::assertCount(2, $result);
        foreach ($result as $report) {
            self::assertSame(10, $report->getUid());
        }
    }

    public function testFilterReportsByReviewerReturnsEmptyArrayWhenNoMatch(): void
    {
        $r1 = $this->buildReport(uid: 10, status: Episciences_Rating_Report::STATUS_COMPLETED);
        $r2 = $this->buildReport(uid: 20, status: Episciences_Rating_Report::STATUS_COMPLETED);

        $result = $this->paper->filterReportsByReviewer([$r1, $r2], 99);

        self::assertCount(0, $result);
    }

    public function testFilterReportsByReviewerWithEmptyInputReturnsEmptyArray(): void
    {
        $result = $this->paper->filterReportsByReviewer([], 10);
        self::assertSame([], $result);
    }

    public function testFilterReportsByReviewerKeepsOnlyExactUidMatch(): void
    {
        // uid comparison uses loose == in source; test with numeric string
        $r1 = $this->buildReport(uid: 5, status: Episciences_Rating_Report::STATUS_COMPLETED);
        $r2 = $this->buildReport(uid: 50, status: Episciences_Rating_Report::STATUS_COMPLETED);

        $result = $this->paper->filterReportsByReviewer([$r1, $r2], 5);

        self::assertCount(1, $result);
        self::assertSame(5, reset($result)->getUid());
    }

    public function testFilterReportsByReviewerReturnsAllWhenAllMatchSameUid(): void
    {
        $reports = [
            $this->buildReport(uid: 7, status: Episciences_Rating_Report::STATUS_WIP),
            $this->buildReport(uid: 7, status: Episciences_Rating_Report::STATUS_COMPLETED),
            $this->buildReport(uid: 7, status: Episciences_Rating_Report::STATUS_PENDING),
        ];

        $result = $this->paper->filterReportsByReviewer($reports, 7);

        self::assertCount(3, $result);
    }

    // -----------------------------------------------------------------------
    // filterReportsByStatus()
    // -----------------------------------------------------------------------

    public function testFilterReportsByStatusReturnsMatchingReports(): void
    {
        $r1 = $this->buildReport(uid: 1, status: Episciences_Rating_Report::STATUS_COMPLETED);
        $r2 = $this->buildReport(uid: 2, status: Episciences_Rating_Report::STATUS_WIP);
        $r3 = $this->buildReport(uid: 3, status: Episciences_Rating_Report::STATUS_COMPLETED);

        $result = $this->paper->filterReportsByStatus([$r1, $r2, $r3], Episciences_Rating_Report::STATUS_COMPLETED);

        self::assertCount(2, $result);
        foreach ($result as $report) {
            self::assertSame(Episciences_Rating_Report::STATUS_COMPLETED, $report->getStatus());
        }
    }

    public function testFilterReportsByStatusReturnsEmptyArrayWhenNoMatch(): void
    {
        $r1 = $this->buildReport(uid: 1, status: Episciences_Rating_Report::STATUS_WIP);
        $r2 = $this->buildReport(uid: 2, status: Episciences_Rating_Report::STATUS_WIP);

        $result = $this->paper->filterReportsByStatus([$r1, $r2], Episciences_Rating_Report::STATUS_COMPLETED);

        self::assertCount(0, $result);
    }

    public function testFilterReportsByStatusWithEmptyInputReturnsEmptyArray(): void
    {
        $result = $this->paper->filterReportsByStatus([], Episciences_Rating_Report::STATUS_COMPLETED);
        self::assertSame([], $result);
    }

    public function testFilterReportsByStatusPendingReturnsOnlyPending(): void
    {
        $pending   = $this->buildReport(uid: 1, status: Episciences_Rating_Report::STATUS_PENDING);
        $wip       = $this->buildReport(uid: 2, status: Episciences_Rating_Report::STATUS_WIP);
        $completed = $this->buildReport(uid: 3, status: Episciences_Rating_Report::STATUS_COMPLETED);

        $result = $this->paper->filterReportsByStatus([$pending, $wip, $completed], Episciences_Rating_Report::STATUS_PENDING);

        self::assertCount(1, $result);
        self::assertSame(Episciences_Rating_Report::STATUS_PENDING, reset($result)->getStatus());
    }

    public function testFilterReportsByStatusReturnsAllWhenAllMatchSameStatus(): void
    {
        $reports = [
            $this->buildReport(uid: 1, status: Episciences_Rating_Report::STATUS_WIP),
            $this->buildReport(uid: 2, status: Episciences_Rating_Report::STATUS_WIP),
        ];

        $result = $this->paper->filterReportsByStatus($reports, Episciences_Rating_Report::STATUS_WIP);

        self::assertCount(2, $result);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildReport(int $uid, int $status): Episciences_Rating_Report
    {
        $report = new Episciences_Rating_Report();
        $report->setUid($uid);
        $report->setStatus($status);
        return $report;
    }
}
