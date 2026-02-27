<?php

class Ccsd_Form_Decorator_FormActions extends Zend_Form_Decorator_HtmlTag
{
    /**
     * HTML tag to use
     * @var string
     */
    protected $_tag = 'div';
    /** @var Zend_Form_Element_Button  */
    public $_submit = null;
    /** @var Zend_Form_Element_Button  */
    public $_cancel = null;
    
    public function initSubmit($name, $options) 
    {
        if (empty($options)) {
            $options = array ("class" => "btn btn-primary", "style" => "margin-top: 15px;");
            $options['label'] = Ccsd_Form::getDefaultTranslator()->translate(isset ($options["label"]) ? $options["label"] : $name);
        }
        
        if (!isset ($this->_submit)) {
            $this->_submit = new Ccsd_Form_Element_Submit($name, $options);
        }
        
        $this->_submit->setDecorators(array('ViewHelper'));
    }

    /**
     * @param string $name
     * @param array $options
     */
    public function initCancel($name, $options)
    {
        if (empty($options)) {
            $options = array ("class" => "btn btn-default", "style" => "margin-top: 15px;");
            $options['label'] = Ccsd_Form::getDefaultTranslator()->translate(isset ($options["label"]) ? $options["label"] : $name);
        }
        
        if (!isset ($this->_cancel)) {
            $this->_cancel = new Zend_Form_Element_Button($name, $options);
        }
        
        $this->_cancel->setDecorators(array('ViewHelper'));
    }

    /**
     * @param string $content
     * @return string
     */
    public function render($content)
    {
        $element = $this->getElement();
        if ((!$element instanceof Ccsd_Form && !$element instanceof Ccsd_Form_SubForm) || !$element->hasActions()) {
            return $content;
        }

        $xhtml = "";

        if (!$this->getOption("class")) {
            $this->setOption("class", "form-actions text-center");
        }
        if (!$this->getOption("style")) {
            //$this->setOption("style", "clear: both;");
        }
        if (empty ($xhtml)) {
            if (isset($this->_submit)) {
                $xhtml .= $this->_submit->__toString();
            }
            // Add spacing between buttons
            if (isset($this->_submit) && isset ($this->_cancel)) {
                $xhtml .= '&nbsp;&nbsp;&nbsp;';
            }
            if (isset ($this->_cancel)) {
                $xhtml .= $this->_cancel->__toString();
            }
        }

        return $content . parent::render( $xhtml );
    }
}