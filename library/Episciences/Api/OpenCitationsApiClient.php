<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException;

/**
 * OpenCitations REST API client.
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
     * Fetch DOIs that cite the given DOI from OpenCitations.
     *
     * Returns:
     *  - null  : API error (response not cached)
     *  - []    : API returned 0 citations (bug #5: cached correctly)
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

        // Rate limiting
        usleep(500000);

        try {
            $body = $this->client->get(OPENCITATIONS_APIURL . $trimDoi, [
                'headers' => array_merge($this->defaultHeaders(), [
                    'authorization' => OPENCITATIONS_TOKEN,
                ]),
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error('OpenCitations API error: ' . $e->getMessage());
            return null;
        }

        $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

        $this->saveToCache($key, $body);
        $this->logger->info('OpenCitations data cached for DOI ' . $trimDoi);

        return $decoded;
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
