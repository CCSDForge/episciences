<?php

/**
 * Class Ccsd_Form_Element_MultiTextArea
 */
class Ccsd_Form_Element_MultiTextArea extends Ccsd_Form_Element_MultiText
{
    /**
     * Helper de vue par défaut utilisé pour l'affichage
     * @var string
     */
    public $helper = 'formTextarea';

    /**
     * Load default decorators
     *
     * @return Zend_Form_Element
     * @throws Zend_Form_Exception
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }
    
        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('GroupArea')
            ->addDecorator('GroupErrors', array ('placement' => Zend_Form_Decorator_Abstract::PREPEND))
            ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
            ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
            ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }
        return $this;
    }
}