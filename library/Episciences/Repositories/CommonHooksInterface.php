<?php
namespace Episciences\Repositories;

interface CommonHooksInterface
{
    public static function hookApiRecords(array $hookParams): array; // for the retrieval [and processing of metadata if necessary]
    public static function hookIsRequiredVersion(): array; // to check if the version is required; return ['result' => true] if the version is required
    public static function hookIsIdentifierCommonToAllVersions(): array; // is it necessary to enter the identifier in the form when submitting the new version;
}