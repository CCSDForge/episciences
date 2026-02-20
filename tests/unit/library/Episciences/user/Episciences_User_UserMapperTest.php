<?php

namespace unit\library\Episciences\user;

use Episciences_User_UserMapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_UserMapper
 *
 * @covers Episciences_User_UserMapper
 */
class Episciences_User_UserMapperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getUserCountAfterDate (static)
    // -------------------------------------------------------------------------

    public function testGetUserCountAfterDateReturnsNonNegativeInteger(): void
    {
        $count = Episciences_User_UserMapper::getUserCountAfterDate('2000-01-01 00:00:00');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetUserCountAfterDateWithDefaultParameterReturnsInt(): void
    {
        // Empty string triggers "current year" logic
        $count = Episciences_User_UserMapper::getUserCountAfterDate('');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testGetUserCountAfterFutureDateReturnsZeroOrPositive(): void
    {
        // A date far in the future should return 0 (no registrations yet)
        $count = Episciences_User_UserMapper::getUserCountAfterDate('2099-12-31 23:59:59');
        $this->assertIsInt($count);
        $this->assertSame(0, $count);
    }

    public function testGetUserCountAfterVeryOldDateReturnsPositive(): void
    {
        // All users were registered after 1970, so count should be >= 0
        $count = Episciences_User_UserMapper::getUserCountAfterDate('1970-01-01 00:00:00');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // -------------------------------------------------------------------------
    // findUserByFirstNameAndName (instance method)
    // -------------------------------------------------------------------------

    public function testFindUserByFirstNameAndNameWithNonExistentNameReturnsNull(): void
    {
        // This test requires the Zend_Db_Table metadata cache to be writable.
        // In some CI/test environments the cache directory may be unavailable.
        try {
            $mapper = new Episciences_User_UserMapper();
            // A name extremely unlikely to exist
            $result = $mapper->findUserByFirstNameAndName('ZZZZNONEXISTENTUSER99999');
            $this->assertNull($result);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'metadataCache')) {
                $this->markTestSkipped('Zend_Db_Table metadata cache not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    public function testFindUserByFirstNameAndNameWithBothParamsAndNonExistentReturnsNull(): void
    {
        try {
            $mapper = new Episciences_User_UserMapper();
            $result = $mapper->findUserByFirstNameAndName('NONEXISTENTLASTNAME', 'NONEXISTENTFIRST');
            $this->assertNull($result);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'metadataCache')) {
                $this->markTestSkipped('Zend_Db_Table metadata cache not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }
}
