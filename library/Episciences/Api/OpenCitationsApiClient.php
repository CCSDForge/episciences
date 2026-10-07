<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Cache\InvalidArgumentException;

/**
 * OpenCitations REST API v2 client.
 *
 * Cache namespace : enrichmentCitations
 * Cache key      : sha1($doi) . '_citations.json'
 *
 */
class OpenCitationsApiClient extends AbstractApiClient
{
    private const CACHE_KEY_SUFFIX = '_citations.json';
    private const CITATIONS_PREFIX = 'coci => ';

    /**
     * Fetch DOIs that cite the given DOI from OpenCitations (API v2).
     *
     * Returns:
     *  - null  : API error (response not cached)
     *  - []    : API returned 0 citations (cached correctly)
     *  - array : list of citation rows from the API
     *
     * @return array<int, array<string, string>>|null
     * @throws InvalidArgumentException
     */
    public function fetchCitingDois(string $doi): ?array
    {
        $trimDoi = trim($doi);
        $key = sha1($trimDoi) . self::CACHE_KEY_SUFFIX;

        $cached = $this->getCached($key);
        if ($cached !== null) {
            $this->logger->info('OpenCitations data from cache for DOI ' . $trimDoi);
            return json_decode($cached, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        }

        $this->logger->info('Fetching OpenCitations data for DOI ' . $trimDoi);

        // OpenCitations v2 requires the ID parameter to have a scheme prefix (e.g. "doi:10.xxx/yyy")
        $apiId = str_starts_with($trimDoi, 'doi:') ? $trimDoi : 'doi:' . $trimDoi;

        $headers = $this->defaultHeaders();
        if (defined('OPENCITATIONS_TOKEN') && OPENCITATIONS_TOKEN !== '') {
            $headers['authorization'] = OPENCITATIONS_TOKEN;
        }

        $attempt = 0;
        $body = null;
        while ($attempt < static::MAX_RETRIES) {
            $attempt++;
            $this->throttle();

            try {
                $response = $this->client->get(OPENCITATIONS_APIURL . $apiId, [
                    'headers' => $headers,
                    'timeout' => 30,
                ]);

                $this->logRateLimitStatus($response, 'OpenCitations');
                $body = $response->getBody()->getContents();
                break;
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                // 429 Too Many Requests: back off and retry up to MAX_RETRIES (rate limit: 180 req/min).
                if ($statusCode === 429) {
                    if (!$this->isCliContext()) {
                        $this->logger->warning("OpenCitations 429 Too Many Requests for DOI {$trimDoi}; not retrying outside CLI execution");
                        return null;
                    }

                    if ($attempt >= static::MAX_RETRIES) {
                        $this->logger->critical("OpenCitations API: rate limit (429) exhausted after {$attempt} attempts for DOI {$trimDoi}, giving up");
                        return null;
                    }

                    $retryAfter = (int) $e->getResponse()->getHeaderLine('Retry-After');
                    $sleepSeconds = $retryAfter > 0 ? $retryAfter : ($attempt * 2);

                    $this->logger->warning("OpenCitations 429 Too Many Requests for DOI {$trimDoi} (attempt {$attempt}/" . static::MAX_RETRIES . "). Waiting {$sleepSeconds}s...");
                    $this->backoff($sleepSeconds);
                    continue;
                }

                $this->logger->error('OpenCitations API error for DOI ' . $trimDoi . " (HTTP {$statusCode}): " . $e->getMessage());
                return null;
            } catch (GuzzleException $e) {
                $this->logger->error('OpenCitations API connection error for DOI ' . $trimDoi . ': ' . $e->getMessage());
                return null;
            }
        }

        if ($body === null) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('OpenCitations API error decoding JSON for DOI ' . $trimDoi . ': ' . $e->getMessage());
            return null;
        }

        $this->saveToCache($key, $body);
        $this->logger->info('OpenCitations data cached for DOI ' . $trimDoi);

        return $decoded;
    }

    /**
     * Proactive throttle before each request to respect the OpenCitations rate limit
     * (180 req/min per IP, ~3 req/s). Extracted as an overridable seam for tests.
     */
    protected function throttle(): void
    {
        if (!$this->isCliContext()) {
            return;
        }
        usleep(static::AUTH_THROTTLE_MICROSECONDS);
    }

    /**
     * Extract clean DOI strings from OpenCitations citation rows.
     *
     * @param array<int, array<string, string>> $rows raw rows from the OpenCitations API
     * @return array<string> list of clean DOIs (empty string if no DOI found in a row)
     */
    public function extractCitingDois(array $rows): array
    {
        return array_map(function (array $row): string {
            $raw = trim(str_replace(self::CITATIONS_PREFIX, '', $row['citing'] ?? ''));

            if ($raw === '') {
                return '';
            }

            // Format A: space-separated identifiers with "doi:" prefix
            // e.g. "doi:10.xxx/yyy pmid:12345678"
            foreach (explode(' ', $raw) as $id) {
                if (str_starts_with($id, 'doi:')) {
                    $doi = substr($id, 4);
                    return (string) preg_replace('/;.*$/', '', $doi);
                }
            }

            // Format B: plain DOI with no prefix (current OpenCitations API format)
            // e.g. "10.4000/lisa.8913"
            if (str_starts_with($raw, '10.')) {
                return (string) preg_replace('/;.*$/', '', $raw);
            }

            return '';
        }, $rows);
    }
}
