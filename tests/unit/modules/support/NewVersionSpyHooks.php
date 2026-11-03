<?php

declare(strict_types=1);

use Episciences\Repositories\FilesEnrichmentInterface;
use Episciences\Repositories\LinkedDataEnrichmentInterface;

/**
 * Hooks of a fake repository labelled "New Version Spy": records the parameters each hook gets.
 * Mirrors Zenodo / BAOBAB: files and linked data hooks, and the loading of the repository record (hookApiRecords()).
 *
 * Global and named after the repository label, as Episciences_Repositories::hasHook() resolves it.
 * Loaded with require_once by the tests that register the fake repository.
 */
final class Episciences_Repositories_NewVersionSpy_Hooks implements FilesEnrichmentInterface, LinkedDataEnrichmentInterface
{
    /** @var array<string, array<string, mixed>> parameters of the last call of each hook */
    public static array $calls = [];

    /** @var array<string, int> number of calls of each hook */
    public static array $callCounts = [];

    /** @var array<string, Throwable> thrown by the named hook, as a repository fetch failure or an unexpected payload would be */
    public static array $exceptions = [];

    /** @var array<string, mixed> repository record returned by hookApiRecords() */
    public static array $record = ['metadata' => ['related_identifiers' => []]];

    /** @var array<string, mixed> repository record the files hook loaded, returned as 'response' when not empty */
    public static array $loadedRecord = [];

    /**
     * Resets the recorded calls and the configured failures
     */
    public static function reset(): void
    {
        self::$calls = [];
        self::$callCounts = [];
        self::$exceptions = [];
        self::$record = ['metadata' => ['related_identifiers' => []]];
        self::$loadedRecord = [];
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookApiRecords(array $hookParams): array
    {
        self::record(__FUNCTION__, $hookParams);

        return self::$record;
    }

    /**
     * Mirrors Zenodo / BAOBAB: returns its own parameters, with the repository record it loaded as 'response'.
     *
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookFilesProcessing(array $hookParams): array
    {
        self::record(__FUNCTION__, $hookParams);

        $result = array_merge($hookParams, ['affectedRows' => 1]);
        if (self::$loadedRecord !== []) {
            $result['response'] = self::$loadedRecord;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        self::record(__FUNCTION__, $hookParams);

        return ['affectedRows' => 1];
    }

    /**
     * @param array<string, mixed> $hookParams
     */
    private static function record(string $hookName, array $hookParams): void
    {
        self::$calls[$hookName] = $hookParams;
        self::$callCounts[$hookName] = (self::$callCounts[$hookName] ?? 0) + 1;

        if (isset(self::$exceptions[$hookName])) {
            throw self::$exceptions[$hookName];
        }
    }
}
