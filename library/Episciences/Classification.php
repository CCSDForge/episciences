<?php

namespace Episciences;

use Episciences_Tools;
use http\Message\Body;

abstract class Classification implements \JsonSerializable
{
    public static string $classificationName;
    protected int $docid = 0;
    protected string $code = '';
    protected string $label = '';
    protected string $source_name = '';


    public function __construct(array $data)
    {
        $this->setOptions($data);
    }

    public function setOptions(array $options): void
    {

        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true, '.' . static::$classificationName);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }


// Implement the JsonSerializable interface
    public function jsonSerialize(bool $isSerializedDocId = true): array
    {
        $toArray = $isSerializedDocId ? ['docid' => $this->docid] : [];

        return array_merge($toArray, [
            'code' => $this->code,
            'label' => $this->label,
            'classificationName' => static::$classificationName,
            'sourceName' => $this->source_name,
        ]);
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return int
     */
    public function getDocid(): int
    {
        return $this->docid;
    }

    /**
     * @param int $docid
     */
    public function setDocid(int $docid): void
    {
        $this->docid = $docid;
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->source_name;
    }

    /**
     * @param string $source_name
     */
    public function setSourceName(string $source_name): void
    {
        $this->source_name = $source_name;
    }

}