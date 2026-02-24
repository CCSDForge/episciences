<?php
declare(strict_types=1);

use Episciences\Api\OpenCitationsApiClient;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Facade for OpenCitations API access.
 *
 * Wraps OpenCitationsApiClient with lazy-init singleton and static-method API
 * for backward compatibility with existing callers.
 */
class Episciences_OpencitationsTools
{
    public const ONE_MONTH = 3600 * 24 * 31;

    private static ?OpenCitationsApiClient $client = null;

    /**
     * For tests: inject a preconfigured client.
     */
    public static function setClient(?OpenCitationsApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): OpenCitationsApiClient
    {
        if (self::$client === null) {
            $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $logger = Episciences_Paper_Citations_Logger::getMonologInstance();
            self::$client = new OpenCitationsApiClient(new Client(), $cache, $logger);
        }
        return self::$client;
    }

    /**
     * Fetch citing DOIs for a given DOI from OpenCitations.
     *
     * Returns:
     *  - null  : API error (not cached)
     *  - []    : 0 citations (bug #5 fixed: valid, cached)
     *  - array : citation rows
     *
     * @return array<int, array<string, string>>|null
     * @throws InvalidArgumentException
     */
    public static function getOpenCitationCitedByDoi(string $doi): ?array
    {
        return self::getClient()->fetchCitingDois($doi);
    }

    /**
     * Extract clean DOI strings from OpenCitations citation rows.
     *
     * @param array<int, array<string, string>> $apiCallCitationCache raw citation rows
     * @return array<string> clean DOI list
     */
    public static function cleanDoisCitingFound(array $apiCallCitationCache): array
    {
        return self::getClient()->extractCitingDois($apiCallCitationCache);
    }
}
