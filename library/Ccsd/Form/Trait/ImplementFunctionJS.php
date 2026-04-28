<?php

/**
 * Trait Ccsd_Form_Trait_ImplementFunctionJS
 *
 * Common function to implement: Ccsd_Form_Interface_Javascript
 * @see Ccsd_Form_Interface_Javascript
 */
trait Ccsd_Form_Trait_ImplementFunctionJS {
    
    protected $_javascript = array ('var' => array(), 'function' => array(), 'ready' => array ());

    /**
     * @param $code
     */
    public function addDocumentReady ($code)
    {
    	array_push ($this->_javascript['ready'], $code);
    }

    /**
     * @param string $function
     * @return string
     *
     * Un meme code n'est stockee qu'une seule fois sous un nom unique
     */
    public function addFunction($function)
    {
    	$name = array_search ($function, $this->_javascript['function']);
    
    	if ($name) {
    		return $name;
    	}
    
    	$name = uniqid('fct');
        while(array_key_exists ($name, $this->_javascript['function'])) {
    		$name = uniqid('fct');
    	}
    
    	$this->setJavascript($function, 'function', $name);
    
    	return $name;
    }

    /**
     * Retourne soit le code de la fonction demande, soit l'ensemble des codes javascript (array)
     * Appeler getJavascript('ready')  devrait retourner le code de ready:      ce n'est pas le cas!!! semble retourner l'ensemble du code!!!
     * Appeler getJavascript('var')    devrait retourner le code pour varname: ce n'est pas le cas!!!  semble retourner l'ensemble du code!!!
     * @param string $type
     * @param string $name
     * @return string|array|bool
     */
    public function getJavascript ($type = null, $name = null)
    {
    	if (null !== $type) {
    		if (null !== $name) {
    			if (isset ($this->_javascript[$type][$name])) {
    				return $this->_javascript[$type][$name];
    			} else return false;
    		}
    	}
    
    	return $this->_javascript;
    }

    /**
     * Permet d'ajouter un code javascript particulier, contrairement a addFunction, on specifie le nom
     * @see addFunction
     * @param string $js    //Code javascript
     * @param string $type  // ready|var|function
     * @param string $name
     * @return $this
     */
    public function setJavascript ($js, $type = null, $name = null)
    {
    	if (null !== $type) {
    		if (null !== $name) {
                $this->_javascript[$type][$name] = $js;
            } else {
    		    $this->_javascript[$type] = $js;
            }
        } else {
    		$this->_javascript = $js;
    	}
    
    	return $this;
    }
    
    public function clearJavascript ()
    {
    	$this->_javascript = array ('var' => array(), 'function' => array(), 'ready' => array ());
    }

    /**
     * Foo functions: Necessary to NOT put a relPublicDirPath attribute to Html element
     * It's that way Zend function
     * @see Zend_Form_Decorator_ViewHelper::render
     * @see Zend_Form::setOptions():381
     * @param $value
     */
    public function setRelPublicDirPath($value) {
    }

    /**
     * Foo functions: Necessary to NOT put a relPublicDirPath attribute to Html element
     * It's that way Zend function
     * @see Zend_Form_Decorator_ViewHelper::render
     * @see Zend_Form::setOptions():381
     * @param $value
     */
    public function setPathdir($value) {
    }
 }