<?php

/**
 * decorator Email input text
 *
 */
class Ccsd_Form_Decorator_Bootstrap_InputAddon extends Zend_Form_Decorator_ViewHelper
{

    public function render($content)
    {
        $element = $this->getElement();
    
        $view = $element->getView();
        if (null === $view) {
            require_once 'Zend/Form/Decorator/Exception.php';
            throw new Zend_Form_Decorator_Exception('ViewHelper decorator cannot render without a registered view object');
        }
    
        if (method_exists($element, 'getMultiOptions')) {
            $element->getMultiOptions();
        }
    
        $helper           = $this->getHelper();
        $separator        = $this->getSeparator();
        $value            = $this->getValue($element);
        $attribs          = $this->getElementAttribs();
        $name             = $element->getFullyQualifiedName();
        $id               = $element->getId();
        $class            = $this->getOption('class');
        $addon             = $this->getOption('addon');
        $attribs['id']    = $id;
        $attribs['class'] = $class;
        
        
        $helperObject  = $view->getHelper($helper);
        if (method_exists($helperObject, 'setTranslator')) {
            $helperObject->setTranslator($element->getTranslator());
        }
    
        $elementContent  = '<div class="input-group input-group-sm">';
        $elementContent .= '<span class="input-group-addon">' . $addon . '</span>';

        // Check list separator
        if (isset($attribs['listsep'])
                && in_array($helper, array('formMulticheckbox', 'formRadio', 'formSelect'))
        ) {
            $listsep = $attribs['listsep'];
            unset($attribs['listsep']);
    
            $elementContent .= $view->$helper($name, $value, $attribs, $element->options, $listsep);
        } else {
            $elementContent .= $view->$helper($name, $value, $attribs, $element->options);
        }

        $elementContent .= '</div>';
    
        switch ($this->getPlacement()) {
            case self::APPEND:
                return $content . $separator . $elementContent;
            case self::PREPEND:
                return $elementContent . $separator . $content;
            default:
                return $elementContent;
        }
    }

}