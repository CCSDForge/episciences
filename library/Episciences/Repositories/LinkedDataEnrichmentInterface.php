<?php

namespace Episciences\Repositories;
interface LinkedDataEnrichmentInterface {
    public static function hookLinkedDataProcessing(array $hookParams): array;
}