<?php

class Ccsd_Form_Element_Description extends Zend_Form_Element
{
    public function render (Zend_View_Interface $view = null) {
        $tag = $this->getAttrib('tag');
        if (!$tag) {
            $tag = 'h4';
        }
        $icon = $this->getAttrib('icon');
        if ($icon) {
            $icon = '<i class="' . $icon . '"></i>&nbsp;';
        }
        return "<$tag>" . $icon . Zend_Registry::get('Zend_Translate')->translate($this->getName()) . "</$tag>";
    }
    
}