<?php

namespace Episciences\Repositories;
interface FilesEnrichmentInterface {
    public static function hookFilesProcessing(array $hookParams): array;
}