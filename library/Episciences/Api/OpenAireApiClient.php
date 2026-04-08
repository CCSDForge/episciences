<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * OpenAire Research Graph REST API client.
 *
 * Cache namespaces:
 *  - openAireResearchGraph : md5($doi) . '.json'
 *  - enrichmentAuthors     : md5($doi) . '_creator.json'
 *  - enrichmentFunding     : md5($doi) . '_funding.json'
 *
 * Three separate PSR-6 pools are injected (one per namespace).
 *
 */
class OpenAireApiClient extends AbstractApiClient
{
    private const API_BASE_URL  = 'https://api.openaire.eu/search/publications';
    private const MAX_RESPONSE_SIZE = 5242880; // 5 MB

    private CacheItemPoolInterface $globalCache;
    private CacheItemPoolInterface $authorsCache;
    private CacheItemPoolInterface $fundingCache;

    public function __construct(
        Client               $client,
        CacheItemPoolInterface $globalCache,
        CacheItemPoolInterface $authorsCache,
        CacheItemPoolInterface $fundingCache,
        LoggerInterface      $logger
    ) {
        // Parent constructor requires a single CacheItemPoolInterface; pass globalCache as primary.
        parent::__construct($client, $globalCache, $logger);
        $this->globalCache  = $globalCache;
        $this->authorsCache = $authorsCache;
        $this->fundingCache = $fundingCache;
    }

    // -------------------------------------------------------------------------
    // Publication fetch
    // -------------------------------------------------------------------------

    /**
     * Fetch OpenAire publication data for a DOI (cached in globalCache pool).
     *
     * Returns null on API error; returns [] on empty/malformed response.
     *
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function fetchPublication(string $doi, int $paperId): ?array
    {
        $key  = md5($doi) . '.json';
        $item = $this->globalCache->getItem($key);

        if ($item->isHit()) {
            $data = json_decode((string) $item->get(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
            return $data;
        }

        $url = self::API_BASE_URL . '/?doi=' . urlencode($doi) . '&format=json';
        $this->logger->info("Fetching OpenAIRE data for DOI {$doi}");

        try {
            $response = $this->client->get($url, [
                'headers'         => $this->defaultHeaders(),
                'timeout'         => 30,
                'allow_redirects' => [
                    'max'       => 2,
                    'strict'    => true,
                    'referer'   => true,
                    'protocols' => ['https'],
                ],
                'verify'          => true,
            ]);
            $body = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf(
                'OpenAIRE API error for paper %d: %s',
                $paperId,
                $e->getMessage()
            ));
            return null;
        }

        if (strlen($body) > self::MAX_RESPONSE_SIZE) {
            $this->logger->error(sprintf(
                'OpenAIRE response too large for DOI %s (%d bytes)',
                $doi,
                strlen($body)
            ));
            return null;
        }

        // Rate limiting
        sleep(1);

        try {
            $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error(sprintf(
                'JSON decode error for paper %d (OpenAIRE): %s',
                $paperId, $e->getMessage()
            ));
            $item->set(json_encode(['']));
            $item->expiresAfter(self::ONE_MONTH);
            $this->globalCache->save($item);
            return [];
        }

        $item->set(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $item->expiresAfter(self::ONE_MONTH);
        $this->globalCache->save($item);

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Creator extraction
    // -------------------------------------------------------------------------

    /**
     * Extract creator array from OpenAire response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractCreators(array $response): ?array
    {
        if (empty($response['response']['results']['result'][0])) {
            return null;
        }
        $result = $response['response']['results']['result'][0];
        return $result['metadata']['oaf:entity']['oaf:result']['creator'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Funding extraction
    // -------------------------------------------------------------------------

    /**
     * Extract funding array from OpenAire response, or null if unavailable.
     *
     * @param array<string, mixed> $response
     * @return array<mixed>|null
     */
    public function extractFunding(array $response): ?array
    {
        if (empty($response['response']['results']['result'][0])) {
            return null;
        }
        $rels = $response['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['rels'] ?? null;
        if ($rels === null || !array_key_exists('rel', $rels)) {
            return null;
        }
        return $rels['rel'];
    }

    // -------------------------------------------------------------------------
    // JEL classification extraction
    // -------------------------------------------------------------------------

    /**
     * Extract JEL classification codes from an OpenAire publication response.
     *
     * @param array<string, mixed> $response
     * @return array<string> unique JEL codes (e.g. "A10", "B23")
     */
    public function extractJelCodes(array $response): array
    {
        $codes = [];
        if (!isset($response['response']['results']['result'])) {
            return $codes;
        }
        foreach ($response['response']['results']['result'] as $result) {
            $subjects = $result['metadata']['oaf:entity']['oaf:result']['subject'] ?? null;
            if ($subjects === null) {
                continue;
            }
            // subject may be a single object or an array of objects
            if (!is_array($subjects) || isset($subjects['@classid'])) {
                $subjects = [$subjects];
            }
            foreach ($subjects as $subject) {
                if (($subject['@classid'] ?? '') !== 'jel' || !isset($subject['$'])) {
                    continue;
                }
                $value = $subject['$'];
                if (str_starts_with($value, 'jel:')) {
                    $code = substr($value, 4); // fix: ltrim('jel:') strips chars, not the prefix string
                    if ($code !== '') {
                        $codes[] = $code;
                    }
                }
            }
        }
        return array_unique($codes);
    }

    // -------------------------------------------------------------------------
    // Cache helpers (author / funding pools)
    // -------------------------------------------------------------------------

    /**
     * Return [cache, cacheKey, cacheItem] for the creators pool.
     *
     * @return array{0: CacheItemPoolInterface, 1: string, 2: \Psr\Cache\CacheItemInterface}
     * @throws InvalidArgumentException
     */
    public function getCreatorCacheItem(string $doi): array
    {
        $key  = md5($doi) . '_creator.json';
        $item = $this->authorsCache->getItem($key);
        return [$this->authorsCache, $key, $item];
    }

    /**
     * Return [cache, cacheKey, cacheItem] for the funding pool.
     *
     * @return array{0: CacheItemPoolInterface, 1: string, 2: \Psr\Cache\CacheItemInterface}
     * @throws InvalidArgumentException
     */
    public function getFundingCacheItem(string $doi): array
    {
        $key  = md5($doi) . '_funding.json';
        $item = $this->fundingCache->getItem($key);
        $item->expiresAfter(self::ONE_MONTH);
        return [$this->fundingCache, $key, $item];
    }

    /**
     * Return the global cache item for a DOI.
     *
     * @throws InvalidArgumentException
     */
    public function getGlobalCacheItem(string $doi): \Psr\Cache\CacheItemInterface
    {
        return $this->globalCache->getItem(md5($doi) . '.json');
    }

    // -------------------------------------------------------------------------
    // ORCID author matching
    // -------------------------------------------------------------------------

    /**
     * Search API data for a matching ORCID for the given author full name.
     *
     * @param array<int, array<string, mixed>> $apiData
     */
    public function findOrcidForAuthor(string $fullName, array $apiData): ?string
    {
        foreach ($apiData as $authorInfoFromApi) {
            $isMatch = false;

            if (array_search($fullName, $authorInfoFromApi, true) !== false
                || array_search(\Episciences_Tools::replaceAccents($fullName), $authorInfoFromApi, true) !== false
            ) {
                $isMatch = true;
            } elseif (isset($authorInfoFromApi['$'])
                && \Episciences_Tools::replaceAccents($fullName) === \Episciences_Tools::replaceAccents($authorInfoFromApi['$'])
            ) {
                $isMatch = true;
            }

            if ($isMatch && array_key_exists('@orcid', $authorInfoFromApi)) {
                return \Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
            }
        }
        return null;
    }
}
