<?php

declare(strict_types=1);

namespace unit\library\Episciences\Rating;

use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Merge_MergingManager;
use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_Rating_ReportManager;
use Episciences_Reviewer;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Rating reports by review request type (#319): a reviewer may have one report of each type on a paper version.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Rating_Report
 * @covers Episciences_Rating_ReportManager::deleteByUidAndDocId
 * @covers Episciences_Reviewer::getReport
 * @covers Episciences_Reviewer::hasCompletedEveryReport
 * @covers Episciences_Merge_MergingManager::reportKeyParts
 * @covers Episciences_Paper::reportKey
 * @covers Episciences_Paper::countedReportsOf
 * @covers \Episciences\Reviewer\RequestTypeRepository::countsAsReportId
 */
final class Episciences_Rating_ReportRequestTypeTest extends TestCase
{
    private const DOCID = 999601;
    private const UID = 999602;

    private Zend_Db_Adapter_Abstract $db;
    private int $fullReport;
    private int $quickOpinion;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
        RequestTypeRepository::clearCache();

        $repository = new RequestTypeRepository($this->db);
        $repository->createDefaults(RVID);
        $this->fullReport = (int)$repository->findFullReport(RVID)?->getId();
        $this->quickOpinion = (int)$repository->findByCode(RVID, 'quick_opinion')?->getId();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        RequestTypeRepository::clearCache();
    }

    private function report(int $requestTypeId, int $status): void
    {
        $this->db->insert(T_REVIEWER_REPORTS, [
            'UID' => self::UID,
            'DOCID' => self::DOCID,
            'REQUEST_TYPE_ID' => $requestTypeId,
            'STATUS' => $status,
            'CREATION_DATE' => '2026-01-01 10:00:00',
        ]);
    }

    private function reportObject(?int $requestTypeId): Episciences_Rating_Report
    {
        $report = new Episciences_Rating_Report();
        $report->setRvid(RVID);
        $report->setRequest_type_id($requestTypeId);

        return $report;
    }

    public function testTheReportOfEachTypeIsFoundAndTheReviewReportByDefault(): void
    {
        $this->report($this->fullReport, Episciences_Rating_Report::STATUS_COMPLETED);
        $this->report($this->quickOpinion, Episciences_Rating_Report::STATUS_WIP);

        $quick = Episciences_Rating_Report::find(self::DOCID, self::UID, $this->quickOpinion);
        $default = Episciences_Rating_Report::find(self::DOCID, self::UID);

        self::assertInstanceOf(Episciences_Rating_Report::class, $quick);
        self::assertSame(Episciences_Rating_Report::STATUS_WIP, (int)$quick->getStatus());
        self::assertSame($this->quickOpinion, $quick->getRequest_type_id());
        self::assertInstanceOf(Episciences_Rating_Report::class, $default);
        self::assertSame($this->fullReport, $default->getRequest_type_id());
        self::assertFalse(Episciences_Rating_Report::find(self::DOCID, self::UID, 999999));
    }

    public function testWithoutAReviewReportTheDefaultSearchFindsNothing(): void
    {
        $this->report($this->quickOpinion, Episciences_Rating_Report::STATUS_WIP);

        self::assertFalse(Episciences_Rating_Report::find(self::DOCID, self::UID));
    }

    public function testTheReportsOfAReviewerAreKeyedByPaperAndType(): void
    {
        $this->report($this->fullReport, Episciences_Rating_Report::STATUS_COMPLETED);
        $this->report($this->quickOpinion, Episciences_Rating_Report::STATUS_WIP);

        $keys = array_keys(Episciences_Rating_Report::findByUid(self::UID));

        self::assertEqualsCanonicalizing([self::DOCID, self::DOCID . '_' . $this->quickOpinion], $keys);
        self::assertSame([self::DOCID, ''], Episciences_Merge_MergingManager::reportKeyParts((string)self::DOCID));
        self::assertSame([self::DOCID, '_' . $this->quickOpinion], Episciences_Merge_MergingManager::reportKeyParts(self::DOCID . '_' . $this->quickOpinion));
    }

    public function testOnlyTheReportOfTheGivenTypeIsDeleted(): void
    {
        $this->report($this->fullReport, Episciences_Rating_Report::STATUS_WIP);
        $this->report($this->quickOpinion, Episciences_Rating_Report::STATUS_WIP);

        self::assertSame(1, Episciences_Rating_ReportManager::deleteByUidAndDocId(self::UID, self::DOCID, $this->quickOpinion));

        self::assertFalse(Episciences_Rating_Report::find(self::DOCID, self::UID, $this->quickOpinion));
        self::assertNotFalse(Episciences_Rating_Report::find(self::DOCID, self::UID, $this->fullReport));
    }

    public function testAReviewerHasCompletedTheirReportsOnlyWhenEachRequestIsCompleted(): void
    {
        $this->report($this->fullReport, Episciences_Rating_Report::STATUS_COMPLETED);
        $this->report($this->quickOpinion, Episciences_Rating_Report::STATUS_WIP);
        $reviewer = (new Episciences_Reviewer())->setRequestTypeIds([$this->fullReport, $this->quickOpinion]);
        $reviewer->setUid(self::UID);

        self::assertFalse($reviewer->hasCompletedEveryReport(self::DOCID));

        $reviewer->setRequestTypeIds([$this->fullReport]);
        self::assertTrue($reviewer->hasCompletedEveryReport(self::DOCID));
    }

    public function testTheReviewReportIsTheReportWithoutTypeOrOfTheReviewReportType(): void
    {
        self::assertTrue($this->reportObject(null)->isReviewReport());
        self::assertTrue($this->reportObject(0)->isReviewReport());
        self::assertTrue($this->reportObject($this->fullReport)->isReviewReport());
        self::assertFalse($this->reportObject($this->quickOpinion)->isReviewReport());
    }

    public function testTheReportsOfATypeSetToCountAreCountedToo(): void
    {
        $phase = (int)(new RequestTypeRepository($this->db))->save(
            new RequestType(null, RVID, 'phase_two', ['en' => 'Phase two'], false, null, 3, true, [], true)
        )->getId();
        $report = $this->reportObject($phase);
        $report->setUid(self::UID);
        $quick = $this->reportObject($this->quickOpinion);
        $quick->setUid(self::UID);

        self::assertFalse($report->isReviewReport(), 'its files stay apart from the review report');
        self::assertTrue($report->countsAsReviewReport());
        self::assertFalse($quick->countsAsReviewReport());
        self::assertSame(['a' => $report], Episciences_Paper::countedReportsOf(['a' => $report, 'b' => $quick]));
    }

    public function testOnlyTheReviewReportsCountAndEachReportHasItsOwnKey(): void
    {
        $review = $this->reportObject($this->fullReport);
        $review->setUid(self::UID);
        $quick = $this->reportObject($this->quickOpinion);
        $quick->setUid(self::UID);

        self::assertSame(self::UID, Episciences_Paper::reportKey($review));
        self::assertSame(self::UID . '_' . $this->quickOpinion, Episciences_Paper::reportKey($quick));

        $reports = [Episciences_Paper::reportKey($review) => $review, Episciences_Paper::reportKey($quick) => $quick];
        self::assertSame([self::UID => $review], Episciences_Paper::countedReportsOf($reports));
    }
}
