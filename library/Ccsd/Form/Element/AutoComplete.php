<?php

class Ccsd_Form_Element_AutoComplete extends ZendX_JQuery_Form_Element_UiWidget {
    
    public $helper = "autoComplete";

    /**
     * méthodes jQuery de cet élément
     *
     * @var array
     */
    protected $_jQueryMethods = array();
    
    /**
     * événement jQuery de cet élément
     *
     * @var array
     */
    protected $_jQueryEvents = array();
    
    /**
     * Retourne la méthode jQuery lié à cet élément
     *
     * @param  string $key
     * @return string
     */
    public function getJQueryMethod($key)
    {
        $key = (string) $key;
        return $this->_jQueryMethods[$key];
    }
    
    /**
     * Retourne toutes les méthodes jQuery lié à cet élément
     *
     * @return array
     */
    public function getJQueryMethods()
    {
        return $this->_jQueryMethods;
    }
    
    /**
     * Enregistre une méthode jQuery pour cet élément
     *
     * @param  string $key
     * @param  string $value
     * @return ZendX_JQuery_Form_Element_UiWidget
     */
    public function setJQueryMethod($key, $value)
    {
        $key = (string) $key;
        $this->_jQueryMethods[$key] = $value;
        return $this;
    }
    
    /**
     * Enregistre des méthodes jQuery pour cet élément
     *
     * @param  Array $params
     * @return ZendX_JQuery_Form_Element_UiWidget
     */
    public function setJQueryMethods($params)
    {
        $this->_jQueryMethods = array_merge($this->_jQueryMethods, $params);
        return $this;
    }
    
    /**
     * Retourne un événement jQuery lié à cet élément
     *
     * @param  string $key
     * @return string
     */
    public function getJQueryEvent($key)
    {
        $key = (string) $key;
        return $this->_jQueryEvents[$key];
    }
    
    /**
     * Retourne toutes les événements jQuery lié à cet élément
     *
     * @return array
     */
    public function getJQueryEvents()
    {
        return $this->_jQueryEvents;
    }
    
    /**
     * Enregistre un événement jQuery pour cet élément
     *
     * @param  string $key
     * @param  string $value
     * @return ZendX_JQuery_Form_Element_UiWidget
     */
    public function setJQueryEvent($key, $value)
    {
        $key = (string) $key;
        $this->_jQueryEvents[$key] = $value;
        return $this;
    }
    
    /**
     * Enregistre des événements jQuery pour cet élément
     *
     * @param  Array $params
     * @return ZendX_JQuery_Form_Element_UiWidget
     */
    public function setJQueryEvents($params)
    {
        $this->_jQueryEvents = array_merge($this->_jQueryEvents, $params);
        return $this;
    }
    
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return;
        }
    
        $decorators = $this->getDecorators();

        if (empty($decorators)) {
            $this->addDecorator('UiWidgetElement', array('class' => 'form-control input-sm'))
            ->addDecorator('Errors', array ('placement' => Zend_Form_Decorator_Abstract::PREPEND))
            ->addDecorator('Description', array ('tag' => 'span', 'class' => 'help-block'))
            ->addDecorator('HtmlTag', array('tag' => 'div', 'class'  => "col-md-9"))
            ->addDecorator('Label', array('tag' => 'label', 'class' => "col-md-3 control-label"));
        }
    }

}