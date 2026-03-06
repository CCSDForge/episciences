<?php

namespace unit\library\Episciences\user;

use Episciences_User_InvitationAnswersManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_InvitationAnswersManager
 *
 * Tests pure logic that does not require a database connection.
 * DB-dependent methods (findById, find with real data) are not tested here.
 *
 * @covers Episciences_User_InvitationAnswersManager
 */
class Episciences_User_InvitationAnswersManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // find() — early return on empty params (no DB access)
    // -------------------------------------------------------------------------

    public function testFindWithEmptyParamsReturnsFalse(): void
    {
        $result = Episciences_User_InvitationAnswersManager::find([]);
        $this->assertFalse($result);
    }
}
