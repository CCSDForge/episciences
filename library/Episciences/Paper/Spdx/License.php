<?php

namespace Episciences\Paper\Spdx;

use Episciences_Tools;

class License
{
    protected string $code;
    protected string $name;
    protected bool $recommended;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
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
     * @return License
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return License
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function recommended(): bool
    {
        return $this->recommended;
    }

    public function setRecommended(bool $recommended): self
    {
        $this->recommended = $recommended;
        return $this;
    }


}