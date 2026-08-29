<?php

class Ccsd_Form_Validate_Isbibcode extends Zend_Validate_Regex {

    public function __construct($pattern = "/.*/")
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        parent::__construct($pattern);
    }
    
}