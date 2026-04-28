<?php
declare(strict_types=1);

use Episciences\Api\BiblioRefApiClient;
use Episciences\AppRegistry;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * Static facade for BiblioRefApiClient.
 *
 * Preserves the legacy static-method API used by callers in Paper.php and Paper/Export.php.
 * Use setClient() to inject a test double in unit tests.
 */
class Episciences_BibliographicalsReferencesTools
{
    private static ?BiblioRefApiClient $client = null;

    /**
     * Inject a pre-configured client (for tests or custom setups).
     */
    public static function setClient(?BiblioRefApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): BiblioRefApiClient
    {
        if (self::$client === null) {
            $logger = AppRegistry::getMonoLogger() ?? new NullLogger();
            $baseUrl = (string) EPISCIENCES_BIBLIOREF['URL'];
            $sslVerify = (bool) EPISCIENCES_BIBLIOREF['SSL_VERIFY'];
            self::$client = new BiblioRefApiClient(new Client(), new NullAdapter(), $logger, $baseUrl, $sslVerify);
        }

        return self::$client;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getBibRefFromApi(string $pdf): array
    {
        return self::getClient()->fetchBibRef($pdf);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function referencesToArray(string $rawCitations): array
    {
        return self::getClient()->parseResponse($rawCitations);
    }
}
