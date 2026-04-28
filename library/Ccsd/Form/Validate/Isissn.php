<?php
class Ccsd_Form_Validate_Isissn extends Zend_Validate_Barcode {


	public function __construct()
	{
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