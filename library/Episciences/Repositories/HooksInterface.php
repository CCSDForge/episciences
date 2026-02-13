<?php

interface Episciences_Repositories_HooksInterface
{
    public static function hookCleanXMLRecordInput(array $input): array;
    public static function hookFilesProcessing(array $hookParams): array;
    public static function hookIsOpenAccessRight(array $hookParams) : array;
    public static function hookHasDoiInfoRepresentsAllVersions(array $hookParams): array;
    public static function hookGetConceptIdentifierFromRecord(array $hookParams): array;
    public static function hookConceptIdentifier(array $hookParams): array;
    public static function hookLinkedDataProcessing(array $hookParams): array;
}