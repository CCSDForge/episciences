<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException;

/**
 * Crossref REST API client.
 *
 * Cache namespace : enrichmentCitations
 * Cache key      : sha1($doi) . '_citationsMetadatas_crossref.json'
 *
 */
class CrossrefApiClient extends AbstractApiClient
{
    private const CACHE_KEY_SUFFIX = '_citationsMetadatas_crossref.json';
    public const TOKEN_HEADER = 'Crossref-Plus-API-Token';

    /**
     * Fetch Crossref metadata for a DOI.
     *
     * @return array<string, mixed>|null null means API error (not cached); array may be empty on no-data.
     * @throws InvalidArgumentException
     */
    public function fetchMetadata(string $doi): ?array
    {
        $key = sha1($doi) . self::CACHE_KEY_SUFFIX;

        $cached = $this->getCached($key);
        if ($cached !== null) {
            return json_decode($cached, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        }

        $this->logger->info('Fetching Crossref metadata for DOI ' . $doi);

        $headers = $this->defaultHeaders();

        // @phpstan-ignore notIdentical.alwaysTrue
        if (defined('CROSSREF_PLUS_API_TOKEN') && (string) constant('CROSSREF_PLUS_API_TOKEN') !== '') {
            $headers[self::TOKEN_HEADER] = 'Bearer ' . constant('CROSSREF_PLUS_API_TOKEN');
        } else {
            // Rate-limit without token: 0.5s between calls
            usleep(500000);
        }

        $url = CROSSREF_APIURL . rawurlencode($doi) . '?mailto=' . rawurlencode(CROSSREF_MAILTO);

        try {
            $body = $this->client->get($url, ['headers' => $headers])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf('Crossref API error for DOI %s — code %s: %s', $doi, $e->getCode(), $e->getMessage()));
            return null;
        }

        if ($body === '') {
            return null;
        }

        $this->saveToCache($key, $body);
        return json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * Extract container-title (journal/book) from Crossref metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function extractLocation(array $metadata): string
    {
        return $metadata['message']['container-title'][0] ?? '';
    }

    /**
     * Extract event location from Crossref metadata (for proceedings).
     *
     * @param array<string, mixed> $metadata
     */
    public function extractEventPlace(array $metadata): string
    {
        return $metadata['message']['event']['location'] ?? '';
    }
}
