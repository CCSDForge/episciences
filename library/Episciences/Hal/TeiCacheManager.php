<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Hal_TeiCacheManager
{
    public const ONE_MONTH = 3600 * 24 * 31;

    private const CACHE_NAMESPACE = 'halTei';
    private const CACHE_KEY_PREFIX = 'hal-tei-';
    private const CACHE_KEY_EXTENSION = '.xml';
    private const HAL_API_BASE_URL = 'https://api.archives-ouvertes.fr/search/';
    private const HAL_USER_AGENT = 'CCSD Episciences support@episciences.org';
    private const TIMEOUT_WEB = 3;
    private const TIMEOUT_CLI = 40;

    /**
     * Fetch TEI from HAL API and store it in cache
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return bool true if fetched from API, false if already cached
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function fetchAndCache(string $identifier, int $version = 0): bool
    {
        $cacheKey = self::buildCacheKey($identifier, $version);
        $cache = self::createCacheAdapter();
        $cacheItem = $cache->getItem($cacheKey);
        $cacheItem->expiresAfter(self::ONE_MONTH);

        if (!$cacheItem->isHit()) {
            $teiXml = self::fetchFromApi($identifier, $version);
            $cacheItem->set($teiXml);
            $cache->save($cacheItem);
            return true;
        }

        return false;
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
        $cacheItem->expiresAfter(self::ONE_MONTH);

        if (!$cacheItem->isHit()) {
            return '';
        }

        return $cacheItem->get();
    }

    private static function createCacheAdapter(): FilesystemAdapter
    {
        return new FilesystemAdapter(self::CACHE_NAMESPACE, self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
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
        $client = new GuzzleClient();
        $url = self::buildApiUrl($identifier, $version);
        $timeout = (PHP_SAPI === 'cli') ? self::TIMEOUT_CLI : self::TIMEOUT_WEB;

        try {
            return $client->get($url, [
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

    private static function buildApiUrl(string $identifier, int $version): string
    {
        $halIdQuery = '(halId_s:' . $identifier . ' OR halIdSameAs_s:' . $identifier . ')';

        if ($version === 0) {
            return self::HAL_API_BASE_URL . '?q=(' . $halIdQuery . ')&wt=xml-tei';
        }

        return self::HAL_API_BASE_URL . '?q=(' . $halIdQuery . ' AND version_i:' . $version . ')&wt=xml-tei';
    }
}
