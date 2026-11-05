<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

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
    private const HAL_API_BASE_URL = 'https://api.archives-ouvertes.fr/search/';
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
     * Fetch TEI from HAL API and store it in cache
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * An empty response (failed HAL call) is not cached, so that the next process tries again;
     * within this process, the failed TEI is not requested again.
     *
     * @return bool true if fetched from HAL and cached, false if already cached or unavailable
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function fetchAndCache(string $identifier, int $version = 0): bool
    {
        $cacheKey = self::buildCacheKey($identifier, $version);
        $cache = self::createCacheAdapter();
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::CACHE_TTL);

        if (self::holdsTei($cacheItem) || isset(self::$unavailable[$cacheKey])) {
            return false;
        }

        $teiXml = self::fetchFromApi($identifier, $version);

        if (trim($teiXml) === '') {
            self::$unavailable[$cacheKey] = true;
            return false;
        }

        $cacheItem->set($teiXml);
        $cache->save($cacheItem);

        return true;
    }

    /**
     * Drop the cached TEI, so that the next fetch reads the document from HAL again
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function delete(string $identifier, int $version = 0): void
    {
        $cacheKey = self::buildCacheKey($identifier, $version);
        unset(self::$unavailable[$cacheKey]);
        self::createCacheAdapter()->deleteItem($cacheKey);
    }

    /**
     * Retrieve cached TEI XML string
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return string TEI XML content, or empty string if not cached
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getFromCache(string $identifier, int $version = 0): string
    {
        $cacheKey = self::buildCacheKey($identifier, $version);
        $cache = self::createCacheAdapter();
        $cacheItem = $cache->getItem($cacheKey);

        return self::holdsTei($cacheItem) ? $cacheItem->get() : '';
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

    /**
     * Fetch TEI from HAL API if not cached, then return the cached content
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return string TEI XML content, or empty string on failure
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function fetchAndGet(string $identifier, int $version = 0): string
    {
        self::fetchAndCache($identifier, $version);
        return self::getFromCache($identifier, $version);
    }

    private static function createCacheAdapter(): CacheItemPoolInterface
    {
        return self::$cachePool ?? new FilesystemAdapter(self::CACHE_NAMESPACE, self::CACHE_TTL, dirname(APPLICATION_PATH) . '/cache/');
    }

    private static function buildCacheKey(string $identifier, int $version): string
    {
        $key = self::CACHE_KEY_PREFIX . $identifier;

        if ($version !== 0) {
            $key .= '-' . $version;
        }

        return $key . self::CACHE_KEY_EXTENSION;
    }

    /**
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return string raw TEI XML response
     */
    private static function fetchFromApi(string $identifier, int $version): string
    {
        $client = self::$httpClient ?? new GuzzleClient();
        $url = self::buildApiUrl($identifier, $version);
        $timeout = (PHP_SAPI === 'cli') ? self::TIMEOUT_CLI : self::TIMEOUT_WEB;

        try {
            return $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::HAL_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'timeout' => $timeout
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
        }

        return '';
    }

    /**
     * @todo Solr query escaping is also missing in 5+ other files across the codebase
     */
    private static function buildApiUrl(string $identifier, int $version): string
    {
        $safeIdentifier = urlencode($identifier);
        $halIdQuery = '(halId_s:' . $safeIdentifier . ' OR halIdSameAs_s:' . $safeIdentifier . ')';

        if ($version === 0) {
            return self::HAL_API_BASE_URL . '?q=(' . $halIdQuery . ')&wt=xml-tei';
        }

        return self::HAL_API_BASE_URL . '?q=(' . $halIdQuery . ' AND version_i:' . $version . ')&wt=xml-tei';
    }
}
