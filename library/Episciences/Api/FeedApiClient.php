<?php

declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Papers feeds of a journal, as built by the Episciences API (GET {api}/feed/{rss|atom}/{journal code}).
 *
 * Journal sites serve them unchanged under their own URLs (see RssController).
 * A feed is cached for a few hours: feed readers poll often, and the papers of a journal change rarely.
 */
final class FeedApiClient
{
    public const FORMAT_RSS = 'rss';
    public const FORMAT_ATOM = 'atom';
    public const FORMATS = [self::FORMAT_RSS, self::FORMAT_ATOM];

    public const CACHE_TTL = 10800; // 3 hours
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 15;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl
    ) {
    }

    /**
     * XML of the papers feed of a journal, null when the API cannot provide it.
     *
     * @throws InvalidArgumentException unknown format
     */
    public function fetch(string $format, string $journalCode): ?string
    {
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown feed format "%s"', $format));
        }

        $url = $this->feedUrl($format, $journalCode);

        try {
            $item = $this->cache->getItem($this->cacheKey($format, $journalCode));
        } catch (CacheInvalidArgumentException) {
            $item = null;
        }

        if ($item !== null && $item->isHit() && is_string($item->get())) {
            return $item->get();
        }

        $feed = $this->request($url);

        if ($feed !== null && $item !== null) {
            $this->cache->save($item->set($feed)->expiresAfter(self::CACHE_TTL));
        }

        return $feed;
    }

    public function feedUrl(string $format, string $journalCode): string
    {
        return sprintf('%s/feed/%s/%s', rtrim($this->apiUrl, '/'), $format, rawurlencode($journalCode));
    }

    private function request(string $url): ?string
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

        if ($response->getStatusCode() !== 200 || trim($feed) === '') {
            $this->logger->warning('Journal feed: unexpected Episciences API answer', ['url' => $url, 'status' => $response->getStatusCode()]);
            return null;
        }

        return $feed;
    }

    private function cacheKey(string $format, string $journalCode): string
    {
        // Journal codes may hold characters reserved in PSR-6 keys
        return sprintf('feed_%s_%s', $format, md5($journalCode));
    }
}
