<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Base class for all Episciences API clients.
 *
 * Provides shared caching helpers, default HTTP headers, and JSON constants.
 * Concrete subclasses must inject a Guzzle Client and a PSR-6 cache pool
 * (either real FilesystemAdapter or ArrayAdapter for tests).
 */
abstract class AbstractApiClient
{
    public const ONE_MONTH = 3600 * 24 * 31;
    protected const JSON_MAX_DEPTH = 512;

    protected Client $client;
    protected CacheItemPoolInterface $cache;
    protected LoggerInterface $logger;

    public function __construct(Client $client, CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Default HTTP headers shared by all API clients.
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'User-Agent'   => EPISCIENCES_USER_AGENT,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Return cached string value for key, or null on cache miss.
     *
     * @throws InvalidArgumentException
     */
    protected function getCached(string $key): ?string
    {
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return (string) $item->get();
        }
        return null;
    }

    /**
     * Store a string value in cache with the default TTL (ONE_MONTH).
     *
     * @throws InvalidArgumentException
     */
    protected function saveToCache(string $key, string $data): void
    {
        $item = $this->cache->getItem($key);
        $item->set($data);
        $item->expiresAfter(self::ONE_MONTH);
        $this->cache->save($item);
    }
}
