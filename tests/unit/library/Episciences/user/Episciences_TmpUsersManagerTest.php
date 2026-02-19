<?php

namespace unit\library\Episciences\user;

use Episciences_TmpUsersManager;
use Episciences_User_Tmp;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_TmpUsersManager
 *
 * @covers Episciences_TmpUsersManager
 */
class Episciences_TmpUsersManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdWithNonExistentIdReturnsFalse(): void
    {
        // ID 0 and very large IDs should not exist in the database
        $result = Episciences_TmpUsersManager::findById(0);
        $this->assertFalse($result);
    }

    public function testFindByIdWithLargeNonExistentIdReturnsFalse(): void
    {
        $result = Episciences_TmpUsersManager::findById(999999999);
        $this->assertFalse($result);
    }

    public function testFindByIdWithNegativeIdReturnsFalse(): void
    {
        $result = Episciences_TmpUsersManager::findById(-1);
        $this->assertFalse($result);
    }

    public function testFindByIdReturnsFalseOrTmpUserInstance(): void
    {
        $result = Episciences_TmpUsersManager::findById(1);
        // Either false (not found) or an Episciences_User_Tmp instance (found)
        $this->assertTrue(
            $result === false || $result instanceof Episciences_User_Tmp,
            'Expected false or Episciences_User_Tmp instance'
        );
    }
}
