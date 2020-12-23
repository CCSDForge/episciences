<?php

class Ccsd_Form_Element_Hidden extends Zend_Form_Element_Hidden
{
    
	public function init()
	{
		parent::init();
	}

	/**
	 * Load default decorators
	 *
	 * @return Zend_Form_Element
	 */
	public function loadDefaultDecorators()
	{
	    if ($this->loadDefaultDecoratorsIsDisabled()) {
	        return $this;
	    }
	
	    $decorators = $this->getDecorators();
	    if (empty($decorators)) {
	        $this->addDecorator('ViewHelper');
	    }
	    return $this;
	}
}