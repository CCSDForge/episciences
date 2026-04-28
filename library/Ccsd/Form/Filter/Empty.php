<?php

class Ccsd_Form_Filter_Empty implements Zend_Filter_Interface {
	
	public function filter($value)
	{
		if ('empty' == $value) {
			return "";
		}

        return $value;
	}	
	
}