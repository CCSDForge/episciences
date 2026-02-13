<?php

interface Episciences_Repositories_CommonHooksInterface
{

    public static function hookApiRecords(array $hookParams): array; // for the retrieval [and processing of metadata if necessary]
    public static function hookCleanIdentifiers(array $hookParams): array; // for possible cleaning of the identifier if necessary
    public static function hookVersion(array $hookParams): array; // version processing if necessary; return ['version' => $version] if the identifier has been modified to take account of changes
    public static function hookIsRequiredVersion(): array; // to check if the version is required; return ['result' => true] if the version is required
}