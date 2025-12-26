<?php

/**
 * Unit tests for Episciences_User batch loading methods
 *
 * Tests the SQL N+1 optimization methods without requiring database access
 * by using PHPUnit mocks for database adapters.
 */
class Episciences_UserTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test loadRolesBatch with empty UIDs array
     */
    public function testLoadRolesBatchWithEmptyArray()
    {
        $result = Episciences_User::loadRolesBatch([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test loadRolesBatch groups roles by UID and RVID correctly
     */
    public function testLoadRolesBatchGroupsByUidAndRvid()
    {
        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->createMock(Zend_Db_Select::class);

        $mockSelect->method('from')->willReturn($mockSelect);
        $mockSelect->method('where')->willReturn($mockSelect);
        $mockDb->method('select')->willReturn($mockSelect);

        // Mock result with multiple roles for same user
        $mockDb->method('fetchAll')->willReturn([
            ['UID' => '100', 'RVID' => '1', 'ROLEID' => 'editor'],
            ['UID' => '100', 'RVID' => '1', 'ROLEID' => 'reviewer'],
            ['UID' => '100', 'RVID' => '2', 'ROLEID' => 'member'],
            ['UID' => '200', 'RVID' => '1', 'ROLEID' => 'member']
        ]);

        Zend_Db_Table_Abstract::setDefaultAdapter($mockDb);

        $result = Episciences_User::loadRolesBatch([100, 200], null);

        // Verify grouping structure
        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(200, $result);

        // User 100 has roles in 2 RVIDs
        $this->assertArrayHasKey(1, $result[100]);
        $this->assertArrayHasKey(2, $result[100]);

        // User 100, RVID 1 has 2 roles
        $this->assertCount(2, $result[100][1]);
        $this->assertContains('editor', $result[100][1]);
        $this->assertContains('reviewer', $result[100][1]);
    }
}
