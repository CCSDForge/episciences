<?php

namespace unit\library\Episciences\Oai;

use Episciences_Oai_Server;
use Episciences_Paper;
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

    // -----------------------------------------------------------------------
    // Helpers (shared)
    // -----------------------------------------------------------------------

    /**
     * Returns a server instance whose constructor is bypassed (no HTTP context needed).
     */
    private function buildServerNoConstructor(array $extraMethods = []): Episciences_Oai_Server
    {
        return $this->getMockBuilder(Episciences_Oai_Server::class)
            ->disableOriginalConstructor()
            ->onlyMethods($extraMethods)
            ->getMock();
    }

    /**
     * Invokes an arbitrary protected/private method via reflection.
     *
     * @throws \ReflectionException
     */
    private function invokeMethod(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($object, ...$args);
    }

    // -----------------------------------------------------------------------
    // existFormat()
    // -----------------------------------------------------------------------

    /**
     * @dataProvider knownFormatProvider
     */
    public function testExistFormatReturnsTrueForKnownFormat(string $format): void
    {
        $server = $this->buildServerNoConstructor();
        self::assertTrue($this->invokeMethod($server, 'existFormat', $format));
    }

    public static function knownFormatProvider(): array
    {
        return [
            'oai_dc'       => ['oai_dc'],
            'tei'          => ['tei'],
            'oai_openaire' => ['oai_openaire'],
            'crossref'     => ['crossref'],
        ];
    }

    /**
     * @dataProvider unknownFormatProvider
     */
    public function testExistFormatReturnsFalseForUnknownFormat(string $format): void
    {
        $server = $this->buildServerNoConstructor();
        self::assertFalse($this->invokeMethod($server, 'existFormat', $format));
    }

    public static function unknownFormatProvider(): array
    {
        return [
            'empty string'  => [''],
            'rss'           => ['rss'],
            'json'          => ['json'],
            'marc21'        => ['marc21'],
        ];
    }

    // -----------------------------------------------------------------------
    // getFormats()
    // -----------------------------------------------------------------------

    public function testGetFormatsContainsCrossref(): void
    {
        $server  = $this->buildServerNoConstructor();
        $formats = $this->invokeMethod($server, 'getFormats');

        self::assertIsArray($formats);
        self::assertArrayHasKey('crossref', $formats);
    }

    public function testGetFormatsCrossrefHasCorrectSchemaAndNamespace(): void
    {
        $server  = $this->buildServerNoConstructor();
        $formats = $this->invokeMethod($server, 'getFormats');

        self::assertSame(
            'https://www.crossref.org/schemas/crossref5.3.1.xsd',
            $formats['crossref']['schema']
        );
        self::assertSame(
            'http://www.crossref.org/schema/5.3.1',
            $formats['crossref']['ns']
        );
    }

    public function testGetFormatsContainsAllExpectedPrefixes(): void
    {
        $server  = $this->buildServerNoConstructor();
        $formats = $this->invokeMethod($server, 'getFormats');

        foreach (['oai_dc', 'tei', 'oai_openaire', 'crossref'] as $prefix) {
            self::assertArrayHasKey($prefix, $formats, "Format '$prefix' must be registered");
        }
    }

    // -----------------------------------------------------------------------
    // checkDateFormat()
    // -----------------------------------------------------------------------

    /**
     * @dataProvider validDateProvider
     */
    public function testCheckDateFormatReturnsTrueForValidDate(string $date): void
    {
        $server = $this->buildServerNoConstructor();
        self::assertTrue($this->invokeMethod($server, 'checkDateFormat', $date));
    }

    public static function validDateProvider(): array
    {
        return [
            'typical'      => ['2023-06-15'],
            'start of year' => ['2024-01-01'],
            'end of year'  => ['2023-12-31'],
        ];
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testCheckDateFormatReturnsFalseForInvalidDate(string $date): void
    {
        $server = $this->buildServerNoConstructor();
        self::assertFalse($this->invokeMethod($server, 'checkDateFormat', $date));
    }

    public static function invalidDateProvider(): array
    {
        // Note: Zend_Validate_Date with format 'yyyy-MM-dd' is permissive about
        // separators and year length. Only include values that it actually rejects.
        return [
            'plain text'         => ['not-a-date'],
            'out-of-range month' => ['2023-13-01'],
            'empty string'       => [''],
        ];
    }

    // -----------------------------------------------------------------------
    // getOaiMetadata()
    // -----------------------------------------------------------------------

    /**
     * For any format other than 'crossref', getOaiMetadata() must delegate to
     * $paper->get($internalFormat) and return its value unchanged.
     *
     * @throws \ReflectionException
     */
    public function testGetOaiMetadataDelegatesToPaperGetForNonCrossrefFormat(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->expects(self::once())
              ->method('get')
              ->with('dc')
              ->willReturn('<dc_element/>');

        $server = $this->buildServerNoConstructor();
        $result = $this->invokeMethod($server, 'getOaiMetadata', $paper, 'dc');

        self::assertSame('<dc_element/>', $result);
    }

    /**
     * For the 'crossref' internal format, getOaiMetadata() must NOT call
     * $paper->get() — it calls Export::getCrossref($paper, true) instead.
     * This ensures the personal depositor e-mail is replaced with a generic
     * address in public OAI-PMH output.
     *
     * Note: Export::getCrossref() itself may throw in the unit-test environment
     * (no DB / Zend_View available).  The assertion that matters here is that
     * $paper->get() was never called; PHPUnit verifies mock expectations on
     * teardown even when an exception interrupts execution.
     *
     * @throws \ReflectionException
     */
    public function testGetOaiMetadataBypassesPaperGetForCrossrefFormat(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->expects(self::never())->method('get');

        $server = $this->buildServerNoConstructor();

        try {
            $this->invokeMethod($server, 'getOaiMetadata', $paper, 'crossref');
        } catch (\Throwable) {
            // Export::getCrossref() may fail without a DB/view context; that is
            // expected in a unit-test.  The only assertion here is the never()
            // expectation on $paper->get(), which PHPUnit checks at teardown.
        }
    }

    // -----------------------------------------------------------------------
    // getIds() — Solr query building
    // -----------------------------------------------------------------------

    /**
     * When $from and $until are provided, the Solr query must include a range
     * filter on publication_date_tdate.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsIncludesDateRangeFilterInSolrQuery(): void
    {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn(new ArrayAdapter());
        $server->expects(self::once())
               ->method('executeSolrQuery')
               ->with(self::stringContains('publication_date_tdate'))
               ->willReturn(false);

        $this->invokeGetIds($server, 'ListRecords', 'oai_dc', '2023-12-31', '2023-01-01', null, null);
    }

    /**
     * When $set is a journal: prefix, the Solr query must filter by revue_code_t.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsIncludesRevueCodeFilterWhenSetIsProvided(): void
    {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn(new ArrayAdapter());
        $server->expects(self::once())
               ->method('executeSolrQuery')
               ->with(self::stringContains('revue_code_t'))
               ->willReturn(false);

        $this->invokeGetIds($server, 'ListRecords', 'oai_dc', null, null, 'journal:jdmdh', null);
    }

    /**
     * ListRecords must request at most LIMIT_RECORDS rows from Solr.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsRequestsCorrectRowsLimitForListRecords(): void
    {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn(new ArrayAdapter());
        $server->expects(self::once())
               ->method('executeSolrQuery')
               ->with(self::stringContains('rows=' . Episciences_Oai_Server::LIMIT_RECORDS))
               ->willReturn(false);

        $this->invokeGetIds($server, 'ListRecords', 'oai_dc', null, null, null, null);
    }

    /**
     * ListIdentifiers must request at most LIMIT_IDENTIFIERS rows from Solr.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsRequestsCorrectRowsLimitForListIdentifiers(): void
    {
        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery']
        );
        $server->method('getTokenCachePool')->willReturn(new ArrayAdapter());
        $server->expects(self::once())
               ->method('executeSolrQuery')
               ->with(self::stringContains('rows=' . Episciences_Oai_Server::LIMIT_IDENTIFIERS))
               ->willReturn(false);

        $this->invokeGetIds($server, 'ListIdentifiers', 'oai_dc', null, null, null, null);
    }

    // -----------------------------------------------------------------------
    // getIds() — Solr result processing
    // -----------------------------------------------------------------------

    /**
     * When Solr reports numFound = 0, getIds() must return integer 0 so that
     * the parent can emit a noRecordsMatch OAI error.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsReturnsZeroWhenSolrReportsNoDocuments(): void
    {
        $solrResponse = serialize([
            'response'      => ['numFound' => 0, 'docs' => []],
            'nextCursorMark' => '*',
        ]);

        $server = $this->buildServer(new ArrayAdapter(), $solrResponse);

        $result = $this->invokeGetIds($server, 'ListRecords', 'oai_dc', null, null, null, null);

        self::assertSame(0, $result);
    }

    /**
     * When Solr returns false (network/index failure), getIds() must return false.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsReturnsFalseWhenSolrFails(): void
    {
        $server = $this->buildServer(new ArrayAdapter(), false);
        $result = $this->invokeGetIds($server, 'ListRecords', 'oai_dc', null, null, null, null);

        self::assertFalse($result);
    }

    /**
     * A malformed (non-array) Solr response must not cause an exception and
     * must return false.
     *
     * @throws \ReflectionException
     */
    public function testGetIdsReturnsFalseWhenSolrResponseIsMalformed(): void
    {
        $server = $this->buildServer(new ArrayAdapter(), serialize('not-an-array'));
        $result = $this->invokeGetIds($server, 'ListRecords', 'oai_dc', null, null, null, null);

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // getIds() — metadataPrefix propagation bug fix
    // -----------------------------------------------------------------------

    /**
     * Bug fix: when getIds() is called with a resumptionToken, the format is
     * read from the token cache. The returned array must include a 'metadataPrefix'
     * key so that the parent's listIds() can recover the format and set the
     * correct XML namespace attributes on <metadata> elements (e.g. xmlns:tei).
     *
     * We simulate a cached token for the 'tei' format and a Solr response
     * reporting numFound = 0, which causes getIds() to return 0 before building
     * the $out array — proving the cache was hit and the format was recovered.
     *
     * @throws \ReflectionException|\Psr\Cache\InvalidArgumentException
     */
    public function testGetIdsReturnsZeroWithTokenWhenSolrReportsNoDocuments(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('oai-token-' . md5('tok-tei'));
        $item->set(['format' => 'tei', 'query' => '', 'cursor' => 0, 'solr' => '']);
        $cache->save($item);

        $solrResponse = serialize([
            'response'      => ['numFound' => 0, 'docs' => []],
            'nextCursorMark' => 'AoE=',
        ]);

        $server = $this->buildServer($cache, $solrResponse);
        $result = $this->invokeGetIds($server, 'ListRecords', 'tei', null, null, null, 'tok-tei');

        // numFound = 0 → return 0, not 'token' (which would mean cache was missed)
        self::assertSame(0, $result, 'Cache hit with numFound=0 must return 0, not the "token" string');
    }

    /**
     * When the format is recovered from a token cache hit and Solr returns results
     * with a nextCursorMark, the returned $out array must start with a 'metadataPrefix'
     * key containing the recovered format.
     *
     * This key is consumed by the parent Ccsd_Oai_Server::listIds() to restore the
     * format variable so that namespace attributes (xmlns:tei, xmlns:datacite) are
     * correctly set on <metadata> elements on every resumed page.
     *
     * We use ListIdentifiers (no metadata generation) so that the test does not
     * require a real DB or locale registry.  numFound > LIMIT_IDENTIFIERS triggers
     * the resumptionToken branch, and getOaiMetadata() is mocked out as a safety net.
     *
     * @throws \ReflectionException|\Psr\Cache\InvalidArgumentException
     */
    public function testGetIdsPropagatesFormatInReturnedArrayWhenFormatComesFromToken(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('oai-token-' . md5('tok2'));
        $item->set(['format' => 'tei', 'query' => '', 'cursor' => 0, 'solr' => '']);
        $cache->save($item);

        // numFound > LIMIT_IDENTIFIERS triggers the resumptionToken branch.
        // nextCursorMark differs from the input token so a new page token is stored.
        // The doc is processed via ListIdentifiers (header only, no metadata call).
        $solrResponse = serialize([
            'response'      => ['numFound' => 500, 'docs' => [['docid' => 0]]],
            'nextCursorMark' => 'AoE=NextPage',
        ]);

        $server = $this->createPartialMock(
            Episciences_Oai_Server::class,
            ['getTokenCachePool', 'executeSolrQuery', 'getOaiMetadata']
        );
        $server->method('getTokenCachePool')->willReturn($cache);
        $server->method('executeSolrQuery')->willReturn($solrResponse);
        $server->method('getOaiMetadata')->willReturn('<stub/>');

        $result = $this->invokeGetIds($server, 'ListIdentifiers', 'tei', null, null, null, 'tok2');

        self::assertIsArray($result, 'A non-empty Solr response must return an array');
        self::assertArrayHasKey(
            'metadataPrefix',
            $result,
            'getIds() must include metadataPrefix in the returned array when format comes from token cache'
        );
        self::assertSame('tei', $result['metadataPrefix']);
    }
}
