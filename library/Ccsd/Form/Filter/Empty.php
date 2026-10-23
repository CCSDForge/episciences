<?php

class Ccsd_Form_Filter_Empty implements Zend_Filter_Interface {
	
    public function __construct()
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

    }

	public function filter($value)
	{
		if ('empty' == $value) {
			return "";
		}

        return $value;
	}	
	
}