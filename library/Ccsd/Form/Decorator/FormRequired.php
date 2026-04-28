<?php

class Ccsd_Form_Decorator_FormRequired extends Zend_Form_Decorator_HtmlTag
{
    /**
     * HTML tag to use
     * @var string
     */
    protected $_tag = 'span';
    
    /**
     * Default placement: append
     * @var string
     */
    protected $_placement = 'PREPEND';

    /**
     * Render content wrapped in an HTML tag
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $form = $this->getElement();
        if (!$form instanceof Zend_Form) {
            return $content;
        }
        
        $countOfRequired = 0;
        foreach ($form->getElements() as $element) {
            if ($element->isRequired()) {
                $countOfRequired ++;
        
                if ($countOfRequired > 1) {
                    // juste pour savoir si plusieurs
                    break;
                }
            }
        }
        
        if ($countOfRequired != 0) {
            $msg = Zend_Form::getDefaultTranslator()->translate('Champs requis');

            $tag       = $this->getTag();
            $placement = $this->getPlacement();
            $noAttribs = $this->getOption('noAttribs');
            $openOnly  = $this->getOption('openOnly');
            $closeOnly = $this->getOption('closeOnly');
            $this->removeOption('noAttribs');
            $this->removeOption('openOnly');
            $this->removeOption('closeOnly');
            
            $attribs = null;
            if (!$noAttribs) {
                $attribs = $this->getOptions();
            }
            
            $attribs['class'] = $this->getOption('class');
        
            switch ($placement) {
                case self::APPEND:
                    return $content
                    . $this->_getOpenTag($tag, $attribs)
                    . $msg
                    . $this->_getCloseTag($tag);
                case self::PREPEND:
                    return $this->_getOpenTag($tag, $attribs)
                    . $msg
                    . $this->_getCloseTag($tag)
                    . $content;
                default:
                    return $this->_getOpenTag($tag, $attribs)
                    . $msg
                    . $this->_getCloseTag($tag)
                    . $content;
            }
        }

        return $content;
    }
}