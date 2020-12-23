<?php

class Ccsd_Form_Decorator_Form extends Zend_Form_Decorator_Form
{
    /**
     * Default placement: append
     * @var string
     */
    protected $_placement = 'APPEND';
    
    public function render($content)
    {        
        $form    = $this->getElement();
        $view    = $form->getView();
        if (null === $view) {
            return $content;
        }

        $this->setOption('role', 'form');
        $this->setOption('data-library', 'ccsd');
        
        $placement = $this->getPlacement();
        
        switch ($placement) {
            case self::PREPEND:
                return parent::render("") . $content;
            case self::APPEND:
            default:
                return parent::render($content);
        }
    }
    
}
