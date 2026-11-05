<?php

declare(strict_types=1);

namespace unit\library\Episciences\Hal;

use Episciences_Hal_TeiCacheManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @covers Episciences_Hal_TeiCacheManager
 */
final class Episciences_Hal_TeiCacheManagerTest extends TestCase
{
    private const IDENTIFIER = 'hal-01234567';

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter(0, false);
        Episciences_Hal_TeiCacheManager::setCachePool($this->cache);
    }

    protected function tearDown(): void
    {
        Episciences_Hal_TeiCacheManager::setCachePool(null);
    }

    private function seed(string $tei, int $version = 0): void
    {
        $key = 'hal-tei-' . self::IDENTIFIER . ($version !== 0 ? '-' . $version : '') . '.xml';
        $this->cache->save($this->cache->getItem($key)->set($tei));
    }

    public function testDeleteDropsTheCachedTeiOfThatVersionOnly(): void
    {
        $this->seed('<TEI>latest</TEI>');
        $this->seed('<TEI>v2</TEI>', 2);

        Episciences_Hal_TeiCacheManager::delete(self::IDENTIFIER, 2);

        self::assertSame('', Episciences_Hal_TeiCacheManager::getFromCache(self::IDENTIFIER, 2));
        self::assertSame('<TEI>latest</TEI>', Episciences_Hal_TeiCacheManager::getFromCache(self::IDENTIFIER));
    }

    public function testCachedTeiIsServedWithoutCallingHal(): void
    {
        $this->seed('<TEI>cached</TEI>');

        self::assertFalse(Episciences_Hal_TeiCacheManager::fetchAndCache(self::IDENTIFIER), 'already cached');
        self::assertSame('<TEI>cached</TEI>', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
    }
}
