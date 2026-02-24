<?php
declare(strict_types=1);

use Episciences\Api\SoftwareHeritageApiClient;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Facade for Software Heritage + HAL API access.
 *
 * Wraps SoftwareHeritageApiClient with lazy-init singleton.
 * Constants preserved for backward compatibility.
 */
class Episciences_SoftwareHeritageTools
{
    public const SH_DOMAIN_API    = 'https://archive.softwareheritage.org/api/1';
    public const API_HAL_URL      = 'https://hal.science/';
    public const PREFIX_SWHID_DIR = 'swh:1:dir:';

    private static ?SoftwareHeritageApiClient $client = null;

    /**
     * For tests: inject a preconfigured client.
     */
    public static function setClient(?SoftwareHeritageApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): SoftwareHeritageApiClient
    {
        if (self::$client === null) {
            $cache = new FilesystemAdapter('softwareHeritage', SoftwareHeritageApiClient::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
            $logger = new Logger('softwareHeritage');
            $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'softwareHeritage_' . date('Y-m-d') . '.log', Logger::INFO));
            self::$client = new SoftwareHeritageApiClient(new Client(), $cache, $logger);
        }
        return self::$client;
    }

    /**
     * Fetch codemeta JSON from a HAL URL.
     *
     * @param string $url HAL identifier/path
     * @return string JSON body, or empty string on failure
     */
    public static function getCodeMetaFromHal(string $url): string
    {
        $result = self::getClient()->fetchCodeMetaFromHal($url);
        if ($result === null) {
            return '';
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Fetch citation metadata from HAL Solr.
     *
     * @param string $halId HAL identifier
     * @param int $version version number (0 = any)
     * @return string JSON body, or empty string on failure
     */
    public static function getCitationsFullFromHal(string $halId, int $version = 0): string
    {
        $result = self::getClient()->fetchCitationFromHalSolr($halId, $version);
        if ($result === null) {
            return '';
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Fetch codemeta.json from a SWH directory SWHID.
     *
     * @param string $swhidDir full SWHID string, e.g. "swh:1:dir:abc123..."
     * @return string codemeta content, or empty string on failure
     */
    public static function getCodeMetaFromDirSwh(string $swhidDir): string
    {
        return self::getClient()->fetchCodeMetaFromDirectory($swhidDir) ?? '';
    }

    /**
     * Replace a SWHID placeholder in a citation string with a badge img tag.
     *
     * @param string $swhid SWHID identifier
     * @param string $citation citation HTML string containing &#x27E8;swhid&#x27E9;
     * @return string citation with placeholder replaced by badge img
     */
    public static function replaceByBadgeHalCitation(string $swhid, string $citation): string
    {
        return self::getClient()->generateBadgeHtml($swhid, $citation);
    }
}
