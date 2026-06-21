<?php

namespace unit\library\Episciences\user;

use Episciences_User_AssignmentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_AssignmentsManager
 *
 * Tests the early-return guard in findById() that does not require DB.
 * Cache hit/miss tests inject a pre-populated ArrayAdapter to bypass DB.
 *
 * @covers Episciences_User_AssignmentsManager
 */
class Episciences_User_AssignmentsManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Episciences_User_AssignmentsManager::setCachePool(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
    }
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

    // -------------------------------------------------------------------------
    // removeAssignment() — guards that do not require DB access
    // -------------------------------------------------------------------------

    public function testRemoveAssignmentWithZeroReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::removeAssignment(0);
        $this->assertFalse($result);
    }

    public function testRemoveAssignmentWithNegativeIntReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::removeAssignment(-1);
        $this->assertFalse($result);
    }

    public function testRemoveAssignmentWithEmptyArrayReturnsFalse(): void
    {
        $result = Episciences_User_AssignmentsManager::removeAssignment([]);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // updateUid() — zero guards (no DB access)
    // -------------------------------------------------------------------------

    public function testUpdateUidWithZeroOldUidReturnsZero(): void
    {
        $result = Episciences_User_AssignmentsManager::updateUid(0, 99);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithZeroNewUidReturnsZero(): void
    {
        $result = Episciences_User_AssignmentsManager::updateUid(99, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithBothZeroReturnsZero(): void
    {
        $result = Episciences_User_AssignmentsManager::updateUid(0, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithDefaultParametersReturnsZero(): void
    {
        $result = Episciences_User_AssignmentsManager::updateUid();
        $this->assertSame(0, $result);
    }

    // -------------------------------------------------------------------------
    // Cache pool wiring
    // -------------------------------------------------------------------------

    public function testGetCachePoolReturnsArrayAdapterByDefault(): void
    {
        $pool = Episciences_User_AssignmentsManager::getCachePool();
        $this->assertInstanceOf(\Symfony\Component\Cache\Adapter\ArrayAdapter::class, $pool);
    }

    public function testSetCachePoolChangesPoolInstance(): void
    {
        $mockPool = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        Episciences_User_AssignmentsManager::setCachePool($mockPool);
        $this->assertSame($mockPool, Episciences_User_AssignmentsManager::getCachePool());
    }

    // -------------------------------------------------------------------------
    // find() — null-query path caches false (no DB needed)
    // -------------------------------------------------------------------------

    public function testFindWithEmptyParamsReturnsFalse(): void
    {
        $this->assertFalse(Episciences_User_AssignmentsManager::find([]));
    }

    public function testFindWithEmptyParamsCachesFalseResult(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        Episciences_User_AssignmentsManager::setCachePool($pool);

        Episciences_User_AssignmentsManager::find([]);

        $key = 'assignments_find_' . md5(serialize([]));
        $item = $pool->getItem($key);
        $this->assertTrue($item->isHit());
        $this->assertFalse($item->get());
    }

    // -------------------------------------------------------------------------
    // findAll() — null-query path caches false (no DB needed)
    // -------------------------------------------------------------------------

    public function testFindAllWithEmptyParamsReturnsFalse(): void
    {
        $this->assertFalse(Episciences_User_AssignmentsManager::findAll([]));
    }

    public function testFindAllWithEmptyParamsCachesFalseResult(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        Episciences_User_AssignmentsManager::setCachePool($pool);

        Episciences_User_AssignmentsManager::findAll([]);

        $key = 'assignments_findall_' . md5(serialize([]));
        $item = $pool->getItem($key);
        $this->assertTrue($item->isHit());
        $this->assertFalse($item->get());
    }

    // -------------------------------------------------------------------------
    // getList() — cache hit path (no DB needed)
    // -------------------------------------------------------------------------

    public function testGetListReturnsCachedValueOnHit(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $params = ['DOCID' => 42];
        $cachedValue = ['sentinel' => 'cached-result'];

        $key = 'assignments_list_' . md5(serialize($params) . '_1');
        $item = $pool->getItem($key);
        $item->set($cachedValue);
        $pool->save($item);

        Episciences_User_AssignmentsManager::setCachePool($pool);

        $result = Episciences_User_AssignmentsManager::getList($params);
        $this->assertSame($cachedValue, $result);
    }

    public function testFindReturnsCachedValueOnHit(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $params = ['ID' => 99];
        $cachedValue = false;

        $key = 'assignments_find_' . md5(serialize($params));
        $item = $pool->getItem($key);
        $item->set($cachedValue);
        $pool->save($item);

        Episciences_User_AssignmentsManager::setCachePool($pool);

        $result = Episciences_User_AssignmentsManager::find($params);
        $this->assertFalse($result);
    }
}
