<?php

class Ccsd_Form_Decorator_GroupErrors extends Zend_Form_Decorator_Errors
{
    
    public $validators = array (Ccsd_Form_Element_MultiTextLang::VALIDATOR_NOT_SAME);

    public function render($content)
    {
        $element = $this->getElement();
        $view    = $element->getView();
        if (null === $view) {
            return $content;
        }

        /** @var Zend_Form $element*/
        $errors = $element->getGroupErrors();
        if (empty($errors)) {
            return $content;
        } else {
            while (is_array($errors)) {
                $errors = array_shift($errors);
            }

        }

        $messages = $element->getMessages();

        if (empty($messages)) {
            return $content;
        }
        
        $separator = $this->getSeparator();
        $placement = $this->getPlacement();

        if ($element instanceof Ccsd_Form_Element_MultiTextAreaLang) {
            $list = $view->formErrors($messages, $this->getOptions());
        } else if (is_array ($errors)) {
            $list = $view->formErrors(array_intersect_key ($messages,array_flip($errors)), $this->getOptions());
            $element->setMessages (array_diff_key ($messages, array_flip($errors)));
        }

        if (empty($list)) {
            return $content;
        }

        switch ($placement) {
            case self::APPEND:
                return $content . $separator . $list;
            case self::PREPEND:
                return $list . $separator . $content;
        }

        return $content;
    }
}