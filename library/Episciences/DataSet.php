<?php

namespace Episciences;

class DataSet extends \Episciences_Paper
{

    public function __construct(array $options = null){

        parent::__construct($options);
        $this->_type = [self::TITLE_TYPE => self::DATASET_TYPE_TITLE];
    }
}