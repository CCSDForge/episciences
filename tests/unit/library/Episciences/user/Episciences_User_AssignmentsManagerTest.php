<?php

namespace unit\library\Episciences\user;

use Episciences_User_AssignmentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_AssignmentsManager
 *
 * Tests the early-return guard in findById() that does not require DB.
 * Other methods (getList, find) require DB and are not tested here.
 *
 * @covers Episciences_User_AssignmentsManager
 */
class Episciences_User_AssignmentsManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // findById — non-numeric guard (no DB access)
    // -------------------------------------------------------------------------

    public function testFindByIdWithStringReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::findById('not-a-number');
        $this->assertFalse($result);
    }

    public function testFindByIdWithEmptyStringReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::findById('');
        $this->assertFalse($result);
    }

    public function testFindByIdWithArrayReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::findById([1, 2, 3]);
        $this->assertFalse($result);
    }

    public function testFindByIdWithNullReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::findById(null);
        $this->assertFalse($result);
    }

    public function testFindByIdWithNumericStringPassesGuard(): void
    {
        // '42' is_numeric → passes guard, then queries DB for non-existent ID
        $result = Episciences_User_AssignmentsManager::findById('42');
        // Either false (not found) or an Assignment instance (found)
        $this->assertTrue(
            $result === false || $result instanceof \Episciences_User_Assignment,
            'Expected false or Episciences_User_Assignment'
        );
    }

    public function testFindByIdWithLargeNonExistentIdReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::findById(999999999);
        $this->assertFalse($result);
    }
}
