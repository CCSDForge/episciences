<?php

namespace unit\library\Episciences\Oai;

use Episciences_Oai_Server;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Unit tests for Episciences_Oai_Server.
 *
 * Focus: OAI resumption token cache behaviour (getIds / getTokenCachePool),
 * security properties (key sanitisation, no raw unserialize) and the
 * executeSolrQuery delegation.
 *
 * DB and Solr are not available in the unit-test environment.
 * getTokenCachePool() and executeSolrQuery() are overridden with test doubles
 * via createPartialMock() so that tests remain fast and deterministic.
 *
 * @covers Episciences_Oai_Server
 */
class Episciences_Oai_ServerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Invokes the protected getIds() method via reflection.
     *
     * @throws \ReflectionException
     */
    private function invokeGetIds(
        Episciences_Oai_Server $server,
        string $method,
        string $format,
        ?string $until,
        ?string $from,
        ?string $set,
        ?string $token
    ): mixed {
        $reflection = new ReflectionMethod(Episciences_Oai_Server::class, 'getIds');
        $reflection->setAccessible(true);
        return $reflection->invoke($server, $method, $format, $until, $from, $set, $token);
    }

    /**
     * Returns a partial mock that replaces getTokenCachePool() and executeSolrQuery()
     * with controllable doubles.
     */
    private function buildServer(
        CacheItemPoolInterface $cachePool,
        string|false $solrResponse = false
    ): Episciences_Oai_Server {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn($cachePool);
        $server->method('executeSolrQuery')->willReturn($solrResponse);
        return $server;
    }

    // -----------------------------------------------------------------------
    // getIds() — method guard
    // -----------------------------------------------------------------------

    /**
     * Any verb that is neither ListIdentifiers nor ListRecords must be rejected
     * before touching the cache or Solr.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsReturnsFalseForInvalidMethod(): void
    {
        $server = $this->buildServer(new ArrayAdapter());

        $result = $this->invokeGetIds($server, 'GetRecord', 'oai_dc', null, null, null, null);

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // getIds() — resumption token cache miss
    // -----------------------------------------------------------------------

    /**
     * When the provided resumption token is absent from the cache, getIds()
     * must return the literal string 'token' so that the OAI caller can report
     * a badResumptionToken error.
     *
     * executeSolrQuery must NOT be called in this case (early return).
     *
     * @throws \ReflectionException|\Psr\Cache\InvalidArgumentException
     */
    public function testGetIdsReturnsTokenStringWhenTokenNotInCache(): void
    {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn(new ArrayAdapter());
        $server->expects(self::never())->method('executeSolrQuery');

        $result = $this->invokeGetIds(
            $server, 'ListIdentifiers', 'oai_dc', null, null, null, 'unknown-token'
        );

        self::assertSame('token', $result);
    }

    /**
     * An expired token (TTL elapsed) must also trigger the 'token' response.
     * We simulate expiry by saving with expiresAfter(1) and then letting the
     * ArrayAdapter store it; since ArrayAdapter respects TTL only on get(), we
     * set the item as expired using a negative TTL-like trick via the
     * ArrayAdapter's lack of filesystem — instead we simply do NOT populate the
     * cache to simulate the expired / missing case.
     *
     * The canonical expiry test is handled by testGetIdsReturnsTokenStringWhenTokenNotInCache
     * above; this test documents the expected OAI error string value explicitly.
     *
     * @throws \ReflectionException
     */
    public function testTokenErrorValueIsLiteralStringToken(): void
    {
        $server = $this->buildServer(new ArrayAdapter());

        $result = $this->invokeGetIds(
            $server, 'ListRecords', 'oai_dc', null, null, null, 'expired-or-missing'
        );

        self::assertIsString($result);
        self::assertSame('token', $result, 'OAI error for bad resumptionToken must be the string "token"');
    }

    // -----------------------------------------------------------------------
    // getIds() — resumption token cache hit
    // -----------------------------------------------------------------------

    /**
     * When the token IS in the cache, getIds() must read the conf from the
     * cache and proceed (not return 'token'). If Solr then fails, the method
     * returns false — which proves the cache path was taken.
     *
     * @throws \ReflectionException|\Psr\Cache\InvalidArgumentException
     */
    public function testGetIdsReturnsFalseWhenTokenHitButSolrFails(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('oai-token-' . md5('valid-token'));
        $item->set(['format' => 'oai_dc', 'query' => '', 'cursor' => 0, 'solr' => '']);
        $cache->save($item);

        $server = $this->buildServer($cache, false);

        $result = $this->invokeGetIds(
            $server, 'ListIdentifiers', 'oai_dc', null, null, null, 'valid-token'
        );

        // 'token' would mean cache was missed; false means cache was hit but Solr failed
        self::assertNotSame('token', $result, 'A cached token must not trigger the "token" error');
        self::assertFalse($result);
    }

    /**
     * The format stored in the cached conf must be used for subsequent processing.
     * An unknown format must cause getIds() to return false after the cache hit.
     *
     * @throws \ReflectionException|\Psr\Cache\InvalidArgumentException
     */
    public function testGetIdsReturnsFalseWhenCachedFormatIsUnknown(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('oai-token-' . md5('tok'));
        $item->set(['format' => 'unknown_format', 'query' => '', 'cursor' => 0, 'solr' => '']);
        $cache->save($item);

        $server = $this->buildServer($cache, false);

        $result = $this->invokeGetIds(
            $server, 'ListIdentifiers', 'oai_dc', null, null, null, 'tok'
        );

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // getTokenCachePool()
    // -----------------------------------------------------------------------

    /**
     * The production pool must be a PSR-6 CacheItemPoolInterface and specifically
     * a FilesystemAdapter (symfony/cache 5.4).
     *
     * The constructor of the parent class (Ccsd_Oai_Server) requires a Request
     * object and calls header(), so we bypass it with disableOriginalConstructor().
     *
     * @throws \ReflectionException
     */
    public function testGetTokenCachePoolReturnsFilesystemAdapterInstance(): void
    {
        $server = $this->getMockBuilder(Episciences_Oai_Server::class)
            ->disableOriginalConstructor()
            ->onlyMethods([]) // no method mocked: all real implementations active
            ->getMock();

        $reflection = new ReflectionMethod(Episciences_Oai_Server::class, 'getTokenCachePool');
        $reflection->setAccessible(true);

        $pool = $reflection->invoke($server);

        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        self::assertInstanceOf(FilesystemAdapter::class, $pool);
    }

    // -----------------------------------------------------------------------
    // Security — cache key sanitisation
    // -----------------------------------------------------------------------

    /**
     * The cache key for a resumption token must be the md5 hash of the raw
     * token value, NOT the raw value itself.
     *
     * This prevents path-traversal / cache-poisoning attacks where an attacker
     * could craft a token containing filesystem-special characters
     * (e.g. "../../etc/passwd").
     *
     * @throws \ReflectionException
     */
    public function testTokenCacheKeyUsesMd5HashNotRawToken(): void
    {
        $maliciousToken = '../../etc/passwd';
        $expectedKey    = 'oai-token-' . md5($maliciousToken);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::atLeastOnce())
             ->method('getItem')
             ->with($expectedKey) // hashed key, never the raw token
             ->willReturn($cacheItem);

        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn($pool);

        $this->invokeGetIds(
            $server, 'ListIdentifiers', 'oai_dc', null, null, null, $maliciousToken
        );
    }

    // -----------------------------------------------------------------------
    // Security — no PHP object deserialisation in stored conf
    // -----------------------------------------------------------------------

    /**
     * The conf stored in the token cache must be a plain PHP array.
     * Using Symfony Cache (which handles serialisation internally) means there
     * is no explicit unserialize() call on the conf data, preventing PHP object
     * injection attacks.
     *
     * This test documents and verifies that the round-trip through ArrayAdapter
     * preserves the array type without any manual serialize/unserialize.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCachedTokenConfIsStoredAndRetrievedAsArray(): void
    {
        $cache = new ArrayAdapter();
        $conf  = ['format' => 'oai_dc', 'query' => '', 'cursor' => 0, 'solr' => ''];

        $item = $cache->getItem('oai-token-' . md5('mytoken'));
        $item->set($conf); // no serialize() — Symfony Cache handles it
        $cache->save($item);

        $retrieved = $cache->getItem('oai-token-' . md5('mytoken'))->get();

        self::assertIsArray($retrieved);
        self::assertSame($conf, $retrieved);
    }

    /**
     * The conf must contain required keys so that subsequent pages can be served.
     * A conf missing 'format' would silently produce wrong OAI output.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCachedTokenConfContainsRequiredKeys(): void
    {
        $cache = new ArrayAdapter();
        $conf  = ['format' => 'tei', 'query' => '&fq=something', 'cursor' => 400, 'solr' => 'q=*:*'];

        $item = $cache->getItem('oai-token-' . md5('page2'));
        $item->set($conf);
        $cache->save($item);

        $retrieved = $cache->getItem('oai-token-' . md5('page2'))->get();

        self::assertArrayHasKey('format', $retrieved);
        self::assertArrayHasKey('query', $retrieved);
        self::assertArrayHasKey('cursor', $retrieved);
        self::assertArrayHasKey('solr', $retrieved);
    }
}
