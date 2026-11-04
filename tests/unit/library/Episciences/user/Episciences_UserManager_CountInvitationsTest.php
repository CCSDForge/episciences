<?php

declare(strict_types=1);

namespace unit\library\Episciences\user;

use Episciences_UserManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_UserManager::countInvitations() counts the invitations sent to a user by the current journal, once each:
 * an answer or a cancellation inserts another USER_INVITATION row with the same AID.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_UserManager::countInvitations
 */
final class Episciences_UserManager_CountInvitationsTest extends TestCase
{
    private const UID = 999901;

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
     * @param string[] $statuses the rows of the invitation: the first one, then its answers or cancellations
     */
    private function insertInvitation(int $rvid, array $statuses): void
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => $rvid,
            'ITEMID' => 1,
            'ITEM' => 'paper',
            'UID' => self::UID,
            'ROLEID' => 'reviewer',
            'STATUS' => 'pending',
            'WHEN' => '2024-01-01 10:00:00',
        ]);
        $aid = (int)$this->db->lastInsertId();

        foreach ($statuses as $status) {
            $this->db->insert(T_USER_INVITATIONS, [
                'AID' => $aid,
                'STATUS' => $status,
                'SENDING_DATE' => '2024-01-01 10:00:00',
                'EXPIRATION_DATE' => '2024-02-01 10:00:00',
            ]);
        }
    }

    public function testAnInvitationIsCountedOnceWhateverItsAnswers(): void
    {
        $this->insertInvitation(RVID, ['pending', 'accepted']);
        $this->insertInvitation(RVID, ['pending', 'accepted', 'cancelled']);
        $this->insertInvitation(RVID, ['pending']);
        // another journal
        $this->insertInvitation(RVID + 1, ['pending', 'declined']);

        $this->assertSame(3, (int)Episciences_UserManager::countInvitations(self::UID)['stats_invitations_nbr']);
    }

    public function testNoInvitation(): void
    {
        $this->assertSame(0, (int)Episciences_UserManager::countInvitations(self::UID)['stats_invitations_nbr']);
    }
}
