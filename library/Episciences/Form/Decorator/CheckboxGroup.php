<?php

class Episciences_Form_Decorator_CheckboxGroup extends Zend_Form_Decorator_HtmlTag
{
    public function render($content)
    {
        /*
         Par défaut : 
         	- ViewHelper : utilise une aide de vue pour rendre l'élément balise de formulaire à proprement parlé.
			- Errors : utilise l'aide de vue FormErrors pour afficher les erreurs de validation éventuelles.
			- Description : utilise l'aide de vue FormNote afin de rendre la description éventuelle de l'élément.
			- HtmlTag : encapsule les trois objets ci-dessus dans un tag <dd>.
			- Label : rend l'intitulé de l'élément en utilisant l'aide de vue FormLabel (et en encapsulant le tout dans un tag <dt>).  
         */
        
        
        $element = $this->getElement();
        $view = $element->getView();
        $name = $element->getName();
        $values = $element->getValue();
        // $name = $element->getFullyQualifiedName();
                
        foreach ($element->getMultiOptions() as $value => $label) {
            $checked = (count($values) && in_array($value, $values)) ? true : false;
        	$checkboxes[] = $view->formCheckbox($name.'[]', $value, array('checked' => $checked))
        	.$view->formLabel($name.'[]', $label).'<br/>';
        }
        
        $checkboxes = implode(PHP_EOL, $checkboxes); 
        
        switch ($this->getPlacement()) {
			case (self::PREPEND):
        		return $checkboxes.$content;
        	case (self::APPEND):
        	default:
				return $content.$checkboxes;
        }
    }
}