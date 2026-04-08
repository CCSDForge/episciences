<?php

namespace unit\library\Episciences\user;

use Episciences_UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_UserManager
 *
 * Tests the early-return edge cases that do not require database access,
 * and DB-dependent queries that can be exercised in the Docker test environment.
 *
 * @covers Episciences_UserManager
 */
class Episciences_UserManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getCorrespondingUidFromUuid — early returns (no DB needed)
    // -------------------------------------------------------------------------

    public function testGetCorrespondingUidFromUuidWithEmptyStringReturnsZero(): void
    {
        $result = Episciences_UserManager::getCorrespondingUidFromUuid('');
        $this->assertSame(0, $result);
    }

    public function testGetCorrespondingUidFromUuidWithDefaultEmptyArgReturnsZero(): void
    {
        $result = Episciences_UserManager::getCorrespondingUidFromUuid();
        $this->assertSame(0, $result);
    }

    public function testGetCorrespondingUidFromUuidWithNonExistentUuidReturnsZero(): void
    {
        // A well-formed UUID that does not exist in the database
        $result = Episciences_UserManager::getCorrespondingUidFromUuid('00000000-0000-0000-0000-000000000000');
        $this->assertSame(0, $result);
    }

    public function testGetCorrespondingUidFromUuidReturnsInt(): void
    {
        $result = Episciences_UserManager::getCorrespondingUidFromUuid('non-existent-uuid-value');
        $this->assertIsInt($result);
    }

    // -------------------------------------------------------------------------
    // getUuidFromUid — early returns (no DB needed)
    // -------------------------------------------------------------------------

    public function testGetUuidFromUidWithZeroReturnsNull(): void
    {
        $result = Episciences_UserManager::getUuidFromUid(0);
        $this->assertNull($result);
    }

    public function testGetUuidFromUidWithNonExistentUidReturnsNullOrString(): void
    {
        // UID 999999999 is extremely unlikely to exist
        $result = Episciences_UserManager::getUuidFromUid(999999999);
        // Either null (not found) or a string UUID (if it exists)
        $this->assertTrue($result === null || is_string($result));
    }

    // -------------------------------------------------------------------------
    // getSubmittedPapersQuery — verifies it returns a Zend_Db_Select instance
    // -------------------------------------------------------------------------

    public function testGetSubmittedPapersQueryReturnsZendDbSelect(): void
    {
        $select = Episciences_UserManager::getSubmittedPapersQuery(1);
        $this->assertInstanceOf(\Zend_Db_Select::class, $select);
    }

    public function testGetSubmittedPapersQuerySqlContainsPapersTable(): void
    {
        $select = Episciences_UserManager::getSubmittedPapersQuery(42);
        $sql = (string) $select;
        $this->assertStringContainsStringIgnoringCase('PAPERS', $sql);
    }

    public function testGetSubmittedPapersQuerySqlContainsUidCondition(): void
    {
        $select = Episciences_UserManager::getSubmittedPapersQuery(42);
        $sql = (string) $select;
        $this->assertStringContainsString('42', $sql);
    }
}
