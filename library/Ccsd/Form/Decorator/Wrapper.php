<?php

class Ccsd_Form_Decorator_Wrapper extends Zend_Form_Decorator_HtmlTag
{

    public $indice;
    
    public function render($content)
    {
        $openOnly  = $this->getOption('openOnly');
                
        if ($openOnly) {
        
            $element = $this->getElement ();
 
            $value = strip_tags($element->getValue());
            
            if ($element->getLength() && $value > $element->getLength()) {
                $value = substr($value, 0, $element->getLength()) . '...';    
            }
            
            $content .= $value;
            
            if ($element instanceof Ccsd_Form_Element_MultiTextLang) {

                $aLangs = $element->getLanguages();
                
                $content .= " (";
                $content .= isset ($aLangs[$this->indice]) ? ucfirst($aLangs[$this->indice]) : $this->indice;                
                $content .= ")";
                 
            }
        }

        return parent::render( $content );
    }
}