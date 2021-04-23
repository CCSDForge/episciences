<?php

interface Episciences_Repositories_HooksInterface
{
    public static function hookCleanXMLRecordInput(array $input): array;
    public static function hookFilesProcessing(array $hookParams): array;
    public static function hookCleanIdentifiers(array $hookParams): array;
}