<?php

declare(strict_types=1);

namespace unit\library\Episciences\user;

use Episciences_User_Assignment;
use Episciences_User_AssignmentsManager;
use Episciences_User_Invitation;
use Episciences_User_InvitationsManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_User_AssignmentsManager::cancelReviewerAssignment() inserts a cancelled invitation row (same AID) and a
 * cancelled assignment row referring to it; a reviewer assigned without invitation only gets the cancelled assignment.
 * The cancelled assignment keeps the journal of the original one, whatever the current journal context.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_User_AssignmentsManager::cancelReviewerAssignment
 */
final class Episciences_User_AssignmentsManager_CancelReviewerAssignmentTest extends TestCase
{
    private const UID = 999902;
    private const DOCID = 999902;

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

    private function insertPendingAssignment(int $rvid = RVID): int
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => $rvid,
            'ITEMID' => self::DOCID,
            'ITEM' => Episciences_User_Assignment::ITEM_PAPER,
            'UID' => self::UID,
            'TMP_USER' => 1,
            'ROLEID' => Episciences_User_Assignment::ROLE_REVIEWER,
            'STATUS' => Episciences_User_Assignment::STATUS_PENDING,
            'WHEN' => '2024-01-01 10:00:00',
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function findAssignment(int $id): Episciences_User_Assignment
    {
        $assignment = Episciences_User_AssignmentsManager::findById($id);
        $this->assertInstanceOf(Episciences_User_Assignment::class, $assignment);

        return $assignment;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAssignmentRow(int $id): array
    {
        return $this->db->fetchRow($this->db->select()->from(T_ASSIGNMENTS)->where('ID = ?', $id));
    }

    public function testCancelsTheInvitationAndTheAssignment(): void
    {
        $aid = $this->insertPendingAssignment();
        $this->db->insert(T_USER_INVITATIONS, [
            'AID' => $aid,
            'STATUS' => Episciences_User_Invitation::STATUS_PENDING,
            'SENDING_DATE' => '2024-01-01 10:00:00',
            'EXPIRATION_DATE' => '2024-02-01 10:00:00',
        ]);
        $pendingInvitationId = (int)$this->db->lastInsertId();
        $this->db->update(T_ASSIGNMENTS, ['INVITATION_ID' => $pendingInvitationId], ['ID = ?' => $aid]);

        $assignment = $this->findAssignment($aid);
        $invitation = Episciences_User_InvitationsManager::findById($pendingInvitationId);
        $this->assertInstanceOf(Episciences_User_Invitation::class, $invitation);

        $cancelled = Episciences_User_AssignmentsManager::cancelReviewerAssignment($assignment, $invitation);

        // a new invitation row, cancelled, for the same assignment
        $invitationRows = $this->db->fetchAll(
            $this->db->select()->from(T_USER_INVITATIONS, ['ID', 'STATUS'])->where('AID = ?', $aid)->order('ID')
        );
        $this->assertCount(2, $invitationRows);
        $this->assertSame(Episciences_User_Invitation::STATUS_PENDING, $invitationRows[0]['STATUS']);
        $this->assertSame(Episciences_User_Invitation::STATUS_CANCELLED, $invitationRows[1]['STATUS']);
        $cancelledInvitationId = (int)$invitationRows[1]['ID'];
        $this->assertSame($cancelledInvitationId, $invitation->getId());

        // a new assignment row, cancelled, referring to the cancelled invitation
        $this->assertNotSame($aid, $cancelled->getId());
        $row = $this->fetchAssignmentRow($cancelled->getId());
        $this->assertSame(Episciences_User_Assignment::STATUS_CANCELLED, $row['STATUS']);
        $this->assertSame($cancelledInvitationId, (int)$row['INVITATION_ID']);
        $this->assertSame(self::UID, (int)$row['UID']);
        $this->assertSame(self::DOCID, (int)$row['ITEMID']);
        $this->assertSame(1, (int)$row['TMP_USER']);
        $this->assertSame(Episciences_User_Assignment::ROLE_REVIEWER, $row['ROLEID']);
        $this->assertSame(RVID, (int)$row['RVID']);
    }

    public function testCancelsTheAssignmentOfAReviewerWithoutInvitation(): void
    {
        $aid = $this->insertPendingAssignment();

        $cancelled = Episciences_User_AssignmentsManager::cancelReviewerAssignment($this->findAssignment($aid));

        $this->assertSame(
            0,
            (int)$this->db->fetchOne($this->db->select()->from(T_USER_INVITATIONS, ['COUNT(*)'])->where('AID = ?', $aid))
        );

        $row = $this->fetchAssignmentRow($cancelled->getId());
        $this->assertSame(Episciences_User_Assignment::STATUS_CANCELLED, $row['STATUS']);
        $this->assertNull($row['INVITATION_ID']);
        $this->assertSame(self::UID, (int)$row['UID']);
    }

    public function testKeepsTheJournalOfTheOriginalAssignment(): void
    {
        $otherRvid = RVID + 1;
        $aid = $this->insertPendingAssignment($otherRvid);

        $cancelled = Episciences_User_AssignmentsManager::cancelReviewerAssignment($this->findAssignment($aid));

        $this->assertSame($otherRvid, (int)$this->fetchAssignmentRow($cancelled->getId())['RVID']);
    }
}
