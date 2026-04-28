<?php

class Ccsd_Form_Element_Radio extends Zend_Form_Element_Radio {
      
    //use Ccsd_Form_Trait_MultiOptions;

    /**
     * Load default decorators
     *
     * @return Zend_Form_Element
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('ViewHelper', array('class' => 'form-control input-sm'))
                ->addDecorator('Errors', array('placement' => 'PREPEND'))
                ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
                ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
                ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }

        return $this;
    }
    
}