<?php

class Ccsd_Form_Decorator_Referentiel extends Ccsd_Form_Decorator_UiWidgetReferentiel
{
    use Ccsd_Form_Trait_GenerateFunctionJS;
    
    public function setElement($element)
    {
        parent::setElement($element);
        
        return $this->buildJS ('referentiel/', array (
                'function' => array ('add', 'delete', 'edit')
        ));
    }

    public function render($content)
    {
        $element = $this->getElement();

        $view = $element->getView();
        if (null === $view) {
            require_once 'Zend/Form/Decorator/Exception.php';
            throw new Zend_Form_Decorator_Exception('Referentiel decorator cannot render without a registered view object');
        }
        
        $value = $element->getValue();

        if (!is_array ($value)) {
            $value = array ($value);
        }
        
        $value = array_filter($value);

        if ((bool)array_filter($value) && !$element instanceof Ccsd_Form_Element_MultiReferentiel) {
            $element->setAttrib('disabled', 'disabled');
        }
        
        if ($element instanceof Ccsd_Form_Element_MultiReferentiel) {
            $element->setAttrib('multiple', 'multiple');
        }

        $element->setValue("");

        $input = @parent::render($content);

        if ($value) {   
            $view->classname  = 'Ccsd_Referentiels_' . ucfirst($element->getType());
            $view->identifier = uniqid('ref');
            $view->item = $element;
            $view->element = $element->getName();
            foreach ($value as $v) {
                $content .= $v->__toString($element->isMore());
            }
        }
        
        
        return $content . $input;
    }

}