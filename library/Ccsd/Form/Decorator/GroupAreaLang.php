<?php

/**
 * Class Ccsd_Form_Decorator_GroupAreaLang
 */
class Ccsd_Form_Decorator_GroupAreaLang extends Ccsd_Form_Decorator_GroupArea
{

    protected $_decorators = array (
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'textarea-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),
        array ('decorator' => 'CViewHelper', 'options' => array ('class' => 'form-control input-sm', 'style' => 'border-bottom-right-radius: 0;')),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'pull-right', 'openOnly' => true)),   
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'input-group', 'style' => 'display: table-cell', 'openOnly' => true)),        
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'class' => 'input-group-btn btn-group' , 'openOnly' => true)),   
        array ('decorator' => 'Lang',        'options' => array ('class' => 'btn btn-sm btn-default', 'style' => 'border-top-left-radius:0; border-top-right-radius:0; border-bottom-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;')),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'closeOnly' => true)),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'class' => 'input-group-btn' , 'openOnly' => true)),
        array ('decorator' => 'Multi',       'options' => array ('class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-bottom-left-radius:0; border-top-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;')),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'closeOnly' => true)),      
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true)),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true)),
        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND))
    );

    /**
     * @return $this|Ccsd_Form_Decorator_GroupArea
     * TODO: Comprendre a quoi cela sert-il puique la valeur initiale est la meme
     */
    public function loadDefaultDecorators ()
    {
    	$this->_decorators = array (
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'textarea-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true,'placement' => Zend_Form_Decorator_Abstract::PREPEND)),
	        array ('decorator' => 'CViewHelper', 'options' => array ('class' => 'form-control input-sm', 'style' => 'border-bottom-right-radius: 0;')),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'pull-right', 'openOnly' => true)),   
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'class' => 'input-group', 'style' => 'display: table-cell', 'openOnly' => true)),        
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'class' => 'input-group-btn btn-group' , 'openOnly' => true)),   
	        array ('decorator' => 'Lang',        'options' => array ('class' => 'btn btn-sm btn-default', 'style' => 'border-top-left-radius:0; border-top-right-radius:0; border-bottom-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;')),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'closeOnly' => true)),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'class' => 'input-group-btn' , 'openOnly' => true)),
	        array ('decorator' => 'Multi',       'options' => array ('class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-bottom-left-radius:0; border-top-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;')),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'span', 'closeOnly' => true)),      
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true)),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true)),
	        array ('decorator' => 'HtmlTag',     'options' => array ('tag' => 'div', 'closeOnly' => true,'placement' => Zend_Form_Decorator_Abstract::APPEND))
	    );
    	return $this;
    }
}