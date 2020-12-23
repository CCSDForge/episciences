<?php

class Ccsd_Form_Decorator_UiWidgetReferentiel extends Ccsd_Form_Decorator_UiWidgetElement
{
    
    protected function _callHelper (Zend_Form_Element $element, Zend_View_Interface $view, $helper)
    {
        return $view->$helper(
                $element->getFullyQualifiedName(),
                $this->getValue($element), 
                $this->_jQueryParams, 
                $this->_jQueryEvents, 
                $this->_jQueryMethods, 
                $this->_attribs, 
                $element->getType(), 
                $element instanceof Ccsd_Form_Element_MultiReferentiel
        );
    }

}