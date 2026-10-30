<?php

declare(strict_types=1);

/**
 * Hooks of a fake repository labelled "New Version Spy": records the parameters each hook gets.
 *
 * Global and named after the repository label, as Episciences_Repositories::hasHook() resolves it.
 * Loaded with require_once by the tests that register the fake repository.
 */
final class Episciences_Repositories_NewVersionSpy_Hooks
{
    /** @var array<string, array<string, mixed>> */
    public static array $calls = [];

    /**
     * Mirrors Zenodo / BAOBAB: returns its own parameters, not the repository record.
     *
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookFilesProcessing(array $hookParams): array
    {
        self::$calls[__FUNCTION__] = $hookParams;

        return array_merge($hookParams, ['affectedRows' => 1]);
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        self::$calls[__FUNCTION__] = $hookParams;

        return [];
    }
}
