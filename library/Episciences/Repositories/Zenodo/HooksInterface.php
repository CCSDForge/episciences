<?php
namespace Episciences\Repositories\Zenodo;

interface HooksInterface
{
    public static function hookIsOpenAccessRight(array $hookParams) : array;
    public static function hookHasDoiInfoRepresentsAllVersions(array $hookParams): array;
    public static function hookGetConceptIdentifierFromRecord(array $hookParams): array;
    public static function hookConceptIdentifier(array $hookParams): array;
}