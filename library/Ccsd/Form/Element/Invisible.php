<?php

class Ccsd_Form_Element_Invisible extends Zend_Form_Element
{
	public function isValid($value, $context = null)
	{
		$this->_value = "";
		return true;
	}
	
    public function render (Zend_View_Interface $view = null) {
        return "";
    }
}