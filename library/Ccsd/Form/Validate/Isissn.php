<?php
class Ccsd_Form_Validate_Isissn extends Zend_Validate_Barcode {


	public function __construct()
	{
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

		parent::__construct(array(
			'adapter'  => 'ISSN',
			'checksum' => false
		));
	}
	
	public function isValid ($value)
	{
		return parent::isValid(preg_replace("#([^-\s]+)([-\s]?)([^-\s]+)#", "$1$3", $value));
	}
}