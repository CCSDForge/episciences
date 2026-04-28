<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Client for the Episciences bibliographic reference extraction API.
 *
 * Fetches and parses structured citations by submitting a PDF URL
 * to the configured biblioref service endpoint.
 *
 * Security note: the PDF URL is validated (HTTP/HTTPS only) and URL-encoded
 * before being passed as a query parameter, preventing SSRF via non-HTTP schemes.
 */
class BiblioRefApiClient extends AbstractApiClient
{
    private const ENDPOINT = '/visualize-citations';

    private string $baseUrl;
    private bool $sslVerify;

    public function __construct(
        Client $client,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $baseUrl = '',
        bool $sslVerify = true
    ) {
        parent::__construct($client, $cache, $logger);
        $this->baseUrl = $baseUrl;
        $this->sslVerify = $sslVerify;
    }

    /**
     * Fetch bibliographic references for a given PDF URL.
     *
     * The PDF URL is validated (HTTP/HTTPS only) before use.
     * Returns an empty array on invalid URL, missing configuration,
     * HTTP failure, or unexpected API response format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBibRef(string $pdfUrl): array
    {
        if (!$this->isValidHttpUrl($pdfUrl)) {
            $this->logger->warning(sprintf('BiblioRef: rejected non-HTTP URL "%s"', $pdfUrl));
            return [];
        }

        if ($this->baseUrl === '') {
            $this->logger->warning('BiblioRef: base URL is not configured');
            return [];
        }

        $apiUrl = $this->baseUrl . self::ENDPOINT . '?url=' . rawurlencode($pdfUrl);

        try {
            $body = $this->client->get($apiUrl, ['verify' => $this->sslVerify])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf(
                'BiblioRef API error for PDF "%s" — code %s: %s',
                $pdfUrl,
                $e->getCode(),
                $e->getMessage()
            ));
            return [];
        }

        if ($body === '') {
            return [];
        }

        return $this->parseResponse($body);
    }

    /**
     * Parse a raw JSON string from the biblioref API into a structured citation array.
     *
     * The API returns either:
     *   - A list of citation objects (success path)
     *   - An object with a top-level "message" key (API-level error indicator)
     *
     * Each output entry may contain:
     *   - 'unstructured_citation': raw text of the reference (from 'raw_reference')
     *   - 'doi': DOI string
     *   - 'csl': CSL-JSON representation
     *
     * Citations with a missing or unparseable 'ref' field are silently skipped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseResponse(string $rawJson): array
    {
        try {
            $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning(sprintf('BiblioRef: failed to decode API response: %s', $e->getMessage()));
            return [];
        }

        if (!is_array($decoded) || array_key_exists('message', $decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $citation) {
            if (!is_array($citation) || !isset($citation['ref']) || !is_string($citation['ref'])) {
                continue;
            }

            try {
                $reference = json_decode($citation['ref'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(sprintf('BiblioRef: skipping citation with invalid ref JSON: %s', $e->getMessage()));
                continue;
            }

            if (!is_array($reference)) {
                continue;
            }

            $entry = [];

            if (isset($reference['raw_reference']) && is_string($reference['raw_reference'])) {
                $entry['unstructured_citation'] = $reference['raw_reference'];
            }

            if (isset($reference['doi']) && is_string($reference['doi'])) {
                $entry['doi'] = $reference['doi'];
            }

            if (isset($citation['csl'])) {
                $entry['csl'] = $citation['csl'];
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Return true only if the URL has a valid HTTP or HTTPS scheme.
     *
     * Prevents SSRF via file://, ftp://, gopher://, etc.
     */
    private function isValidHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        return is_string($scheme) && ($scheme === 'http' || $scheme === 'https');
    }
}
