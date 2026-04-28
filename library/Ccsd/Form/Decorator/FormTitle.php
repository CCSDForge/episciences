<?php

class Ccsd_Form_Decorator_FormTitle extends Zend_Form_Decorator_HtmlTag
{
    /**
     * HTML tag to use
     * @var string
     */
    protected $_tag = 'span';
    
    /**
     * Default placement: append
     * @var string
     */
    protected $_placement = 'PREPEND';

    /**
     * Render content wrapped in an HTML tag
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $form = $this->getElement();
        if (!$form instanceof Zend_Form) {
            return $content;
        }

        $countOfRequired = 0;
        foreach ($form->getElements() as $element) {
            if ($element->isRequired()) {
                $countOfRequired ++;

                if ($countOfRequired > 1) {
                    // juste pour savoir si plusieurs
                    break;
                }
            }
        }

        if ($countOfRequired != 0) {
            $this->setOption('class', 'required');
            
            $placement = $this->getPlacement();
            
            switch ($placement) {
                case self::PREPEND:
                    return parent::render('&nbsp;' . Zend_Form::getDefaultTranslator()->translate('Champs requis')) . $content;
                case self::APPEND:
                default:
                    return $content . parent::render('&nbsp;' . Zend_Form::getDefaultTranslator()->translate('Champs requis'));
            }
        }

        return $content;
    }
}