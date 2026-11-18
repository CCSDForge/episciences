<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_User_Assignment as Assignment;
use Episciences_User_AssignmentsManager;
use Episciences_UsersManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * A reviewer may hold one request of each type on a paper (#319): the assignment rows carry the type, and the latest
 * row is read for each request, so a request never hides another one.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getReviewers
 * @covers Episciences_User_AssignmentsManager::getList
 * @covers Episciences_User_AssignmentsManager::currentReviewerRequestTypeIds
 * @covers Episciences_User_AssignmentsManager::requestTypesInProgressByReviewer
 * @covers Episciences_Paper::unassign
 * @covers Episciences_UsersManager::assign
 * @covers Episciences_User_Assignment::save
 */
final class Episciences_ReviewRequestTypeAssignmentsTest extends TestCase
{
    private const DOCID = 999401;
    private const UID = 999402;

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
        Episciences_User_AssignmentsManager::getCachePool()->clear();

        $repository = new RequestTypeRepository($this->db);
        $repository->createDefaults(RVID);
        $this->fullReport = (int)$repository->findFullReport(RVID)?->getId();
        $this->quickOpinion = (int)$repository->findByCode(RVID, 'quick_opinion')?->getId();

        $this->db->insert(T_USERS, [
            'UID' => self::UID,
            'uuid' => sprintf('00000000-0000-0000-0000-%012d', self::UID),
            'SCREEN_NAME' => 'Test Reviewer',
            'USERNAME' => 'request-type-test-' . self::UID,
            'API_PASSWORD' => '',
            'EMAIL' => 'request-type-test@example.org',
            'LASTNAME' => 'Reviewer',
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        Episciences_User_AssignmentsManager::getCachePool()->clear();
    }

    /**
     * Rows of one request, in this order (increasing IDs), all in the same second
     * @param string[] $statuses
     */
    private function request(int $requestTypeId, array $statuses): void
    {
        foreach ($statuses as $status) {
            $this->db->insert(T_ASSIGNMENTS, [
                'RVID' => RVID,
                'ITEMID' => self::DOCID,
                'ITEM' => Assignment::ITEM_PAPER,
                'UID' => self::UID,
                'TMP_USER' => 0,
                'ROLEID' => Assignment::ROLE_REVIEWER,
                'REQUEST_TYPE_ID' => $requestTypeId,
                'STATUS' => $status,
                'WHEN' => '2026-01-01 10:00:00',
            ]);
        }
    }

    private function paper(): Episciences_Paper
    {
        return (new Episciences_Paper())->setDocid(self::DOCID)->setRvid(RVID);
    }

    /**
     * @return array<int, string> status of the latest row of each request, by request type
     */
    private function latestStatusByType(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT REQUEST_TYPE_ID, STATUS FROM ' . T_ASSIGNMENTS . ' WHERE ITEMID = ? AND UID = ? ORDER BY ID',
            [self::DOCID, self::UID]
        );

        return array_column($rows, 'STATUS', 'REQUEST_TYPE_ID');
    }

    public function testADeclinedQuickOpinionDoesNotHideTheReviewReportInProgress(): void
    {
        $this->request($this->fullReport, [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        $this->request($this->quickOpinion, [Assignment::STATUS_PENDING, Assignment::STATUS_DECLINED]);

        $reviewers = Episciences_PapersManager::getReviewers(self::DOCID, [Assignment::STATUS_ACTIVE]);

        self::assertArrayHasKey(self::UID, $reviewers);
        self::assertSame(Assignment::STATUS_ACTIVE, $reviewers[self::UID]->getStatus());
        self::assertSame([$this->fullReport], $reviewers[self::UID]->getRequestTypeIds());
    }

    public function testThePendingRequestOfAReviewerAlreadyActiveForAnotherTypeIsFound(): void
    {
        $this->request($this->fullReport, [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        $this->request($this->quickOpinion, [Assignment::STATUS_PENDING]);

        $pending = Episciences_PapersManager::getReviewers(self::DOCID, [Assignment::STATUS_PENDING]);

        self::assertSame([$this->quickOpinion], $pending[self::UID]->getRequestTypeIds());
        self::assertSame(
            [$this->fullReport, $this->quickOpinion],
            Episciences_User_AssignmentsManager::currentReviewerRequestTypeIds(self::UID, self::DOCID)
        );
    }

    public function testTheReviewerAssignmentsKeepThePaperOfTheRequestInProgress(): void
    {
        $this->request($this->fullReport, [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        $this->request($this->quickOpinion, [Assignment::STATUS_PENDING, Assignment::STATUS_DECLINED]);

        $assignments = Episciences_User_AssignmentsManager::getList([
            'item' => Assignment::ITEM_PAPER,
            'roleid' => Assignment::ROLE_REVIEWER,
            'uid' => self::UID,
            'tmp_user' => 0,
            'status' => [Assignment::STATUS_ACTIVE, Assignment::STATUS_PENDING],
        ]);

        self::assertArrayHasKey(self::DOCID, $assignments);
        self::assertSame(Assignment::STATUS_ACTIVE, $assignments[self::DOCID]->getStatus());
        self::assertSame($this->fullReport, $assignments[self::DOCID]->getRequest_type_id());
    }

    public function testAPendingRequestWhoseInvitationHasExpiredNoLongerBlocksANewInvitation(): void
    {
        $this->db->insert(T_USER_INVITATIONS, [
            'AID' => 0, 'STATUS' => 'pending', 'SENDING_DATE' => '2026-01-01 10:00:00', 'SENDER_UID' => 1,
            'EXPIRATION_DATE' => '2026-01-02 10:00:00',
        ]);
        $expiredInvitation = (int)$this->db->lastInsertId();
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => RVID, 'ITEMID' => self::DOCID, 'ITEM' => Assignment::ITEM_PAPER, 'UID' => self::UID, 'TMP_USER' => 0,
            'ROLEID' => Assignment::ROLE_REVIEWER, 'REQUEST_TYPE_ID' => $this->quickOpinion, 'STATUS' => Assignment::STATUS_PENDING,
            'WHEN' => '2026-01-01 10:00:00', 'INVITATION_ID' => $expiredInvitation,
        ]);
        $this->request($this->fullReport, [Assignment::STATUS_ACTIVE]);

        self::assertSame(
            [$this->fullReport],
            Episciences_User_AssignmentsManager::currentReviewerRequestTypeIds(self::UID, self::DOCID, false, false)
        );
        self::assertSame(
            [self::UID => [$this->fullReport]],
            Episciences_User_AssignmentsManager::requestTypesInProgressByReviewer(self::DOCID)
        );
        // still a request to end when the reviewer is unassigned
        self::assertContains($this->quickOpinion, Episciences_User_AssignmentsManager::currentReviewerRequestTypeIds(self::UID, self::DOCID));
    }

    public function testUnassigningAReviewerEndsEachRequestInProgressOnly(): void
    {
        // a type created by the journal, besides the two default types
        $secondRound = (int)(new RequestTypeRepository($this->db))->save(new RequestType(null, RVID, 'second_round', ['en' => 'Second round']))->getId();
        $this->request($this->fullReport, [Assignment::STATUS_PENDING, Assignment::STATUS_ACTIVE]);
        $this->request($this->quickOpinion, [Assignment::STATUS_PENDING]);
        $this->request($secondRound, [Assignment::STATUS_PENDING, Assignment::STATUS_DECLINED]);

        $this->paper()->unassign(self::UID, Assignment::ROLE_REVIEWER);

        self::assertSame([
            $this->fullReport => Assignment::STATUS_INACTIVE,
            $this->quickOpinion => Assignment::STATUS_INACTIVE,
            // the declined request keeps its status
            $secondRound => Assignment::STATUS_DECLINED,
        ], $this->latestStatusByType());
        self::assertSame([], Episciences_User_AssignmentsManager::currentReviewerRequestTypeIds(self::UID, self::DOCID));
    }

    public function testAReviewerAssignmentWithoutATypeIsAReviewReportAndIsReported(): void
    {
        $previousLogger = Zend_Registry::isRegistered('appLogger') ? Zend_Registry::get('appLogger') : null;
        $log = new TestHandler();
        Zend_Registry::set('appLogger', new Logger('test', [$log]));

        try {
            Episciences_UsersManager::assign(self::UID, [
                'rvid' => RVID,
                'itemid' => self::DOCID,
                'item' => Assignment::ITEM_PAPER,
                'roleid' => Assignment::ROLE_REVIEWER,
                'status' => Assignment::STATUS_PENDING,
            ]);
        } finally {
            Zend_Registry::set('appLogger', $previousLogger);
        }

        self::assertSame([$this->fullReport => Assignment::STATUS_PENDING], $this->latestStatusByType());
        // a missing type is a call site to fix: it is reported
        self::assertTrue($log->hasWarningThatContains('without a request type'));
    }

    public function testTheGivenTypeIsSavedAndAnUpdateWithoutTypeKeepsIt(): void
    {
        /** @var Assignment $assignment */
        $assignment = Episciences_UsersManager::assign(self::UID, [
            'rvid' => RVID,
            'itemid' => self::DOCID,
            'item' => Assignment::ITEM_PAPER,
            'roleid' => Assignment::ROLE_REVIEWER,
            'status' => Assignment::STATUS_PENDING,
            'request_type_id' => $this->quickOpinion,
        ])[0];

        $copy = new Assignment(['id' => $assignment->getId(), 'uid' => self::UID, 'itemid' => self::DOCID, 'item' => Assignment::ITEM_PAPER,
            'roleid' => Assignment::ROLE_REVIEWER, 'status' => Assignment::STATUS_PENDING, 'rvid' => RVID]);
        $copy->save();

        self::assertSame([$this->quickOpinion => Assignment::STATUS_PENDING], $this->latestStatusByType());
    }
}
