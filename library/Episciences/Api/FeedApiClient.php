<?php

declare(strict_types=1);

namespace Episciences\Api;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Papers feeds of a journal, as built by the Episciences API (GET {api}/feed/{rss|atom}/{journal code}).
 *
 * Journal sites serve them unchanged under their own URLs (see RssController).
 * A feed is cached for a few hours: feed readers poll often, and the papers of a journal change rarely.
 * The last valid copy is kept longer: it is served when the API cannot provide the feed.
 */
final class FeedApiClient
{
    public const FORMAT_RSS = 'rss';
    public const FORMAT_ATOM = 'atom';
    public const FORMATS = [self::FORMAT_RSS, self::FORMAT_ATOM];
    // Root element of a feed of each format
    private const ROOT_ELEMENTS = [self::FORMAT_RSS => 'rss', self::FORMAT_ATOM => 'feed'];

    public const CACHE_TTL = 10800; // 3 hours
    // Lifetime of the last valid copy, served when the API fails
    public const STALE_TTL = 2592000; // 30 days
    // Delay before asking the API again after a failure (seconds)
    public const RETRY_DELAY = 600;
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 15;

    /**
     * @param (Closure(): int)|null $clock current Unix time, time() when null
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly ?Closure $clock = null
    ) {
    }

    /**
     * XML of the papers feed of a journal: the cached copy while it is fresh, else the feed of the API,
     * else the last valid copy; null when the API fails and no copy is left.
     *
     * @throws InvalidArgumentException unknown format
     */
    public function fetch(string $format, string $journalCode): ?string
    {
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown feed format "%s"', $format));
        }

        try {
            $item = $this->cache->getItem($this->cacheKey($format, $journalCode));
        } catch (CacheInvalidArgumentException) {
            $item = null;
        }

        $copy = $item !== null ? $this->cachedCopy($item) : null;

        if ($copy !== null && $copy['freshUntil'] > $this->now()) {
            return $copy['feed'];
        }

        $feed = $this->request($format, $this->feedUrl($format, $journalCode));

        if ($feed !== null) {
            $this->store($item, $feed, self::CACHE_TTL);
            return $feed;
        }

        if ($copy === null) {
            return null;
        }

        // The API is asked again after RETRY_DELAY, the last valid copy is served meanwhile
        $this->logger->info('Journal feed: serving the last valid copy', ['format' => $format, 'journal' => $journalCode]);
        $this->store($item, $copy['feed'], self::RETRY_DELAY);

        return $copy['feed'];
    }

    public function feedUrl(string $format, string $journalCode): string
    {
        return sprintf('%s/feed/%s/%s', rtrim($this->apiUrl, '/'), $format, rawurlencode($journalCode));
    }

    /**
     * @return array{feed: string, freshUntil: int}|null
     */
    private function cachedCopy(CacheItemInterface $item): ?array
    {
        if (!$item->isHit()) {
            return null;
        }

        $copy = $item->get();

        if (!is_array($copy) || !is_string($copy['feed'] ?? null) || !is_int($copy['freshUntil'] ?? null)) {
            return null;
        }

        return ['feed' => $copy['feed'], 'freshUntil' => $copy['freshUntil']];
    }

    /**
     * Keeps the feed for STALE_TTL, fresh for $freshFor seconds
     */
    private function store(?CacheItemInterface $item, string $feed, int $freshFor): void
    {
        if ($item === null) {
            return;
        }

        $item->set(['feed' => $feed, 'freshUntil' => $this->now() + $freshFor])->expiresAfter(self::STALE_TTL);
        $this->cache->save($item);
    }

    private function request(string $format, string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                RequestOptions::HEADERS => ['Accept' => 'application/xml'],
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
                RequestOptions::TIMEOUT => self::TIMEOUT,
            ]);
        } catch (GuzzleException $e) {
            $this->logger->warning('Journal feed: the Episciences API request failed', ['url' => $url, 'exception' => $e->getMessage()]);
            return null;
        }

        $feed = (string)$response->getBody();

        // A proxy or maintenance page answered with 200 must not be served (nor cached) as a feed
        if ($response->getStatusCode() !== 200 || !self::isFeed($feed, $format)) {
            $this->logger->warning('Journal feed: unexpected Episciences API answer', ['url' => $url, 'status' => $response->getStatusCode()]);
            return null;
        }

        return $feed;
    }

    /**
     * Well-formed XML whose root element is the one of the format (the Content-Type of feeds is often wrong)
     */
    private static function isFeed(string $content, string $format): bool
    {
        if (trim($content) === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml !== false && $xml->getName() === self::ROOT_ELEMENTS[$format];
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    private function cacheKey(string $format, string $journalCode): string
    {
        // Journal codes may hold characters reserved in PSR-6 keys
        return sprintf('feed_%s_%s', $format, md5($journalCode));
    }
}
