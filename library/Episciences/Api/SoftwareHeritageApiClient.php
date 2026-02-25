<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException;

/**
 * Software Heritage + HAL API client.
 *
 * Cache namespace : softwareHeritage
 * Cache key      : sha1($halUrl) . '_swh.json'   TTL = ONE_MONTH
 *
 */
class SoftwareHeritageApiClient extends AbstractApiClient
{
    public const SH_DOMAIN_API      = 'https://archive.softwareheritage.org/api/1';
    public const API_HAL_URL        = 'https://hal.science/';
    public const PREFIX_SWHID_DIR   = 'swh:1:dir:';

    private const BADGE_BASE_URL = 'https://archive.softwareheritage.org/badge/';

    // -------------------------------------------------------------------------
    // HAL codemeta
    // -------------------------------------------------------------------------

    /**
     * Fetch codemeta JSON from a HAL URL.
     *
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function fetchCodeMetaFromHal(string $halUrl): ?array
    {
        $key = sha1($halUrl) . '_swh.json';

        $cached = $this->getCached($key);
        if ($cached !== null) {
            return json_decode($cached, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        }

        try {
            $body = $this->client->request('GET', self::API_HAL_URL . $halUrl . '/codemeta')
                ->getBody()->getContents();
            $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            $this->logger->warning('SWH/HAL codemeta fetch error: ' . $e->getMessage());
            return null;
        } catch (\JsonException $e) {
            $this->logger->warning('SWH/HAL codemeta JSON decode error: ' . $e->getMessage());
            return null;
        }

        $this->saveToCache($key, $body);
        return $decoded;
    }

    // -------------------------------------------------------------------------
    // HAL Solr citations
    // -------------------------------------------------------------------------

    /**
     * Fetch citation metadata from HAL Solr for a given HAL ID.
     *
     * @return array<string, mixed>|null
     */
    public function fetchCitationFromHalSolr(string $halId, int $version = 0): ?array
    {
        $escapedHalId = addslashes($halId);
        $strQuery = '((halId_s:' . $escapedHalId . ' OR halIdSameAs_s:' . $escapedHalId . ')';

        if ($version !== 0) {
            $strQuery .= ' AND version_i:' . $version;
        }
        $strQuery .= ')&fl=docType_s,citationFull_s,swhidId_s';

        $baseUrl = \Episciences_Repositories::getApiUrl(\Episciences_Repositories::HAL_REPO_ID);
        $url = $baseUrl . '/search?q=' . $strQuery;

        try {
            $body = $this->client->request('GET', $url)->getBody()->getContents();
            return json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            $this->logger->warning('HAL Solr fetch error: ' . $e->getMessage());
            return null;
        } catch (\JsonException $e) {
            $this->logger->warning('HAL Solr JSON decode error: ' . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // SWH directory codemeta
    // -------------------------------------------------------------------------

    /**
     * Fetch codemeta.json from a SWH directory SWHID.
     *
     * @param string $swhidDir full SWHID string, e.g. "swh:1:dir:abc123..."
     * @return string|null codemeta JSON body, or null on failure
     */
    public function fetchCodeMetaFromDirectory(string $swhidDir): ?string
    {
        $cleanSwhid = str_replace(self::PREFIX_SWHID_DIR, '', $swhidDir);

        try {
            $body = $this->client->request('GET', self::SH_DOMAIN_API . '/directory/' . $cleanSwhid . '/codemeta.json')
                ->getBody()->getContents();

            $res = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

            if (array_key_exists('target_url', $res)) {
                $targetUrl = $res['target_url'];

                if (!is_string($targetUrl) || !str_starts_with($targetUrl, 'https://')) {
                    $this->logger->warning('SWH target_url is not HTTPS, refusing to follow: ' . (string) $targetUrl);
                    return null;
                }

                $raw = $this->client->request('GET', $targetUrl . 'raw')->getBody()->getContents();
                return $raw !== '' ? $raw : null;
            }
        } catch (GuzzleException $e) {
            $this->logger->warning('SWH directory fetch error: ' . $e->getMessage());
            return null;
        } catch (\JsonException $e) {
            $this->logger->warning('SWH directory JSON decode error: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Badge generation
    // -------------------------------------------------------------------------

    /**
     * Generate the Software Heritage badge HTML for a SWHID.
     *
     * @param string $swhid   the SWHID identifier
     * @param string $citation the citation text to embed in a regex replacement later
     * @return string HTML <img> tag
     */
    public function generateBadgeHtml(string $swhid, string $citation): string
    {
        $safeSwhid = htmlspecialchars($swhid, ENT_QUOTES, 'UTF-8');

        return preg_replace(
            '~&#x27E8;' . preg_quote($safeSwhid, '~') . '&#x27E9;~',
            '<img src="' . self::BADGE_BASE_URL . $safeSwhid . '" alt="Archived | ' . $safeSwhid . '"/>',
            $citation
        ) ?? $citation;
    }
}
