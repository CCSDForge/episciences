<?php

declare(strict_types=1);

namespace unit\library\Episciences\Hal;

use Episciences_Hal_TeiCacheManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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
        Episciences_Hal_TeiCacheManager::setHttpClient(null);
    }

    private function halResponds(Response ...$responses): MockHandler
    {
        $handler = new MockHandler($responses);
        Episciences_Hal_TeiCacheManager::setHttpClient(new Client(['handler' => HandlerStack::create($handler)]));

        return $handler;
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

    public function testEmptyTeiCachedBeforeFailuresStoppedBeingCachedIsFetchedAgain(): void
    {
        $this->seed('');
        $handler = $this->halResponds(new Response(200, [], '<TEI>fetched</TEI>'));

        self::assertSame('', Episciences_Hal_TeiCacheManager::getFromCache(self::IDENTIFIER));
        self::assertSame('<TEI>fetched</TEI>', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
        self::assertCount(0, $handler, 'HAL called');
    }

    public function testFetchedTeiIsCached(): void
    {
        $this->halResponds(new Response(200, [], '<TEI>fetched</TEI>'));

        self::assertTrue(Episciences_Hal_TeiCacheManager::fetchAndCache(self::IDENTIFIER));
        self::assertSame('<TEI>fetched</TEI>', Episciences_Hal_TeiCacheManager::getFromCache(self::IDENTIFIER));
    }

    public function testFailedTeiIsNeitherCachedNorRequestedAgainInTheSameProcess(): void
    {
        $handler = $this->halResponds(new Response(200, [], ''), new Response(200, [], '<TEI>back</TEI>'));

        self::assertFalse(Episciences_Hal_TeiCacheManager::fetchAndCache(self::IDENTIFIER), 'nothing fetched');
        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
        self::assertCount(1, $handler, 'HAL called once for the failed TEI');
        self::assertFalse($this->cache->hasItem('hal-tei-' . self::IDENTIFIER . '.xml'), 'the failure is not cached');
    }

    public function testDeleteRequestsAFailedTeiAgain(): void
    {
        $this->halResponds(new Response(200, [], ''), new Response(200, [], '<TEI>back</TEI>'));
        Episciences_Hal_TeiCacheManager::fetchAndCache(self::IDENTIFIER);

        Episciences_Hal_TeiCacheManager::delete(self::IDENTIFIER);

        self::assertSame('<TEI>back</TEI>', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
    }

    public function testFailureOfOneVersionDoesNotBlockAnother(): void
    {
        $this->halResponds(new Response(200, [], ''), new Response(200, [], '<TEI>v2</TEI>'));

        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER));
        self::assertSame('<TEI>v2</TEI>', Episciences_Hal_TeiCacheManager::fetchAndGet(self::IDENTIFIER, 2));
    }
}
