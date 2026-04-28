<?php

class Ccsd_Form_Element_Submit extends Zend_Form_Element_Submit
{
    public $helper = 'formButton';
    
    public function __construct($spec, $options = null)
    {
        if (is_string($spec) && ((null !== $options) && is_string($options))) {
            $options = array('label' => $options);
        }
    
        if (!isset($options['ignore'])) {
            $options['ignore'] = true;
        }
        
        $options['type'] = 'submit';

        parent::__construct($spec, $options);
    }
}