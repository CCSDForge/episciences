<?php
declare(strict_types=1);

use Episciences\Api\DoiApiClient;
use Episciences\AppRegistry;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * Static facade for DOI metadata access and DOI string utilities.
 *
 * HTTP calls are delegated to DoiApiClient (injectable via setClient() for tests).
 * Pure utility methods (checkIfDomainExist, cleanDoi, normalizeArxivDoi) have no
 * side effects and can be called without any client.
 */
class Episciences_DoiTools
{
    /**
     * @const string Canonical doi.org URL prefix
     */
    public const DOI_ORG_PREFIX = 'https://doi.org/';

    private static ?DoiApiClient $client = null;

    /**
     * Inject a pre-configured client (for tests or custom setups).
     */
    public static function setClient(?DoiApiClient $client): void
    {
        self::$client = $client;
    }

    private static function getClient(): DoiApiClient
    {
        if (self::$client === null) {
            $logger = AppRegistry::getMonoLogger() ?? new NullLogger();
            self::$client = new DoiApiClient(new Client(), new NullAdapter(), $logger);
        }

        return self::$client;
    }

    /**
     * Fetch CSL+JSON metadata string for a DOI or doi.org URL.
     *
     * ArXiv identifiers are normalised to a doi.org-resolvable URL before the request.
     * Returns an empty string on HTTP failure.
     */
    public static function getMetadataFromDoi(string $doi): string
    {
        if (Episciences_Tools::isArxiv($doi)) {
            $doi = self::normalizeArxivDoi($doi);
        }

        if (!self::checkIfDomainExist($doi)) {
            $doi = self::DOI_ORG_PREFIX . $doi;
        }

        return self::getClient()->fetchCsl($doi);
    }

    /**
     * Normalize an arXiv identifier to a doi.org-resolvable URL.
     *
     * Strips the version suffix (e.g. "v2", "v10") and prepends the arXiv DOI prefix.
     * doi.org does not accept version suffixes in arXiv DOI lookups.
     *
     * Examples:
     *   "2301.12345v2"       → "{prefix}/arxiv.2301.12345"
     *   "arxiv:2301.12345v2" → "{prefix}arxiv:2301.12345"
     */
    public static function normalizeArxivDoi(string $doi): string
    {
        // Remove version suffix (e.g. "v2", "v10") — character class was wrong in old code
        $doi = preg_replace('~v\d+$~i', '', $doi) ?? $doi;

        $prefix = Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ARXIV_REPO_ID);

        if (!preg_match('~(?i)arxiv~', $doi)) {
            return $prefix . '/arxiv.' . $doi;
        }

        return $prefix . $doi;
    }

    /**
     * Return true if $doi already carries a doi.org or dx.doi.org URL prefix.
     */
    public static function checkIfDomainExist(string $doi): bool
    {
        return (bool) preg_match('~^https://(dx\.)?doi\.org/~', $doi);
    }

    /**
     * Strip the doi.org or dx.doi.org URL prefix from a DOI string.
     *
     * Returns an empty string if $doi is empty. Returns $doi unchanged if it
     * does not start with a known doi.org prefix.
     */
    public static function cleanDoi(string $doi = ''): string
    {
        return ($doi !== '') ? str_replace(['https://doi.org/', 'https://dx.doi.org/'], '', $doi) : '';
    }
}
