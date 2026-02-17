<?php

namespace Episciences\Repositories;

interface InputSanitizerInterface
{
    public static function hookCleanIdentifiers(array $hookParams): array; // for possible cleaning of the identifier if necessary
    public static function hookVersion(array $hookParams): array; // version processing if necessary; return ['version' => $version] if the identifier has been modified to take account of changes

}