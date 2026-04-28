<?php

class Ccsd_Form_Element_Referentiel extends Ccsd_Form_Element_AutoComplete implements Ccsd_Form_Interface_Javascript {
	
	use Ccsd_Form_Trait_ImplementFunctionJS;
	public $pathDir = __DIR__ ;
    public $relPublicDirPath =  "../../../public"; 
		
    protected $_type;
    
    protected $_deleteFunction = "delete_ref";
    protected $_editFunction = "edit_ref";
    private $url = "/ajax/ajaxgetreferentiel?element=";
    protected $_ref = array ();
    
    public $helper = "referentiel";

    protected $_more = false;

    public function init ()
    {
        parent::init();

        $this->jQueryParams['type']  = "POST";
        $this->jQueryParams['async'] = false;
    }

    public function getType ()
    {
        return $this->_type;
    }
    
    public function setType ($type)
    {
        $this->_type = $type;
        $this->jQueryParams['url'] = $this->url . $this->getName() . "&type=$type";
        return $this;
    }
    
    public function setMore ($b = false)
    {
    	$this->_more = $b;
    	return $this;
    }
    
    public function isMore()
    {
    	return $this->_more;
    }
    
    public function getRef ()
    {
        return $this->_ref;
    }
    
    public function setRef ($r=array())
    {
        $this->_ref = $r;
        return $this;
    }
    
    public function setDeleteFunction ($str = "")
    {
        $this->_deleteFunction = $str;
        return $this;
    }
    
    public function getDeleteFunction ()
    {
        return $this->_deleteFunction . " (this, \"" . $this->getName() . "\", \"false\");";
    }
    
    public function setEditFunction ($str = "")
    {
        $this->_editFunction = $str;
        return $this;
    }
    
    public function getEditFunction ()
    {
        return $this->_editFunction . " (\"" . $this->_type . "\", \"" . $this->getName() . "\", this);";
    }

    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return;
        }
    
        $decorators = $this->getDecorators();

        if (empty($decorators)) {
            $this->addDecorator('Referentiel', array('class' => 'form-control input-sm'))
            ->addDecorator('Errors', array ('placement' => Zend_Form_Decorator_Abstract::PREPEND))
            ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
            ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
            ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }
    }

    public function render ( Zend_View_interface $view = null) {
        if ($this->_isPartialRendering) {
            return '';
        }

        if (null !== $view) {
            $this->setView($view);
        }

        $content = '';
        /** @var Zend_Form_Decorator_Abstract $decorator */
        foreach ($this->getDecorators() as $decorator) {
            $decorator->setElement($this);
            $content = $decorator->render($content);
        }
        
        return $content;
    }
    
    public function setValue ($value)
    {
        $class = 'Ccsd_Referentiels_' . ucfirst($this->_type);
        /** @var Ccsd_Referentiels_Abstract $class */
        $class = new $class;
        
        if ($value instanceof Ccsd_Referentiels_Abstract) {
            $this->_value = $value;
            return $this;
        }

        if (is_array ($value)) {
            $value = $class->set($value);
        } else {
            $value = $class->load($value);
        }     
 
        $this->_value = $value;
        return $this;
    }
    
    public function getValue () 
    {
        return $this->_value;
    }
    
    public function isValid ($value, $context = null)
    {
    	$v = true;
    	if (is_array($value)) {
            $value = array_filter($value);
        }
    	if (!empty ($value)) {
    	    /** @var Ccsd_Referentiels_Abstract $class */
    		$class = 'Ccsd_Referentiels_' . ucfirst($this->_type);
    		$class = new $class();
    		if (!($class->isValid($value))) {
    			$v = false;
    			$this->addError(Ccsd_Form::getDefaultTranslator()->translate("Invalid data"));
    			$this->markAsError();
    		}
    	}
        return $v && parent::isValid($value);
    }   

}