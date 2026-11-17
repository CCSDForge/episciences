<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper;
use StatsController;
use unit\modules\ControllerTestCase;

/**
 * The submission date of the papers listed by the statistics page is the date of the first version of each
 * submission, as the API counts the submissions: the new versions of a paper do not always keep the date of its first
 * version (old data), and a refused paper submitted again is a new submission.
 */
final class StatsControllerFirstSubmissionDateTest extends ControllerTestCase
{
    /**
     * Versions of the journal: paper 1 (v1 2016, v2 and v3 2017), paper 2 (refused in 2016, then submitted again in
     * 2018), paper 3 (only an obsolete version)
     */
    private const array VERSIONS = [
        ['DOCID' => 10, 'PAPERID' => 1, 'STATUS' => Episciences_Paper::STATUS_OBSOLETE, 'SUBMISSION_DATE' => '2016-06-24 10:00:00'],
        ['DOCID' => 15, 'PAPERID' => 1, 'STATUS' => Episciences_Paper::STATUS_OBSOLETE, 'SUBMISSION_DATE' => '2017-01-17 10:00:00'],
        ['DOCID' => 20, 'PAPERID' => 1, 'STATUS' => Episciences_Paper::STATUS_PUBLISHED, 'SUBMISSION_DATE' => '2017-03-14 10:00:00'],
        ['DOCID' => 11, 'PAPERID' => 2, 'STATUS' => Episciences_Paper::STATUS_REFUSED, 'SUBMISSION_DATE' => '2016-10-03 10:00:00'],
        ['DOCID' => 30, 'PAPERID' => 2, 'STATUS' => Episciences_Paper::STATUS_OBSOLETE, 'SUBMISSION_DATE' => '2018-02-01 10:00:00'],
        ['DOCID' => 31, 'PAPERID' => 2, 'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED, 'SUBMISSION_DATE' => '2018-05-01 10:00:00'],
        ['DOCID' => 40, 'PAPERID' => 3, 'STATUS' => Episciences_Paper::STATUS_OBSOLETE, 'SUBMISSION_DATE' => '2019-04-02 10:00:00'],
    ];

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'StatsController');
    }

    protected function setUp(): void
    {
        $this->givenJournal();
        // the versions of the papers (PAPERID IN), or the listed versions (DOCID IN)
        $this->db->respondTo('/FROM `PAPERS`/', static function (string $sql): array {
            if (str_contains($sql, 'PAPERID IN')) {
                return self::VERSIONS;
            }

            preg_match('/DOCID IN \(([^)]*)\)/', $sql, $matches);
            $docIds = array_map('intval', explode(',', $matches[1] ?? ''));

            return array_map(
                static fn(array $version): array => $version + ['RVID' => RVID, 'FLAG' => 'submitted'],
                array_values(array_filter(self::VERSIONS, static fn(array $version): bool => in_array($version['DOCID'], $docIds, true)))
            );
        });
    }

    public function testTheDateOfTheFirstVersionOfEachSubmission(): void
    {
        self::assertSame(
            [
                10 => '2016-06-24 10:00:00', 15 => '2016-06-24 10:00:00', 20 => '2016-06-24 10:00:00',
                11 => '2016-10-03 10:00:00', 30 => '2018-02-01 10:00:00', 31 => '2018-02-01 10:00:00',
                40 => '2019-04-02 10:00:00',
            ],
            StatsController::getFirstSubmissionDates([1, 2, 2, 3, 0])
        );
    }

    public function testTheVersionsAreReadInTheJournal(): void
    {
        StatsController::getFirstSubmissionDates([1, 2]);

        $queries = array_column($this->db->queries(), 'sql');
        self::assertCount(1, $queries);
        self::assertStringContainsString('(RVID = ' . RVID . ')', $queries[0]);
        self::assertStringContainsString('(PAPERID IN (1, 2))', $queries[0]);
        self::assertStringContainsString('ORDER BY `PAPERID` ASC, `DOCID` ASC', $queries[0]);
    }

    public function testWithoutPaperNothingIsRead(): void
    {
        self::assertSame([], StatsController::getFirstSubmissionDates([0, -1, 'abc']));
        self::assertSame([], $this->db->queries());
    }

    /**
     * The listed papers are dated by their first submission, not by the date of the listed version; the refused paper
     * and its new submission keep their own dates
     */
    public function testTheListedPapersAreDatedByTheirFirstSubmission(): void
    {
        $rows = StatsController::getPaperRows([20, 11, 31], [StatsController::COLUMN_SUBMISSION]);
        $dates = array_column($rows, StatsController::COLUMN_SUBMISSION, 'docid');
        ksort($dates);

        self::assertSame([11 => '2016-10-03', 20 => '2016-06-24', 31 => '2018-02-01'], $dates);
    }

    /**
     * A listed version which became obsolete (a new version was submitted since the list was built) keeps the date of
     * the first version of its submission, not its own date
     */
    public function testAListedObsoleteVersionIsDatedByItsFirstSubmission(): void
    {
        $rows = StatsController::getPaperRows([15, 30], [StatsController::COLUMN_SUBMISSION]);
        $dates = array_column($rows, StatsController::COLUMN_SUBMISSION, 'docid');
        ksort($dates);

        self::assertSame([15 => '2016-06-24', 30 => '2018-02-01'], $dates);
    }

    public function testWithoutSubmissionColumnTheVersionsAreNotRead(): void
    {
        StatsController::getPaperRows([20], []);

        self::assertCount(1, array_column($this->db->queries(), 'sql'));
    }
}
