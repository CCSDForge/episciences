<?php

class Ccsd_Form_Decorator_Group extends Zend_Form_Decorator_HtmlTag
{
    public $indice = 0;
    public $hasValue = false;
    public $subIndice;
    
    use Ccsd_Form_Trait_GenerateFunctionJS;
    
    public $init;
    public $add;
    public $modify;
    public $delete;
    public $valid;
    public $length;
    
    public $isBuilt = false;

    protected $_decorators = array (
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'div', 'class' => 'input-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),           
        array ('decorator' => 'CViewHelper','options' => array ('class' => 'form-control input-sm')),
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'class' => 'input-group-btn btn-group', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND, 'onlyLang' => true)),
        array ('decorator' => 'Lang',       'options' => array ('class' => 'btn btn-sm btn-default', 'style' => 'border-radius:0; height: 30px; padding-top:0; padding-bottom: 0;', 'ul_style' => 'max-height: 140px; overflow:auto;')),
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND, 'onlyLang' => true)),
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'class' => 'input-group-btn' , 'openOnly' => true)),
        array ('decorator' => 'Multi',      'options' => array ('class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-bottom-left-radius:0; height: 30px; padding-top:0; padding-bottom: 0;')),
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'closeOnly' => true)),
        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'div', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND))
    );
    
    public function setDecorators ($decorators)
    {
        $this->_decorators = $decorators;
        return $this;
    }
    
    public function loadDefaultDecorators ()
    {
    	$this->_decorators = array (
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'div', 'class' => 'input-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),           
	        array ('decorator' => 'CViewHelper','options' => array ('class' => 'form-control input-sm')),
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'class' => 'input-group-btn btn-group', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND, 'onlyLang' => true)),
	        array ('decorator' => 'Lang',       'options' => array ('class' => 'btn btn-sm btn-default', 'style' => 'border-radius:0; height: 30px; padding-top:0; padding-bottom: 0;', 'ul_style' => 'max-height: 140px; overflow:auto;')),
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND, 'onlyLang' => true)),
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'class' => 'input-group-btn' , 'openOnly' => true)),
	        array ('decorator' => 'Multi',      'options' => array ('class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-bottom-left-radius:0; height: 30px; padding-top:0; padding-bottom: 0;')),
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'span', 'closeOnly' => true)),
	        array ('decorator' => 'HtmlTag',    'options' => array ('tag' => 'div', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND))
	    );
    	return $this;
    }
    
    public function getDecorators ()
    {
        return $this->_decorators;
    }
 
    public function setWrappers () {
        $this->_decorators = array (
                array ('decorator' => 'HtmlTag',      'options' => array ('tag' => 'div', 'class' => 'input-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),
                array ('decorator' => 'Wrapper',      'options' => array ('tag' => 'span', 'class' => 'label label-primary', 'style' => 'font-size: inherit; display: block; text-align: justify; white-space: normal; padding: 1px  0px 1px 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),
                array ('decorator' => 'CViewHelper',  'options' => array ('class' => 'form-control')),
                array ('decorator' => 'Multi',        'options' => array ('mode' => 'edit',    'class' => 'btn btn-xs btn-primary', 'style' => 'border-radius:0; height: 20px; padding-top:0; padding-bottom: 0; margin-left: 5px;')),
                array ('decorator' => 'Multi',        'options' => array ('mode' => 'default', 'class' => 'btn btn-xs btn-primary', 'style' => 'border-radius:0; height: 20px; padding-top:0; padding-bottom: 0;')),
                array ('decorator' => 'Wrapper',      'options' => array ('tag' => 'span', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND)),
                array ('decorator' => 'HtmlTag',      'options' => array ('tag' => 'div', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND))
        );
    }
    
	protected function build ($element)
    {
    	if (method_exists($element, 'getLanguages')) {
    		$element = $this->buildJS ('lang/', array (
	    		'function' => array ('init')
	    	));
    	}
    	
    	$element = $this->buildJS ($element->getPrefix(), array (
    		'function' => array ('delete', 'valid', 'modify', 'add', 'refresh')
    	));
    	
    	$this->isBuilt = true;
    	
    	return $element;
    }
    
    public function render($content)
    {
        $element = $this->getElement();
        if (!$element instanceof Ccsd_Form_Element_MultiText) {
            return $content;
        }

        $view = $element->getView();
        if (!$view instanceof Zend_View_Interface) {
            return $content;
        }
  
        if (!$this->isBuilt) {
        	$element = $this->build($element);
        }

        $render = "";

        if (Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED == $element->getDisplay()) {
            if ($element->isClone()) {
                $this->loadDefaultDecorators();
            } else {
                $this->setWrappers();
            }
        }

        if ($element->isClone() && $element->isTiny() && $element instanceof Ccsd_Form_Element_MultiTextLang && !$element->isPluriValues()) {
        	$value = $element->getValue();
        	
        	if (is_array($value)) {
        		$value = array_diff_key($element->getLanguages(), $value);
        		
        		if (empty($value)) {
        			$this->_decorators[0]['options']['style'] .= ' display: none;';
        		}
        	}
        }

        if ($element->isClone() && $element instanceof Ccsd_Form_Element_MultiTextLang && !$element->isStillChoice()) {
        	$this->_decorators[0]['options']['style'] .= ' display: none;';
    		$this->_decorators[1]['options']['disabled'] = 'disabled';
        }

        foreach ($this->_decorators as $i => $decorator) {
            
            if (!$element instanceof Ccsd_Form_Element_MultiTextLang && !$element instanceof Ccsd_Form_Element_MultiTextArea && in_array($i, array(2,3,4)) && ($element->isClone() || Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED != $element->getDisplay())) {
                continue;
            }
            
            if ('Lang' == $decorator['decorator']) {
                $class  = "Ccsd_Form_Decorator_Lang";
                $class .= $element instanceof Ccsd_Form_Element_MultiTextLang && $element->isPluriValues() ? "_Keyword" : "";
            } else {
                $class = $element->getPluginLoader('DECORATOR')->load($decorator['decorator']);
            }

            $d = new $class($decorator['options']);
            
            $d->init 	= $this->init;
            $d->add 	= $this->add;
            $d->modify  = $this->modify;
            $d->delete  = $this->delete;
            $d->valid 	= $this->valid;
            $d->refresh = $this->refresh;

            if ($d instanceof Ccsd_Form_Decorator_Lang || $d instanceof Ccsd_Form_Decorator_CViewHelper) {
                $d->indice    = $this->indice;
                $d->subIndice = $this->subIndice;
            } else if ($d instanceof Ccsd_Form_Decorator_Wrapper) {
                $d->indice    = $this->indice;
            }
            
            $d->setElement($element);

            $render .= @$d->render('');
        }

        return $content . $render;
    }
}