<?php

class Ccsd_Form_Element_Date extends Zend_Form_Element_Text implements Ccsd_Form_Interface_Javascript
{
	use Ccsd_Form_Trait_ImplementFunctionJS;
    public $pathDir = __DIR__ ;
    public $relPublicDirPath =  "../../../public"; 
	
    public function init()
    {
        $this->loadDefaultDecorators();        
        //$this->addValidator(new Ccsd_Form_Validate_Date(array('format' => array ('yyyy-MM-dd', 'yyyy-MM', 'yyyy'))));
    }
   

    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        if (empty($decorators)) {                
            $this->addDecorator('Date', array('class' => 'form-control input-sm'))
                ->addDecorator('Errors', array('placement' => 'PREPEND'))
                ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
                ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
                ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }
        return $this;
    }
}