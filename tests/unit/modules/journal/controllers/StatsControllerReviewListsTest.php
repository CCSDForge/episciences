<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StatsController;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Lists of the reviews of the evaluation figures (StatsController): one row by invitation or by report, given by the
 * API without the reviewers.
 */
final class StatsControllerReviewListsTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var MockObject&Zend_Db_Adapter_Abstract $adapter */
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll'])
            ->getMock();
        // the papers of the journal: DOCID 201 and 203 (204 is no longer in the journal)
        $adapter->method('fetchAll')->willReturn([
            ['DOCID' => 201, 'PAPERID' => 1, 'RVID' => RVID, 'STATUS' => \Episciences_Paper::STATUS_BEING_REVIEWED, 'FLAG' => 'submitted', 'SUBMISSION_DATE' => '2024-01-10 10:00:00'],
            ['DOCID' => 203, 'PAPERID' => 3, 'RVID' => RVID, 'STATUS' => \Episciences_Paper::STATUS_REFUSED, 'FLAG' => 'submitted', 'SUBMISSION_DATE' => '2024-02-10 10:00:00'],
        ]);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private static function evaluations(): array
    {
        return [
            ['name' => 'reviews-requested', 'value' => 4, 'reviews' => [
                ['docid' => 201, 'date' => '2024-03-01', 'answer' => 'accepted'],
                ['docid' => 201, 'date' => '2024-03-02', 'answer' => 'declined'],
                ['docid' => 203, 'date' => '2024-05-02', 'answer' => 'expired'],
                ['docid' => 0, 'date' => '2024-05-02', 'answer' => 'pending'],
            ], 'hiddenReviews' => 1],
            ['name' => 'reviews-received', 'value' => 1, 'reviews' => [['docid' => 201, 'date' => '2024-03-20']], 'hiddenReviews' => 0],
            ['name' => 'median-reviews-number', 'value' => 1],
        ];
    }

    public function testReviewListsOfTheRequestedAndReceivedReviews(): void
    {
        $lists = StatsController::getReviewLists(self::evaluations());

        self::assertSame([StatsController::REVIEWS_REQUESTED, StatsController::REVIEWS_RECEIVED], array_keys($lists));
        self::assertSame([201, 203], $lists[StatsController::REVIEWS_REQUESTED]['docIds']);
        self::assertCount(3, $lists[StatsController::REVIEWS_REQUESTED]['reviews'], 'an invalid DOCID is left out');
        self::assertSame(1, $lists[StatsController::REVIEWS_REQUESTED]['hidden']);
        self::assertSame([['docid' => 201, 'date' => '2024-03-20']], $lists[StatsController::REVIEWS_RECEIVED]['reviews']);
    }

    public function testNoListWithoutTheReviewsOfTheApi(): void
    {
        self::assertSame([], StatsController::getReviewLists([['name' => 'reviews-requested', 'value' => 4], ['name' => 'reviews-received', 'value' => 1]]));
    }

    /**
     * The page counts the reviews (several by paper), not the papers.
     */
    public function testTheReviewsAreCounted(): void
    {
        $lists = StatsController::getReviewLists(self::evaluations());
        $counts = StatsController::countPaperLists($lists, [StatsController::REVIEWS_REQUESTED => 4, StatsController::REVIEWS_RECEIVED => 1]);

        self::assertSame(['figure' => StatsController::REVIEWS_REQUESTED, 'count' => 3, 'hidden' => 1, 'incomplete' => false, 'reviews' => true], $counts[StatsController::REVIEWS_REQUESTED]);
        self::assertSame(1, $counts[StatsController::REVIEWS_RECEIVED]['count']);
    }

    public function testARowByInvitationWithItsPaperDateAndAnswer(): void
    {
        $lists = StatsController::getReviewLists(self::evaluations());
        $rows = StatsController::getReviewRows($lists[StatsController::REVIEWS_REQUESTED]['reviews'], static fn(string $text): string => '[' . $text . ']');

        self::assertSame([201, 201, 203], array_column($rows, 'docid'));
        self::assertSame(['2024-03-01', '2024-03-02', '2024-05-02'], array_column($rows, StatsController::COLUMN_INVITATION));
        // the answer, translated
        self::assertSame(['[acceptée]', '[déclinée]', '[expirée]'], array_column($rows, StatsController::COLUMN_ANSWER));
        self::assertArrayNotHasKey(StatsController::COLUMN_REPORT, $rows[0]);
        // no reviewer
        self::assertArrayNotHasKey('uid', $rows[0]);
    }

    public function testARowByReportWithItsDate(): void
    {
        $rows = StatsController::getReviewRows([['docid' => 201, 'date' => '2024-03-20'], ['docid' => 204, 'date' => '2024-04-20']], static fn(string $text): string => $text);

        // the version no longer in the journal is not listed
        self::assertCount(1, $rows);
        self::assertSame('2024-03-20', $rows[0][StatsController::COLUMN_REPORT]);
        self::assertArrayNotHasKey(StatsController::COLUMN_ANSWER, $rows[0]);
    }
}
