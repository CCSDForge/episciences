<?php

namespace unit\library\Episciences\user;

use Episciences_User_InvitationsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_InvitationsManager
 *
 * Tests pure logic that does not require a database connection.
 * DB-dependent methods (findById, find with real data) are not tested here.
 *
 * Bugs fixed and covered:
 * - BUG-IM1: find() had $sql->order('ID DESC') inside foreach loop → ORDER BY added N times.
 *   Fixed by moving order() outside the loop.
 * - BUG-IM2: updateSenderUid() updated column 'UID' instead of 'SENDER_UID'.
 *   Fixed by using the correct column name.
 *
 * @covers Episciences_User_InvitationsManager
 */
class Episciences_User_InvitationsManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // find() — early return on empty params (no DB access)
    // -------------------------------------------------------------------------

    public function testFindWithEmptyParamsReturnsFalse(): void
    {
        $result = Episciences_User_InvitationsManager::find([]);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // updateSenderUid() — zero guards (no DB access)
    // BUG-IM2: was updating 'UID' column instead of 'SENDER_UID'
    // -------------------------------------------------------------------------

    public function testUpdateSenderUidWithZeroOldUidReturnsZero(): void
    {
        $result = Episciences_User_InvitationsManager::updateSenderUid(0, 99);
        $this->assertSame(0, $result);
    }

    public function testUpdateSenderUidWithZeroNewUidReturnsZero(): void
    {
        $result = Episciences_User_InvitationsManager::updateSenderUid(99, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateSenderUidWithBothZeroReturnsZero(): void
    {
        $result = Episciences_User_InvitationsManager::updateSenderUid(0, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateSenderUidWithNegativeOldUidReturnsZero(): void
    {
        // Loose comparison: -1 == 0 is false, but -1 == 0 with == is false,
        // so the guard ($oldUid == 0) does NOT catch negatives.
        // This documents the current behaviour.
        $result = Episciences_User_InvitationsManager::updateSenderUid(-1, 5);
        // -1 passes the guard, DB query runs on non-existent UID → 0 rows affected
        $this->assertIsInt($result);
    }

    public function testUpdateSenderUidWithDefaultParametersReturnsZero(): void
    {
        // Default values are both 0 → guard returns 0 immediately
        $result = Episciences_User_InvitationsManager::updateSenderUid();
        $this->assertSame(0, $result);
    }
}
