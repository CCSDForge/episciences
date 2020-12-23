<?php

/**
 * Trait Ccsd_Form_Trait_Populate
 */
trait Ccsd_Form_Trait_Populate {
     /** @var string[] */
     protected $_populate;
     /** @var string */
     private   $_class;
    /** @var string   */
     private   $_method;
     private   $_args;
     protected $_data = array ();

    /**
     * @param array $populate
     * @return $this
     * @throws Zend_Form_Exception
     */
     public function setPopulate ($populate)
     {
         if (isset($populate['class']) && isset($populate['method'])) {
             if (!class_exists($populate['class'])) {
                 throw new Zend_Form_Exception(sprintf('Class not found : %s', $populate['class']));
             }
             
             $this->_class      = $populate['class'];
             
             if (!isset($populate['method'])) {
                 throw new Zend_Form_Exception(sprintf('Need a method'));
             }
             $this->_method     = $populate['method'];
              
             if (isset ($populate['args'])) {
                 $this->_args       = $populate['args'];
             }
         } else if (!is_array ($populate)) {
             throw new Zend_Form_Exception(sprintf("Can't populate with no array"));
         } else {
             $this->setData($populate);
         }

         $this->_populate   = $populate;
         
         return $this;
     }

    /**
     * @return bool
     */
     public function isPopulate ()
     {
         return isset ($this->_populate);
     }

    /**
     * @param $data
     * @return $this
     */
     public function setData ($data)
     {
         if (!is_array ($data)) {
             $data = array ($data);
         }
         
         $this->_data = $data;
         
         /* @var Hal_Translate $translator */
         $translator = Zend_Form::getDefaultTranslator();
         if (isset ($translator)) {
             $this->_data = array_map(function ($v) use($translator) {
                 if (is_array($v)) {
                     return $v;
                 }
                 switch ($v) {
                     case $translator->isTranslated($v) :
                         return $translator->translate($v);
                     case $translator->isTranslated('lang_' . $v) :
                         return $translator->translate('lang_' . $v);
                     default :
                         return $v;
                 }

             }, $this->_data);
         }

         return $this;
     }

    /**
     * @return array
     */
     public function getData ()
     {
         return $this->_data;
     }

    /**
     * @return bool
     */
     public function isDefined ()
     {
         return isset ($this->_class) && isset ($this->_method);
     }

    /**
     * @throws Zend_Form_Exception
     */
     public function build ()
     {
         if ($this->isDefined()) {
             try {
                 $reflectionMethod = new ReflectionMethod($this->_class, $this->_method);

                 if (isset ($this->_args)) {
                     $pass = array ();
                     foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                         if (!array_key_exists ($reflectionParameter->name, $this->_args)) {
                             //TODO si la méthode à des parametres optionnels
                             /*
                             require_once 'Zend/Form/Exception.php';
                             throw new Zend_Form_Exception(sprintf('Paramètre requis', $reflectionParameter->name));*/
                         } elseif ($this->_args[$reflectionParameter->name] != '') {
                             $pass[] = $this->_args[$reflectionParameter->name];
                         }
                     }
                 }

                if (empty ($pass)) {
                     $this->setData ($reflectionMethod->invoke(null));
                 } else {
                     $this->setData ($reflectionMethod->invokeArgs($reflectionMethod, $pass));
                 }
             } catch (Exception $e) {
                 throw new Zend_Form_Exception(sprintf('La méthode ne peut pas être appelée: %s', $this->_method));
             }   
         }

         if (!isset ($this->_data)) {
             throw new Zend_Form_Exception('Aucune donnée n\'est définie');
         }
     }
 }