<?php

class Ccsd_Form_Decorator_UiWidgetElement extends ZendX_JQuery_Form_Decorator_UiWidgetElement
{

    /**
     * événement jQuery de cet élément
     *
     * @var array
    */
    protected $_jQueryEvents = array();
    
    /**
     * méthodes jQuery de cet élément
     *
     * @var array
     */
    protected $_jQueryMethods = array();
    
    /**
     * Enregistre un événement jQuery pour cet élément
     *
     * @param  string $key
     * @param  string $value
     * @return Ccsd_Form_Decorator_UiWidgetElement
     */
    public function setJQueryEvent($key, $value)
    {
        $this->_jQueryEvents[(string) $key] = $value;
        return $this;
    }
   
    /**
     * Enregistre des événements jQuery pour cet élément
     *
     * @param  array $params
     * @return Ccsd_Form_Decorator_UiWidgetElement
     */
    public function setJQueryEvents(array $params)
    {
        $this->_jQueryEvents = array_merge($this->_jQueryEvents, $params);
        return $this;
    }
    
    /**
     * Retourne un événement jQuery lié à cet élément
     *
     * @param  string $key
     * @return mixed|null
     */
    public function getJQueryEvent($key)
    {
        $this->getElementAttribs();
        $key = (string) $key;
        if (array_key_exists($key, $this->_jQueryEvents)) {
            return $this->_jQueryEvents[$key];
        }
    
        return null;
    }
    
    /**
     * Retourne toutes les événements jQuery lié à cet élément
     *
     * @return array
     */
    public function getJQueryEvents()
    {
        $this->getElementAttribs();
        return $this->_jQueryEvents;
    } 
    
    /**
     * Enregistre une méthode jQuery pour cet élément
     *
     * @param  string $key
     * @param  string $value
     * @return Ccsd_Form_Decorator_UiWidgetElement
     */
    public function setJQueryMethod($key, $value)
    {
        $this->_jQueryMethods[(string) $key] = $value;
        return $this;
    }
     
    /**
     * Enregistre des méthodes jQuery pour cet élément
     *
     * @param  array $params
     * @return Ccsd_Form_Decorator_UiWidgetElement
     */
    public function setJQueryMethods(array $params)
    {
        $this->_jQueryMethods = array_merge($this->_jQueryMethods, $params);
        return $this;
    }
    
    /**
     * Retourne une méthode jQuery lié à cet élément
     *
     * @param  string $key
     * @return mixed|null
     */
    public function getJQueryMethod($key)
    {
        $this->getElementAttribs();
        $key = (string) $key;
        if (array_key_exists($key, $this->_jQueryMethods)) {
            return $this->_jQueryMethods[$key];
        }
    
        return null;
    }
    
    /**
     * Retourne toutes les méthodes jQuery lié à cet élément
     *
     * @return array
     */
    public function getJQueryMethods()
    {
        $this->getElementAttribs();
        return $this->_jQueryMethods;
    }
      
    /**
     * Récupère les attributs de l'élément
     *
     * @return array
    */
    public function getElementAttribs()
    {
        if (null === $this->_attribs) {
            if($this->_attribs = parent::getElementAttribs()) {
                if (array_key_exists('jQueryEvents', $this->_attribs)) {
                    $this->setJQueryEvents($this->_attribs['jQueryEvents']);
                    unset($this->_attribs['jQueryEvents']);
                }
                
                if (array_key_exists('jQueryMethods', $this->_attribs)) {
                    $this->setJQueryEvents($this->_attribs['jQueryMethods']);
                    unset($this->_attribs['jQueryMethods']);
                }
            }
        }
    
        return $this->_attribs;
    }

    /**
     * Render an jQuery UI Widget element using its associated view helper
     *
     * @param  string $content
     * @return string
     * @throws Zend_Form_Decorator_Exception if element or view are not registered
     */
    public function render($content)
    {
        $element = $this->getElement();
        
        $view = $element->getView();
        if (null === $view) {
            require_once 'Zend/Form/Decorator/Exception.php';
            throw new Zend_Form_Decorator_Exception('UiWidgetElement decorator cannot render without a registered view object');
        }

        $separator = $this->getSeparator();
        
        $attribs   = $this->getElementAttribs();
        $id = $element->getId();
        $attribs['id'] = $id;
        $attribs['class'] = $this->getOption('class');
        $this->_attribs = $attribs;

        $elementContent = $this->_callHelper($element, $view, $this->getHelper());
        
        switch ($this->getPlacement()) {
            case self::APPEND:
                return $content . $separator . $elementContent;
            case self::PREPEND:
                return $elementContent . $separator . $content;
            default:
                return $elementContent;
        }
    }

    protected function _callHelper (Zend_Form_Element $element, Zend_View_Interface $view, $helper)
    {
        return $view->$helper($element->getFullyQualifiedName(), $this->getValue($element), $element->getJQueryParams(), $element->getJQueryEvents(), $element->getJQueryMethods(), $this->_attribs);
    }
    
    
    
    
    
}