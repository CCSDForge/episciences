<?php
declare(strict_types=1);

use Episciences\Api\OpenAlexApiClient;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Facade for OpenAlex API access.
 *
 * Wraps OpenAlexApiClient with lazy-init singleton and static-method API
 * for backward compatibility with existing callers.
 *
 * Inject a custom client via setClient() for unit tests.
 */
class Episciences_OpenalexTools
{
    public const ONE_MONTH = 3600 * 24 * 31;

    private static ?OpenAlexApiClient $client = null;

    /**
     * For tests: inject a preconfigured client.
     */
    public static function setClient(?OpenAlexApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): OpenAlexApiClient
    {
        if (self::$client === null) {
            $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $logger = Episciences_Paper_Citations_Logger::getMonologInstance();
            self::$client = new OpenAlexApiClient(new Client(), $cache, $logger);
        }
        return self::$client;
    }

    /**
     * Fetch OpenAlex metadata for a DOI.
     *
     * @return array<string, mixed>|null null = API error; array = data (may be empty [""] marker)
     * @throws InvalidArgumentException
     */
    public static function getMetadataOpenAlexByDoi(string $doiWhoCite): ?array
    {
        Episciences_Paper_Citations_Logger::log('Fetching OpenAlex metadata for DOI ' . $doiWhoCite);
        return self::getClient()->fetchMetadata($doiWhoCite);
    }

    /**
     * Format author list into a semicolon-separated string.
     *
     * @param array<int, array<string, mixed>> $authorList
     */
    public static function getAuthors(array $authorList): string
    {
        return self::getClient()->formatAuthors($authorList);
    }

    /**
     * Format first and last page.
     */
    public static function getPages(?string $fp, ?string $lp): string
    {
        return self::getClient()->formatPages($fp, $lp);
    }

    /**
     * Resolve best OA info from OpenAlex location data.
     *
     * @param array<string, mixed>|null $primaryLocation
     * @param array<int, array<string, mixed>> $locations
     * @param array<string, mixed>|null $bestOaLocation
     * @return array{source_title: string, oa_link: string}|string
     */
    public static function getBestOaInfo(?array $primaryLocation, array $locations, ?array $bestOaLocation): array|string
    {
        return self::getClient()->resolveBestOaInfo($primaryLocation, $locations, $bestOaLocation);
    }

    /**
     * Strip unwanted HAL display strings from a location name.
     */
    public static function removeHalStringFromLocation(string $location): string
    {
        return self::getClient()->removeHalString($location);
    }
}
