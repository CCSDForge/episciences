<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException;

/**
 * OpenAlex REST API client.
 *
 * Cache namespace : enrichmentCitations
 * Cache key      : sha1($doi) . '_citationsMetadatas.json'
 *
 * @phpstan-type OaLocation array{source: array<string, mixed>|null, is_oa: bool, landing_page_url?: string, source_title?: string}
 * @phpstan-type OaInfo array{source_title: string, oa_link: string}
 */
class OpenAlexApiClient extends AbstractApiClient
{
    private const CACHE_KEY_SUFFIX = '_citationsMetadatas.json';
    public const PARAMS = '?select=title,authorships,open_access,biblio,primary_location,locations,publication_year,best_oa_location,type_crossref';
    public const UNWANTED_HAL_STRINGS = ['HAL (Le Centre pour la Communication Scientifique Directe)'];

    /**
     * Fetch OpenAlex metadata for a DOI.
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

        // Rate limiting: always 0.5s between calls
        usleep(500000);

        $url = OPENALEX_APIURL . 'https://doi.org/' . $doi . self::PARAMS . '&mailto=' . OPENALEX_MAILTO;

        try {
            $body = $this->client->get($url, ['headers' => $this->defaultHeaders()])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error('OpenAlex API error for DOI ' . $doi . ': ' . $e->getMessage());
            return null;
        }

        if ($body === '') {
            return null;
        }

        $this->saveToCache($key, $body);
        return json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * Format author list into a semicolon-separated string.
     *
     * @param array<int, array<string, mixed>> $authorList
     */
    public function formatAuthors(array $authorList): string
    {
        if (empty($authorList)) {
            return '';
        }

        $parts = [];
        foreach ($authorList as $authorInfo) {
            $entry = (string) $authorInfo['raw_author_name'];
            if (isset($authorInfo['author']['orcid'])) {
                $entry .= ', ' . str_replace('https://orcid.org/', '', (string) $authorInfo['author']['orcid']);
            }
            $parts[] = $entry;
        }

        return implode('; ', $parts);
    }

    /**
     * Format first and last page into a "fp-lp" string.
     */
    public function formatPages(?string $fp, ?string $lp): string
    {
        if ($fp === null) {
            return '';
        }
        if ($lp === null || $fp === $lp) {
            return $fp;
        }
        return $fp . '-' . $lp;
    }

    /**
     * Resolve the best open-access info from OpenAlex location data.
     *
     * @param array<string, mixed>|null $primary
     * @param array<int, array<string, mixed>> $locations
     * @param array<string, mixed>|null $bestOa
     * @return array{source_title: string, oa_link: string}|string
     */
    public function resolveBestOaInfo(?array $primary, array $locations, ?array $bestOa): array|string
    {
        if ($bestOa !== null
            && isset($bestOa['source'])
            && is_array($bestOa['source'])
        ) {
            return [
                'source_title' => (string) ($bestOa['source']['display_name'] ?? ''),
                'oa_link'      => (string) ($bestOa['landing_page_url'] ?? ''),
            ];
        }

        if ($primary !== null
            && isset($primary['is_oa'])
            && $primary['is_oa'] === true
            && isset($primary['source'])
            && is_array($primary['source'])
        ) {
            return [
                'source_title' => (string) ($primary['source']['display_name'] ?? ''),
                'oa_link'      => (string) ($primary['landing_page_url'] ?? ''),
            ];
        }

        foreach ($locations as $location) {
            if (($location['is_oa'] ?? false) === true
                && isset($location['source'])
                && is_array($location['source'])
            ) {
                if ((string) ($location['source']['type'] ?? '') !== 'journal') {
                    $journal = $this->findJournalNameInLocations($locations);
                    if ($journal === '') {
                        $journal = (string) ($location['source']['display_name'] ?? '');
                    }
                } else {
                    $journal = (string) ($location['source']['display_name'] ?? '');
                }
                return ['source_title' => $journal, 'oa_link' => (string) ($location['landing_page_url'] ?? '')];
            }
        }

        return $this->findFirstAlternativeLocation($locations);
    }

    /**
     * Strip unwanted HAL display strings from a location name.
     */
    public function removeHalString(string $location): string
    {
        return in_array($location, self::UNWANTED_HAL_STRINGS, true) ? '' : $location;
    }

    /**
     * Find journal name by scanning locations array (O(n)).
     *
     * @param array<int, array<string, mixed>> $locations
     */
    private function findJournalNameInLocations(array $locations): string
    {
        foreach ($locations as $location) {
            if (!isset($location['source']) || !is_array($location['source'])) {
                continue;
            }
            if ((string) ($location['source']['type'] ?? '') === 'journal') {
                return (string) ($location['source']['display_name'] ?? '');
            }
            if ((string) ($location['version'] ?? '') === 'publishedVersion'
                && ($location['is_accepted'] ?? false) === true
                && ($location['is_published'] ?? false) === true
            ) {
                return (string) ($location['source']['display_name'] ?? '');
            }
        }
        return '';
    }

    /**
     * Find first location with a non-null source (fallback).
     *
     * @param array<int, array<string, mixed>> $locations
     * @return array{source_title: string, oa_link: string}|string
     */
    private function findFirstAlternativeLocation(array $locations): array|string
    {
        foreach ($locations as $location) {
            if (!isset($location['source']) || !is_array($location['source'])) {
                continue;
            }
            if ((string) ($location['source']['type'] ?? '') !== 'journal') {
                $journal = $this->findJournalNameInLocations($locations);
                if ($journal === '') {
                    $journal = (string) ($location['source']['display_name'] ?? '');
                }
            } else {
                $journal = (string) ($location['source']['display_name'] ?? '');
            }
            $oaLink = ($location['is_oa'] ?? false) === true
                ? (string) ($location['source']['landing_page_url'] ?? '')
                : '';
            return ['source_title' => $journal, 'oa_link' => $oaLink];
        }
        return '';
    }
}
