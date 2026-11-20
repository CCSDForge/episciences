<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\PreviousVersionReviewers;
use Episciences_Paper;
use Episciences_Rating_Report;
use PHPUnit\Framework\MockObject\MockObject;
use unit\modules\ControllerTestCase;

/**
 * Reviewers of the previous versions of a paper (git #1387).
 *
 * Extends ControllerTestCase for its database stub.
 */
final class PreviousVersionReviewersTest extends ControllerTestCase
{
    private const PAPER_ID = 10;

    /**
     * @return MockObject&Episciences_Paper
     */
    private function version(int $docId, string $when = '2026-06-01 10:00:00'): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(self::PAPER_ID);
        $paper->method('getDocid')->willReturn($docId);
        $paper->method('getRvid')->willReturn(RVID);
        $paper->method('getWhen')->willReturn($when);

        return $paper;
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewer(int $docId, int $uid, string $status, ?int $reportStatus = null, array $person = []): array
    {
        return $person + [
            'ITEMID' => $docId, 'UID' => $uid, 'TMP_USER' => 0, 'STATUS' => $status, 'WHEN' => '2026-02-01 10:00:00',
            'REPORT_STATUS' => $reportStatus, 'REPORT_DATE' => $reportStatus === null ? null : '2026-03-01 10:00:00',
            'FIRSTNAME' => 'First' . $uid, 'LASTNAME' => 'Last' . $uid, 'EMAIL' => "r$uid@example.org",
            'SCREEN_NAME' => "R$uid", 'USERNAME' => "r$uid", 'LANGUEID' => 'en',
        ];
    }

    public function testWhatBecameOfEachRequest(): void
    {
        $rows = PreviousVersionReviewers::fromRows(
            [['DOCID' => 10, 'VERSION' => 1, 'REPOID' => 1, 'WHEN' => '2026-01-01 10:00:00']],
            [
                self::reviewer(10, 1, 'active', Episciences_Rating_Report::STATUS_COMPLETED),
                self::reviewer(10, 2, 'active', Episciences_Rating_Report::STATUS_WIP),
                self::reviewer(10, 3, 'active'),
                self::reviewer(10, 4, 'declined'),
                self::reviewer(10, 5, 'expired'),
                self::reviewer(10, 6, 'cancelled'),
                self::reviewer(10, 7, 'pending'),
            ]
        );

        self::assertSame([
            PreviousVersionReviewers::STATUS_REPORT_COMPLETED,
            PreviousVersionReviewers::STATUS_REPORT_IN_PROGRESS,
            PreviousVersionReviewers::STATUS_ACCEPTED,
            PreviousVersionReviewers::STATUS_DECLINED,
            PreviousVersionReviewers::STATUS_EXPIRED,
            PreviousVersionReviewers::STATUS_CANCELLED,
            PreviousVersionReviewers::STATUS_NOT_ANSWERED,
        ], array_column($rows, 'status'));
        // the date of the report when there is one
        self::assertSame('2026-03-01 10:00:00', $rows[0]['date']);
        self::assertSame('2026-02-01 10:00:00', $rows[3]['date']);
    }

    public function testLatestVersionFirstThenByName(): void
    {
        $rows = PreviousVersionReviewers::fromRows(
            [
                ['DOCID' => 20, 'VERSION' => 2, 'REPOID' => 0, 'WHEN' => '2026-04-01 10:00:00'],
                ['DOCID' => 10, 'VERSION' => 1, 'REPOID' => 1, 'WHEN' => '2026-01-01 10:00:00'],
            ],
            [
                self::reviewer(10, 1, 'active', null, ['LASTNAME' => 'Zed']),
                self::reviewer(20, 2, 'active', null, ['LASTNAME' => 'Bee', 'TMP_USER' => 1]),
                self::reviewer(10, 3, 'active', null, ['LASTNAME' => 'Ada']),
            ]
        );

        self::assertSame([[20, 'Bee'], [10, 'Ada'], [10, 'Zed']], array_map(static fn(array $row): array => [$row['docid'], $row['lastname']], $rows));
        self::assertTrue($rows[0]['tmp_version']);
        self::assertTrue($rows[0]['tmp_user']);
        self::assertFalse($rows[1]['tmp_version']);
    }

    public function testTheFirstVersionHasNoPreviousReviewersWithoutQuery(): void
    {
        self::assertSame([], PreviousVersionReviewers::ofPaper($this->version(self::PAPER_ID)));
        self::assertSame([], $this->db->queries());
    }

    public function testThePreviousVersionsOfTheJournalAreRead(): void
    {
        $this->db
            ->respondTo('/FROM `PAPERS`/', [['DOCID' => 10, 'VERSION' => 1, 'REPOID' => 1, 'WHEN' => '2026-01-01 10:00:00']])
            ->respondTo('/FROM `USER_ASSIGNMENT`/', [self::reviewer(10, 1, 'active', Episciences_Rating_Report::STATUS_COMPLETED)]);

        $rows = PreviousVersionReviewers::ofPaper($this->version(30));

        self::assertCount(1, $rows);
        [$versions, $reviewers] = $this->db->selects();
        self::assertStringContainsString('PAPERID = ' . self::PAPER_ID, $versions);
        self::assertStringContainsString('RVID = ' . RVID, $versions);
        self::assertStringContainsString("`WHEN` < '2026-06-01 10:00:00'", $versions);
        // the rows added when a new version replaces the paper say nothing about the request
        self::assertStringContainsString("STATUS != 'inactive'", $reviewers);
        self::assertStringContainsString('ITEMID IN (10)', $reviewers);
    }

    public function testOnlyAPreviousVersionOfTheSamePaperIsAccepted(): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', static fn(string $sql): array => str_contains($sql, 'DOCID = 10') ? [['DOCID' => 10]] : []);
        $current = $this->version(30);

        self::assertSame(10, PreviousVersionReviewers::previousVersionOf($current, '10'));
        self::assertNull(PreviousVersionReviewers::previousVersionOf($current, '11'));
        self::assertNull(PreviousVersionReviewers::previousVersionOf($current, 30));
        self::assertNull(PreviousVersionReviewers::previousVersionOf($current, 'x'));
        self::assertNull(PreviousVersionReviewers::previousVersionOf($current, null));

        $sql = $this->db->selects()[0];
        self::assertStringContainsString('PAPERID = ' . self::PAPER_ID, $sql);
        self::assertStringContainsString('RVID = ' . RVID, $sql);
        self::assertStringContainsString("`WHEN` < '2026-06-01 10:00:00'", $sql);
    }
}
