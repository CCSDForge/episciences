<?php

class Ccsd_Form_Decorator_GroupArea extends Ccsd_Form_Decorator_Group
{
    protected $_decorators = [
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'textarea-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true, 'placement' => Zend_Form_Decorator_Abstract::PREPEND]],
        ['decorator' => 'CViewHelper', 'options' => ['class' => 'form-control input-sm', 'style' => 'border-bottom-right-radius: 0;']],
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'pull-right', 'openOnly' => true]],
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'input-group', 'style' => 'display: table-cell', 'openOnly' => true]],
        ['decorator' => 'Multi', 'options' => ['class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-top-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;']],
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true]],
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true]],
        ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true, 'placement' => Zend_Form_Decorator_Abstract::APPEND]]
    ];

    public function loadDefaultDecorators()
    {
        $this->_decorators = [
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'textarea-group', 'style' => 'margin-bottom : 10px;', 'openOnly' => true, 'placement' => Zend_Form_Decorator_Abstract::PREPEND]],
            ['decorator' => 'CViewHelper', 'options' => ['class' => 'form-control input-sm', 'style' => 'border-bottom-right-radius: 0;']],
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'pull-right', 'openOnly' => true]],
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'class' => 'input-group', 'style' => 'display: table-cell', 'openOnly' => true]],
            ['decorator' => 'Multi', 'options' => ['class' => 'btn btn-sm btn-primary', 'style' => 'border-top-left-radius:0; border-top-right-radius:0; border-top: 0; height: 30px; padding-top:0; padding-bottom: 0;']],
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true]],
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true]],
            ['decorator' => 'HtmlTag', 'options' => ['tag' => 'div', 'closeOnly' => true, 'placement' => Zend_Form_Decorator_Abstract::APPEND]]
        ];
        return $this;
    }

    public function setWrappers()
    {
        parent::setWrappers();

        $this->_decorators[0]['options']['class'] = 'textarea-group advanced';
        $this->_decorators[1]['options']['style'] = 'font-size: inherit; display: inline-block; text-align: justify; white-space: normal; padding: 1px  0px 1px 10px;';
    }
}
