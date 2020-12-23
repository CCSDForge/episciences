<?php

class Ccsd_Form_Decorator_ViewHelper extends Zend_Form_Decorator_ViewHelper
{
    /**
     * Render an element using a view helper
     *
     * Determine view helper from 'viewHelper' option, or, if none set, from
     * the element type. Then call as
     * helper($element->getName(), $element->getValue(), $element->getAttribs())
     *
     * @param  string $content
     * @return string
     * @throws Zend_Form_Decorator_Exception if element or view are not registered
     */
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

        $helper        = $this->getHelper();
        $separator     = $this->getSeparator();
        $value         = $this->getValue($element);
        $attribs       = $this->getElementAttribs();
        $name          = $element->getFullyQualifiedName();
        $id            = $element->getId();
        $class         = $this->getOption("class");   
        $attribs['id'] = $id;
        
        if ($class) {
            if (isset ($attribs['class']) && $attribs['class']) {
                $attribs['class'] = $attribs['class'] . " " . $class;
            } else {
                $attribs['class'] = $class;
            }
            
        }

        $helperObject  = $view->getHelper($helper);
        if (method_exists($helperObject, 'setTranslator')) {
            $helperObject->setTranslator($element->getTranslator());
        }

        // Check list separator
        if (isset($attribs['listsep'])
            && in_array($helper, array('formMulticheckbox', 'formRadio', 'formSelect'))
        ) {
            $listsep = $attribs['listsep'];
            unset($attribs['listsep']);

            $elementContent = $view->$helper($name, $value, $attribs, $element->options, $listsep);
        } else {
            
            $elementContent = $view->$helper($name, $value, $attribs, $element->options);
        }

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
