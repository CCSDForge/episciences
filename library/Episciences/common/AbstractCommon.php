<?php

namespace Episciences\common;

use Episciences_Tools;

abstract class AbstractCommon
{

    public function __construct(array $options )
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

}