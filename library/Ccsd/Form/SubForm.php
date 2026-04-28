<?php

/**
 * Class Ccsd_Form_SubForm
 * @m ethod Ccsd_Form getDecorator(string )
 */
class Ccsd_Form_SubForm extends Zend_Form_SubForm
{    
    protected $_actions = false;
    
    public function init()
    {

        $this->loadDefaultDecorators();
        
        $this->addPrefixPath('ZendX_JQuery_Form_Element','ZendX/JQuery/Form/Element', Zend_Form::ELEMENT);
        $this->addPrefixPath('ZendX_JQuery_Form_Decorator', 'ZendX/JQuery/Form/Decorator', Zend_Form::DECORATOR);
        $this->addPrefixPath('Ccsd_Form_Element','Ccsd/Form/Element', Zend_Form::ELEMENT);
        $this->addPrefixPath('Ccsd_Form_Decorator', 'Ccsd/Form/Decorator', Zend_Form::DECORATOR);
        $this->addPrefixPath('Ccsd_Form_Decorator_Bootstrap', 'Ccsd/Form/Decorator/Bootstrap/', Zend_Form::DECORATOR);
    }
    
    /**
     * Retrieve all form element values
     *
     * @param  bool $suppressArrayNotation
     * @return array
     */
    public function getValues($suppressArrayNotation = false)
    {
        $values = parent::getValues($suppressArrayNotation);

        foreach ($this->getElements() as $e) {
            if ($e instanceof Ccsd_Form_Element_Invisible || $e instanceof Ccsd_Form_Element_Hr) {
                unset ($values[$e->getName()]);
            }
        }

        return $values;
    }
    
    /**
     * Load the default decorators
     *
     * @return Zend_Form
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }
    
        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('FormElements')
                 ->addDecorator('FormActions');
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function hasActions ()
    {
        return $this->_actions;
    }

    /**
     * @param bool $b
     * @return $this
     */
    public function setActions ($b = false)
    {
        $this->_actions = $b;
        return $this;
    }

    /**
     *
     */
    public function createSubmitButton ($name = "Enregistrer", $options = array ())
    {
        $this->getDecorator("FormActions")->initSubmit($name, $options);
        //self::getDefaultTranslator()->translate($name);
        return $this;
    }
    
    public function createCancelButton ($name = "Annuler", $options = array ())
    {
        $this->getDecorator("FormActions")->initCancel($name, $options);
        // self::getDefaultTranslator()->translate($name);
        return $this;
    }
	
}