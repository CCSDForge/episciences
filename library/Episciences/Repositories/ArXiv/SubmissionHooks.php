<?php

/**
 * OAI-PMH request options and DataCite fallback of an arXiv submission, called by Episciences_Submit.
 *
 * Not named Episciences_Repositories_ArXiv_Hooks on purpose: Episciences_Repositories::hasHook() would
 * then find a hook class for arXiv and change its other submission paths.
 */
class Episciences_Repositories_ArXiv_SubmissionHooks
{
    /** The arXiv OAI-PMH server is asked twice, with short timeouts, before the DataCite fallback */
    public const OAI_MAX_ATTEMPTS = 2;
    public const OAI_REQUEST_TIMEOUT = 15;
    public const OAI_CONNECT_TIMEOUT = 5;

    /**
     * Options of the OAI-PMH requests of a submission
     *
     * @return array{maxAttempts: int, requestTimeout: int, connectTimeout: int}
     */
    public static function hookOaiRequestOptions(): array
    {
        return [
            'maxAttempts' => self::OAI_MAX_ATTEMPTS,
            'requestTimeout' => self::OAI_REQUEST_TIMEOUT,
            'connectTimeout' => self::OAI_CONNECT_TIMEOUT,
        ];
    }

    /**
     * Record read from DataCite when the arXiv OAI-PMH server is unavailable
     *
     * @param array<string, mixed> $hookParams ['identifier' => arXiv identifier without version]
     * @return array{record: string, versions: int[], latestVersion: int|null, source: string}|array{}
     *  empty when the fallback is switched off
     * @throws Ccsd_Error ID_DOES_NOT_EXIST_CODE when DataCite does not know the document; another Ccsd_Error
     *  when DataCite cannot be read
     */
    public static function hookFallbackRecord(array $hookParams): array
    {
        if (!Episciences_Repositories_ArXiv_DataciteFallback::isEnabled() || empty($hookParams['identifier'])) {
            return [];
        }

        return Episciences_Repositories_ArXiv_DataciteFallback::fetch((string)$hookParams['identifier'])
            + ['source' => Episciences_Repositories_ArXiv_DataciteFallback::SOURCE];
    }
}
