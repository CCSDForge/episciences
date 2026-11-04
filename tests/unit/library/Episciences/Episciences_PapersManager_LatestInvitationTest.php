<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The latest row of an invitation is the one with the highest ID: an answer inserts a row with the same AID, possibly
 * in the same second as the invitation, so two rows can share the latest sending date.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getLatestInvitationByDocIdQuery
 * @covers Episciences_PapersManager::getReviewers
 */
final class Episciences_PapersManager_LatestInvitationTest extends TestCase
{
    private const DOCID = 999101;
    private const UID = 999102;
    private const DATE = '2024-01-01 10:00:00';

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    /**
     * An invitation and its answers, all in the same second: one assignment and one invitation row by step.
     *
     * @param array<int, array{0: string, 1: string}> $steps (status of the invitation, status of the assignment)
     */
    private function insertInvitation(int $uid, bool $isTmpUser, array $steps): void
    {
        $aid = null;

        foreach ($steps as [$invitationStatus, $assignmentStatus]) {
            $this->db->insert(T_ASSIGNMENTS, [
                'RVID' => RVID,
                'ITEMID' => self::DOCID,
                'ITEM' => 'paper',
                'UID' => $uid,
                'TMP_USER' => (int)$isTmpUser,
                'ROLEID' => 'reviewer',
                'STATUS' => $assignmentStatus,
                'WHEN' => self::DATE,
            ]);
            $assignmentId = (int)$this->db->lastInsertId();
            $aid ??= $assignmentId;

            $this->db->insert(T_USER_INVITATIONS, [
                'AID' => $aid,
                'STATUS' => $invitationStatus,
                'SENDING_DATE' => self::DATE,
                'EXPIRATION_DATE' => '2024-02-01 10:00:00',
            ]);
            $this->db->update(T_ASSIGNMENTS, ['INVITATION_ID' => (int)$this->db->lastInsertId()], ['ID = ?' => $assignmentId]);
        }
    }

    private function insertTmpUser(): int
    {
        $this->db->insert(T_TMP_USER, ['EMAIL' => 'latest-invitation-test@example.org', 'FIRSTNAME' => 'Test', 'LASTNAME' => 'Reviewer', 'LANG' => 'en']);

        return (int)$this->db->lastInsertId();
    }

    public function testTheAnswerGivenInTheSameSecondIsTheLatestRow(): void
    {
        $this->insertInvitation(self::UID, false, [['pending', 'pending'], ['accepted', 'active']]);

        $rows = $this->db->fetchAll(Episciences_PapersManager::getLatestInvitationByDocIdQuery(self::DOCID));

        $this->assertCount(1, $rows);
        $this->assertSame('accepted', $rows[0]['INVITATION_STATUS']);
        $this->assertSame(Episciences_User_Assignment::STATUS_ACTIVE, $rows[0]['ASSIGNMENT_STATUS']);
    }

    public function testATemporaryReviewerWhoAcceptedInTheSameSecondIsNotPending(): void
    {
        $answered = $this->insertTmpUser();
        $this->insertInvitation($answered, true, [['pending', 'pending'], ['accepted', 'active']]);
        $pending = $this->insertTmpUser();
        $this->insertInvitation($pending, true, [['pending', 'pending']]);

        $reviewers = Episciences_PapersManager::getReviewers(self::DOCID, Episciences_User_Assignment::STATUS_PENDING);

        $this->assertSame(['tmp_' . $pending], array_keys($reviewers));
    }
}
