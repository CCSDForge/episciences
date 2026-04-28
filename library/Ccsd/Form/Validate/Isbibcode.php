<?php

class Ccsd_Form_Validate_Isbibcode extends Zend_Validate_Regex {

    public function __construct($pattern = "/.*/")
    {
        parent::__construct($pattern);
    }
    
}