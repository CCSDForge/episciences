<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\DefaultRequestType;
use AdministratepaperController;
use Episciences\Reviewer\RequestType;
use Episciences_PapersManager;
use Episciences_Reviewer;
use Episciences_ReviewersManager;
use Episciences_User_Assignment as Assignment;
use PHPUnit\Framework\TestCase;

/**
 * A reviewer may hold one request of each type on a paper (#319): the requests in progress are counted, listed and
 * shown by type.
 *
 * @covers AdministratepaperController::reviewersBusyWithEveryType
 * @covers AdministratepaperController::invitationRequestType
 * @covers Episciences_PapersManager::reviewersByRequestStatus
 * @covers Episciences_Reviewer::mostAdvancedByKey
 * @covers Episciences_ReviewersManager::reviewerRequests
 */
final class ReviewerRequestsByTypeTest extends TestCase
{
    private const RVID = 3;
    private const FULL_REPORT = 10;
    private const QUICK_OPINION = 11;
    private const SECOND_ROUND = 12;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php';
    }

    /**
     * @return list<RequestType>
     */
    private function types(): array
    {
        return [
            new RequestType(self::FULL_REPORT, self::RVID, DefaultRequestType::FullReport, ['en' => 'Review report']),
            new RequestType(self::QUICK_OPINION, self::RVID, DefaultRequestType::QuickOpinion, ['en' => 'Quick opinion']),
            new RequestType(self::SECOND_ROUND, self::RVID, null, ['en' => 'Second round']),
        ];
    }

    public function testOnlyTheReviewersBusyWithEveryEnabledTypeAreLeftOut(): void
    {
        $busy = [
            1 => [self::FULL_REPORT, self::QUICK_OPINION, self::SECOND_ROUND],
            2 => [self::FULL_REPORT],
        ];

        self::assertSame([1], AdministratepaperController::reviewersBusyWithEveryType($busy, $this->types()));
        // with the review report as only enabled type, the second reviewer is busy too
        self::assertSame([1, 2], AdministratepaperController::reviewersBusyWithEveryType($busy, [$this->types()[0]]));
    }

    public function testThePostedTypeMustBeAnEnabledTypeOfTheJournal(): void
    {
        $types = $this->types();

        self::assertSame(self::QUICK_OPINION, AdministratepaperController::invitationRequestType((string)self::QUICK_OPINION, $types)?->getId());
        self::assertNull(AdministratepaperController::invitationRequestType('999', $types));
        self::assertNull(AdministratepaperController::invitationRequestType(['11'], $types));
        // a form opened before the types existed posts no type: a review report
        self::assertSame(self::FULL_REPORT, AdministratepaperController::invitationRequestType(null, $types)?->getId());
        self::assertSame(self::FULL_REPORT, AdministratepaperController::invitationRequestType('', $types)?->getId());
    }

    public function testAReviewerIsListedWithTheTypesOfTheSelectedRequestsAndTheirMostAdvancedStatus(): void
    {
        $rows = [
            ['UID' => 1, 'STATUS' => Assignment::STATUS_DECLINED, 'WHEN' => '2026-01-03', 'REQUEST_TYPE_ID' => self::QUICK_OPINION],
            ['UID' => 1, 'STATUS' => Assignment::STATUS_ACTIVE, 'WHEN' => '2026-01-01', 'REQUEST_TYPE_ID' => self::FULL_REPORT],
            ['UID' => 2, 'STATUS' => Assignment::STATUS_PENDING, 'WHEN' => '2026-01-02', 'REQUEST_TYPE_ID' => self::QUICK_OPINION],
            ['UID' => 2, 'STATUS' => Assignment::STATUS_ACTIVE, 'WHEN' => '2026-01-01', 'REQUEST_TYPE_ID' => self::FULL_REPORT],
        ];

        $active = Episciences_PapersManager::reviewersByRequestStatus($rows, [Assignment::STATUS_ACTIVE]);
        self::assertSame([1, 2], array_keys($active));
        // the declined quick opinion does not hide the review report in progress
        self::assertSame([self::FULL_REPORT], $active[1]['REQUEST_TYPE_IDS']);
        self::assertSame(Assignment::STATUS_ACTIVE, $active[1]['STATUS']);

        $inProgress = Episciences_PapersManager::reviewersByRequestStatus($rows, [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        self::assertSame(Assignment::STATUS_ACTIVE, $inProgress[2]['STATUS']);
        self::assertSame('2026-01-02', $inProgress[2]['WHEN']);
        self::assertSame([self::QUICK_OPINION, self::FULL_REPORT], $inProgress[2]['REQUEST_TYPE_IDS']);

        self::assertSame([], Episciences_PapersManager::reviewersByRequestStatus($rows, [Assignment::STATUS_CANCELLED]));
    }

    public function testOneRowPerKeyKeepsThePreferredStatus(): void
    {
        $rows = [
            ['ITEMID' => 5, 'STATUS' => Assignment::STATUS_DECLINED],
            ['ITEMID' => 5, 'STATUS' => Assignment::STATUS_ACTIVE],
            ['ITEMID' => 5, 'STATUS' => Assignment::STATUS_PENDING],
            ['ITEMID' => 6, 'STATUS' => Assignment::STATUS_INACTIVE],
        ];

        $kept = Episciences_Reviewer::mostAdvancedByKey($rows, 'ITEMID', [Assignment::STATUS_ACTIVE, Assignment::STATUS_PENDING]);
        self::assertSame(Assignment::STATUS_ACTIVE, $kept[5]['STATUS']);
        self::assertSame(Assignment::STATUS_INACTIVE, $kept[6]['STATUS']);

        $waitingFirst = Episciences_Reviewer::mostAdvancedByKey($rows, 'ITEMID', [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        self::assertSame(Assignment::STATUS_PENDING, $waitingFirst[5]['STATUS']);
    }

    public function testEachRequestOfAReviewerIsReinvited(): void
    {
        $both = (new Episciences_Reviewer())->setRequestTypeIds([self::FULL_REPORT, self::QUICK_OPINION]);
        $unknown = new Episciences_Reviewer();

        $requests = Episciences_ReviewersManager::reviewerRequests([7 => $both, 8 => $unknown]);

        self::assertCount(3, $requests);
        self::assertSame([$both, self::FULL_REPORT], $requests[0]);
        self::assertSame([$both, self::QUICK_OPINION], $requests[1]);
        self::assertSame([$unknown, null], $requests[2]);
    }
}
