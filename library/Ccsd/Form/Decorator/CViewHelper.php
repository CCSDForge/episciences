<?php

class Ccsd_Form_Decorator_CViewHelper extends Zend_Form_Decorator_ViewHelper
{
	//use Ccsd_Form_Trait_GenerateFunctionJS;
	
	public function render($content)
    {
        $element = $this->getElement();
        if (!$element instanceof Ccsd_Form_Element_MultiText) {
            return $content;
        }
        
        if ($this->getOption ('disabled')) {
        	$element->setAttrib('disabled', 'disabled');
        }
        
        $class = $this->getOption('class');
        //Il faut conserver les classes CSS
        $element->setAttrib('class', $element->getAttrib('class') . ' ' . $class);

        if (!$element->isClone()) {
            $messages  = $element->getMessages();
        }
        
        $helper = $element->helper;
        $style = "";
        
        if (Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED == $element->getDisplay() && !$element->isClone()){
            if (!($element instanceof Ccsd_Form_Element_MultiTextArea || $element instanceof Ccsd_Form_Element_MultiTextAreaLang)) {
                $element->helper = 'formHidden';
            } else {
                $style = 'display: none;';
            }
        }

        $element->setAttrib('style', $this->getOption('style') . " $style");
        
        if (method_exists($element, 'isPluriValues') && $element->isPluriValues()) {
        	$element->setAttrib('data-keyword', true);
        }
        
        if (($element instanceof Ccsd_Form_Element_MultiTextArea || $element instanceof Ccsd_Form_Element_MultiTextAreaLang) && $element->isTiny() && !$element->isClone()) {
        	$element->helper = 'formTextareaTinyMCE';
        }

        $content = parent::render($content);

        $element->helper = $helper;

        return $content;
    }
}