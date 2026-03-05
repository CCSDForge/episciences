<?php

namespace Episciences\Repositories;

interface DataSanitizerInterface
{
    public static function hookCleanXMLRecordInput(array $input): array;

}