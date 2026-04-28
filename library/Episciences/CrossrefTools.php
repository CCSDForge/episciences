<?php
declare(strict_types=1);

use Episciences\Api\CrossrefApiClient;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Facade for Crossref API access.
 *
 * Wraps CrossrefApiClient with lazy-init singleton and static-method API
 * for backward compatibility with existing callers.
 *
 * Inject a custom client via setClient() for unit tests.
 */
class Episciences_CrossrefTools
{
    public const ONE_MONTH = 3600 * 24 * 31;
    public const CROSSREF_PLUS_API_TOKEN_HEADER_NAME = 'Crossref-Plus-API-Token';

    private static ?CrossrefApiClient $client = null;

    /**
     * For tests: inject a preconfigured client (e.g. with MockHandler).
     */
    public static function setClient(?CrossrefApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): CrossrefApiClient
    {
        if (self::$client === null) {
            $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $logger = Episciences_Paper_Citations_Logger::getMonologInstance();
            self::$client = new CrossrefApiClient(new Client(), $cache, $logger);
        }
        return self::$client;
    }

    /**
     * Return the container-title from Crossref when OpenAlex has no location.
     *
     * @param array<string, mixed>|string $getBestOpenAccessInfo result from OpenalexTools::getBestOaInfo()
     * @param string $doiWhoCite DOI of the citing paper
     * @return string location string, or empty string
     * @throws InvalidArgumentException
     */
    public static function getLocationFromCrossref($getBestOpenAccessInfo, string $doiWhoCite): string
    {
        if ($getBestOpenAccessInfo !== '') {
            return '';
        }

        Episciences_Paper_Citations_Logger::log('NO LOCATIONS IN OPENALEX ' . $doiWhoCite);

        $metadata = self::getClient()->fetchMetadata($doiWhoCite);
        if ($metadata === null || reset($metadata) === '') {
            return '';
        }

        return self::getClient()->extractLocation($metadata);
    }

    /**
     * Add event_place to the global metadata array for a proceedings-article.
     *
     * @param string $typeCrossref Crossref document type
     * @param string $doiWhoCite DOI of the citing paper
     * @param array<int, array<string, mixed>> $globalInfoMetadata accumulated metadata array
     * @param int $i current index
     * @return array<int, array<string, mixed>> updated metadata array
     * @throws InvalidArgumentException
     */
    public static function addLocationEvent(string $typeCrossref, string $doiWhoCite, array $globalInfoMetadata, int $i): array
    {
        if ($typeCrossref !== 'proceedings-article') {
            $globalInfoMetadata[$i]['event_place'] = '';
            return $globalInfoMetadata;
        }

        $metadata = self::getClient()->fetchMetadata($doiWhoCite);
        if ($metadata === null || reset($metadata) === '') {
            $globalInfoMetadata[$i]['event_place'] = '';
            return $globalInfoMetadata;
        }

        $globalInfoMetadata[$i]['event_place'] = self::getClient()->extractEventPlace($metadata);
        return $globalInfoMetadata;
    }
}
