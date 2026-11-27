<?php

use Episciences\AppRegistry;
use Episciences\Hal\HalTeiSource;
use Episciences\Hal\OaiTeiResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * TEI of HAL document versions, read from the OAI-PMH server of their repository (see HalTeiSource)
 * and cached: the licence, author, funding and linked data enrichments of a paper share one request.
 */
class Episciences_Hal_TeiCacheManager
{
    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * Lifetime of a cached TEI: HAL metadata (fundings, affiliations, licence) can be edited without
     * a new document version, so a cached TEI must not hide such an edit for long
     */
    public const CACHE_TTL = 3600 * 24;

    private const CACHE_NAMESPACE = 'halTei';
    private const CACHE_KEY_PREFIX = 'hal-tei-';
    private const CACHE_KEY_EXTENSION = '.xml';
    private const HAL_USER_AGENT = 'CCSD Episciences support@episciences.org';
    private const TIMEOUT_WEB = 3;
    private const TIMEOUT_CLI = 40;

    private static ?CacheItemPoolInterface $cachePool = null;

    private static ?ClientInterface $httpClient = null;

    /**
     * Cache keys of the TEI that HAL failed to return in this process (request or CLI run):
     * a failed TEI is not cached, so the next process tries again, but the licence, author and
     * funding enrichments of the same paper do not each wait for HAL once more.
     *
     * @var array<string, true>
     */
    private static array $unavailable = [];

    /**
     * Replace the cache pool (tests); null restores the filesystem cache. Forgets the failed fetches.
     */
    public static function setCachePool(?CacheItemPoolInterface $cachePool): void
    {
        self::$cachePool = $cachePool;
        self::$unavailable = [];
    }

    /**
     * Replace the HTTP client (tests); null restores a default Guzzle client
     */
    public static function setHttpClient(?ClientInterface $httpClient): void
    {
        self::$httpClient = $httpClient;
    }

    /**
     * Fetch the TEI from HAL and store it in cache, unless it is cached already.
     * A failed request is not cached, so that the next process tries again;
     * within this process, the failed TEI is not requested again.
     *
     * @return bool true if fetched from HAL and cached, false if already cached or unavailable
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function fetchAndCache(HalTeiSource $source): bool
    {
        $cache = self::createCacheAdapter();
        $cacheItem = $cache->getItem(self::buildCacheKey($source));
        $cacheItem->expiresAfter(self::CACHE_TTL);

        if (self::holdsTei($cacheItem) || isset(self::$unavailable[$cacheItem->getKey()])) {
            return false;
        }

        return self::fetchAndStore($cache, $cacheItem, $source);
    }

    /**
     * Read the TEI from HAL again, so that metadata edited on HAL without a new version are seen.
     * The cached TEI is replaced only once HAL returned the new one: when HAL fails, the TEI cached
     * before, if any, is kept, and the failed TEI is not requested again in this process.
     *
     * @return bool true if the TEI was read from HAL and cached
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function refresh(HalTeiSource $source): bool
    {
        $cache = self::createCacheAdapter();
        $cacheItem = $cache->getItem(self::buildCacheKey($source));
        $cacheItem->expiresAfter(self::CACHE_TTL);

        return self::fetchAndStore($cache, $cacheItem, $source);
    }

    /**
     * Retrieve the cached TEI
     *
     * @return string TEI XML content, or empty string if not cached
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getFromCache(HalTeiSource $source): string
    {
        $cacheItem = self::createCacheAdapter()->getItem(self::buildCacheKey($source));

        return self::holdsTei($cacheItem) ? $cacheItem->get() : '';
    }

    /**
     * Fetch the TEI from HAL if not cached, then return the cached content
     *
     * @return string TEI XML content, or empty string on failure
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function fetchAndGet(HalTeiSource $source): string
    {
        self::fetchAndCache($source);
        return self::getFromCache($source);
    }

    /**
     * Fetch the TEI from HAL and store it in the cache item; a failure is not stored
     */
    private static function fetchAndStore(CacheItemPoolInterface $cache, CacheItemInterface $cacheItem, HalTeiSource $source): bool
    {
        $tei = self::fetchFromOai($source);

        if ($tei === null) {
            self::$unavailable[$cacheItem->getKey()] = true;
            return false;
        }

        unset(self::$unavailable[$cacheItem->getKey()]);
        $cacheItem->set($tei);
        $cache->save($cacheItem);

        return true;
    }

    /**
     * Whether the cache item holds a TEI. Before failed fetches stopped being cached, an empty
     * response was stored like a TEI: such an entry is a miss, so that HAL is asked again.
     */
    private static function holdsTei(CacheItemInterface $cacheItem): bool
    {
        if (!$cacheItem->isHit()) {
            return false;
        }

        $tei = $cacheItem->get();

        return is_string($tei) && trim($tei) !== '';
    }

    private static function createCacheAdapter(): CacheItemPoolInterface
    {
        return self::$cachePool ?? new FilesystemAdapter(self::CACHE_NAMESPACE, self::CACHE_TTL, dirname(APPLICATION_PATH) . '/cache/');
    }

    /**
     * One key per repository: two HAL instances (production, preprod) may hold the same identifier
     */
    private static function buildCacheKey(HalTeiSource $source): string
    {
        $key = self::CACHE_KEY_PREFIX . $source->repoId . '-' . $source->identifier;

        if ($source->version !== 0) {
            $key .= '-v' . $source->version;
        }

        return $key . self::CACHE_KEY_EXTENSION;
    }

    /**
     * @return string|null the TEI, null on failure (reported)
     */
    private static function fetchFromOai(HalTeiSource $source): ?string
    {
        $url = $source->getRecordUrl();

        if ($url === null) {
            AppRegistry::getMonoLogger()?->warning(sprintf('HAL TEI of %s: the repository has no OAI-PMH server', $source->describe()));
            return null;
        }

        $client = self::$httpClient ?? new GuzzleClient();

        try {
            $body = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::HAL_USER_AGENT,
                    'Accept' => 'application/xml, text/xml',
                ],
                'timeout' => (PHP_SAPI === 'cli') ? self::TIMEOUT_CLI : self::TIMEOUT_WEB,
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
            return null;
        }

        // An OAI-PMH error (unknown identifier or version) or a maintenance page come with a 200 status
        $response = OaiTeiResponse::parse($body);

        if (!$response->isTei()) {
            AppRegistry::getMonoLogger()?->warning(sprintf('HAL TEI of %s not read from %s: %s', $source->describe(), $url, $response->error));
        }

        return $response->tei;
    }
}
