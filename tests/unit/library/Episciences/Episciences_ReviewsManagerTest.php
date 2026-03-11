<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_ReviewsManager.
 *
 * DB-dependent methods (getList, findByRvid, findByRvcode,
 * findActiveJournals, etc.) are tested only for their pure-logic
 * branches (type dispatch in find(), cache behaviour).
 *
 * @covers Episciences_ReviewsManager
 */
class Episciences_ReviewsManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clearCache();
    }

    protected function tearDown(): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    // =========================================================================
    // find() — type dispatch
    // =========================================================================

    public function testFindWithNonNumericNonStringReturnsFalse(): void
    {
        // find() with non-string, non-numeric → returns false immediately
        $result = Episciences_ReviewsManager::find([]);
        self::assertFalse($result);
    }

    public function testFindWithNullReturnsFalse(): void
    {
        $result = Episciences_ReviewsManager::find(null);
        self::assertFalse($result);
    }

    public function testFindWithUnknownRvidReturnsFalse(): void
    {
        $result = Episciences_ReviewsManager::find(999999);
        self::assertFalse($result);
    }

    public function testFindWithUnknownRvcodeReturnsFalse(): void
    {
        $result = Episciences_ReviewsManager::find('totally-unknown-journal-code-xyz');
        self::assertFalse($result);
    }

    // =========================================================================
    // Cache behaviour
    // =========================================================================

    public function testFindCachesUnknownRvid(): void
    {
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);

        Episciences_ReviewsManager::find(999999);

        $cache = $prop->getValue(null);
        self::assertArrayHasKey('rvid_999999', $cache);
        self::assertFalse($cache['rvid_999999']);
    }

    public function testFindByRvcodeDoesNotCacheUnknownRvcode(): void
    {
        // Unlike findByRvid(), findByRvcode() returns early (before caching) on DB miss
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);

        $result = Episciences_ReviewsManager::find('totally-unknown-journal-xyz');

        self::assertFalse($result);
        $cache = $prop->getValue(null);
        self::assertArrayNotHasKey('rvcode_totally-unknown-journal-xyz', $cache);
    }

    public function testSecondFindCallUsesCacheWithoutDbHit(): void
    {
        // Pre-seed the cache (as if a previous DB lookup already ran)
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);
        $prop->setValue(null, ['rvid_999999' => false]);

        // find() must return the cached value without touching DB
        $result = Episciences_ReviewsManager::find(999999);
        self::assertFalse($result);

        // Cache must still contain exactly the seeded entry (no extra DB call)
        $cache = $prop->getValue(null);
        self::assertCount(1, $cache);
        self::assertArrayHasKey('rvid_999999', $cache);
    }

    // =========================================================================
    // findByRvid — cache key format
    // =========================================================================

    public function testFindByRvidCacheKeyFormat(): void
    {
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);

        Episciences_ReviewsManager::findByRvid(888888);

        $cache = $prop->getValue(null);
        self::assertArrayHasKey('rvid_888888', $cache);
    }

    // =========================================================================
    // findByRvcode — enabledOnly cache key
    // =========================================================================

    public function testFindByRvcodeCacheKeyDiffersByEnabledOnly(): void
    {
        // Pre-seed both cache keys to prove they are independent
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'rvcode_myjournal'         => false,
            'rvcode_myjournal_enabled' => false,
        ]);

        // Each call checks its own key (enabledOnly=false vs enabledOnly=true)
        $resultAll     = Episciences_ReviewsManager::findByRvcode('myjournal', false);
        $resultEnabled = Episciences_ReviewsManager::findByRvcode('myjournal', true);

        self::assertFalse($resultAll);
        self::assertFalse($resultEnabled);

        // Both keys remain separate in the cache
        $cache = $prop->getValue(null);
        self::assertArrayHasKey('rvcode_myjournal', $cache);
        self::assertArrayHasKey('rvcode_myjournal_enabled', $cache);
    }
}
