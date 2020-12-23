<?php

class Ccsd_Form_Element_MultiReferentiel extends Ccsd_Form_Element_Referentiel {

    protected $_multiple = true;
    
    protected $_isArray = true;

    public function getMultiple ()
    {
        return $this->_multiple;
    }
        
    public function getDeleteFunction ()
    {
        return $this->_deleteFunction . " (this, \"" . $this->getName() . "\", \"" . $this->_multiple . "\");";
    }
    
    public function setValue ($value)
    {        
        $class = 'Ccsd_Referentiels_' . ucfirst($this->_type);
        
        if (!is_array ($value)) {
        	$value = array ($value);
        }
        
        foreach ($value as &$v) {
        	if ($v instanceof Ccsd_Referentiels_Abstract) {
        		continue;
        	}
        	
        	if (is_array ($v)) {
        		$v = (new $class)->set($v);
        	} else {
        		$v = (new $class)->load($v);
        	}
            //Zend_Debug::dump($v);
        }

        $this->_value = $value;
        return $this;
    }

    /*public function isValid ($values)
    {
        if (!is_array($values)) {
            $values = array($values);
        }
        $values = array_filter($values);

        $class = 'Ccsd_Referentiels_' . ucfirst($this->_type);
        $valid = true;
        foreach($values as $value) {
            $valid = $valid && (new $class())->isValid($value);
        }
        if (! $valid) {
            $this->addError(Ccsd_Form::getDefaultTranslator()->translate("Invalid data"));
            $this->markAsError();
        }
        return $valid;
    }*/

}