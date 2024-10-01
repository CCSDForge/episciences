<?php

namespace Episciences\Classification;

use Episciences\Classification;

class msc2020 extends Classification
{
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

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), ['description' => $this->description]);
    }


}

