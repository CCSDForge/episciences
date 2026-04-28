<?php
class Episciences_Form_Element_Html extends Zend_Form_Element_Xhtml
{
    public $helper = 'formNote';

    public function loadDefaultDecorators()
    {
        $this->addDecorator('ViewHelper');
    }
} 
?>