<?php

namespace Episciences\Classification;

use Episciences\Classification;

class msc2020 extends Classification
{
    public const ZBMATH_ORG_CLASSIFICATION_BASE_QUERY = 'https://zbmath.org/classification/?q=';
    public static string $classificationName = 'msc2020';
    protected string $description = '';


    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function jsonSerialize(bool $isSerializedDocId = true): array
    {
        return array_merge(parent::jsonSerialize(), ['description' => $this->description]);
    }

}

