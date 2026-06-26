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

    public function testFindByRvidAlsoCachesRvcode(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_ReviewsManager_CacheTestAdapter([
            'RVID' => 8,
            'CODE' => 'demo',
            'NAME' => 'Demo',
            'STATUS' => Episciences_Review::ENABLED,
        ]);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

            $review = Episciences_ReviewsManager::findByRvid(8);
            $sameReview = Episciences_ReviewsManager::findByRvcode('demo');

            self::assertInstanceOf(Episciences_Review::class, $review);
            self::assertSame($review, $sameReview);
            self::assertSame(1, $adapter->fetchRowCount);

            $cache = $this->getCache();
            self::assertSame($review, $cache['rvid_8']);
            self::assertSame($review, $cache['rvcode_demo']);
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    public function testFindByRvcodeAlsoCachesRvid(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_ReviewsManager_CacheTestAdapter([
            'RVID' => 8,
            'CODE' => 'demo',
            'NAME' => 'Demo',
            'STATUS' => Episciences_Review::ENABLED,
        ]);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

            $review = Episciences_ReviewsManager::findByRvcode('demo');
            $sameReview = Episciences_ReviewsManager::findByRvid(8);

            self::assertInstanceOf(Episciences_Review::class, $review);
            self::assertSame($review, $sameReview);
            self::assertSame(1, $adapter->fetchRowCount);

            $cache = $this->getCache();
            self::assertSame($review, $cache['rvcode_demo']);
            self::assertSame($review, $cache['rvid_8']);
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    private function getCache(): array
    {
        $prop = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $prop->setAccessible(true);
        return $prop->getValue(null);
    }

    // =========================================================================
    // loadSettingsForReviews()
    // =========================================================================

    public function testLoadSettingsForReviewsWithEmptyArrayDoesNothing(): void
    {
        // Must return immediately without touching the DB (no adapter set up).
        Episciences_ReviewsManager::loadSettingsForReviews([]);
        $this->addToAssertionCount(1);
    }

    public function testLoadSettingsForReviewsAppliesSettingsByRvid(): void
    {
        $review1 = new Episciences_Review(['rvid' => 1]);
        $review2 = new Episciences_Review(['rvid' => 2]);

        $rows = [
            ['RVID' => '1', 'SETTING' => Episciences_Review::SETTING_JOURNAL_DESCRIPTION, 'VALUE' => 'Journal One'],
            ['RVID' => '2', 'SETTING' => Episciences_Review::SETTING_JOURNAL_DESCRIPTION, 'VALUE' => 'Journal Two'],
            ['RVID' => '2', 'SETTING' => Episciences_Review::SETTING_JOURNAL_CREATION_YEAR, 'VALUE' => '2015'],
        ];

        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_ReviewsManager_FetchAllTestAdapter($rows);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
            Episciences_ReviewsManager::loadSettingsForReviews([1 => $review1, 2 => $review2]);
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }

        // _settingsLoaded=true after the batch load, so getSetting() reads from memory
        self::assertSame('Journal One', $review1->getSetting(Episciences_Review::SETTING_JOURNAL_DESCRIPTION));
        self::assertSame('Journal Two', $review2->getSetting(Episciences_Review::SETTING_JOURNAL_DESCRIPTION));
        self::assertFalse($review1->getSetting(Episciences_Review::SETTING_JOURNAL_CREATION_YEAR));
        self::assertSame('2015', $review2->getSetting(Episciences_Review::SETTING_JOURNAL_CREATION_YEAR));
    }

    public function testLoadSettingsForReviewsIgnoresRowsForUnknownRvid(): void
    {
        $review = new Episciences_Review(['rvid' => 5]);

        // DB returns a row for RVID 99 which is not present in $reviews
        $rows = [
            ['RVID' => '99', 'SETTING' => Episciences_Review::SETTING_JOURNAL_DESCRIPTION, 'VALUE' => 'Unknown'],
        ];

        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_ReviewsManager_FetchAllTestAdapter($rows);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
            Episciences_ReviewsManager::loadSettingsForReviews([5 => $review]);
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }

        self::assertFalse($review->getSetting(Episciences_Review::SETTING_JOURNAL_DESCRIPTION));
    }
}

final class Episciences_ReviewsManager_FetchAllTestAdapter extends Zend_Db_Adapter_Abstract
{
    public function __construct(private array $rows)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    public function fetchAll($sql, $bind = [], $fetchMode = null): array
    {
        return $this->rows;
    }

    public function listTables(): array { return []; }
    public function describeTable($tableName, $schemaName = null): array { return []; }
    protected function _connect(): void {}
    public function isConnected(): bool { return true; }
    public function closeConnection(): void {}
    public function prepare($sql) { return null; }
    public function lastInsertId($tableName = null, $primaryKey = null) { return null; }
    protected function _beginTransaction(): void {}
    protected function _commit(): void {}
    protected function _rollBack(): void {}
    public function setFetchMode($mode): void { $this->_fetchMode = $mode; }
    public function limit($sql, $count, $offset = 0): string { return $sql; }
    public function supportsParameters($type): bool { return false; }
    public function getServerVersion(): string { return 'test'; }
}

final class Episciences_ReviewsManager_CacheTestAdapter extends Zend_Db_Adapter_Abstract
{
    public int $fetchRowCount = 0;

    public function __construct(private readonly array $row)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    public function fetchRow($sql, $bind = [], $fetchMode = null)
    {
        ++$this->fetchRowCount;
        return $this->row;
    }

    public function listTables()
    {
        return [];
    }

    public function describeTable($tableName, $schemaName = null)
    {
        return [];
    }

    protected function _connect()
    {
    }

    public function isConnected()
    {
        return true;
    }

    public function closeConnection()
    {
    }

    public function prepare($sql)
    {
        return null;
    }

    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        return null;
    }

    protected function _beginTransaction()
    {
    }

    protected function _commit()
    {
    }

    protected function _rollBack()
    {
    }

    public function setFetchMode($mode)
    {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0)
    {
        return $sql;
    }

    public function supportsParameters($type)
    {
        return false;
    }

    public function getServerVersion()
    {
        return 'test';
    }
}
