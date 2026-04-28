<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Client for fetching CSL+JSON metadata from doi.org.
 *
 * Sends a GET request to the given doi.org URL with the CSL+JSON Accept header
 * and returns the raw response body. Returns an empty string on HTTP failure.
 */
class DoiApiClient extends AbstractApiClient
{
    private const CSL_JSON_ACCEPT = 'application/vnd.citationstyles.csl+json';

    /**
     * Fetch CSL+JSON metadata for a fully-resolved doi.org URL.
     *
     * @param string $doiUrl Fully-qualified doi.org URL (e.g. https://doi.org/10.1234/abc)
     * @return string Raw CSL+JSON body, or empty string on HTTP error.
     */
    public function fetchCsl(string $doiUrl): string
    {
        try {
            return $this->client->get($doiUrl, [
                'headers' => [
                    'Accept'       => self::CSL_JSON_ACCEPT,
                    'Content-Type' => 'application/json',
                ],
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error(sprintf(
                'DoiApiClient: failed to fetch metadata for "%s" — code %s: %s',
                $doiUrl,
                $e->getCode(),
                $e->getMessage()
            ));
            return '';
        }
    }
}
