<?php

class Ccsd_Form_Decorator_Date extends Zend_Form_Decorator_HtmlTag
{

	use Ccsd_Form_Trait_GenerateFunctionJS;
	
    public $values = array ();
    
    //public $init;
    
    public function setElement ($element)
    {
    	parent::setElement($element);
    	
        return $this->buildJS ('date/', array (
                'function' => array ('init')
        ));
    }

    public function render($content)
    {
        /* @var Zend_Form_Element_Text $element */
        $element = $this->getElement();
        $view    = $element->getView();
        if (null === $view) {
            return $content;
        }

        if ($element->getAttrib('id') == '') {
            $element->setAttrib('id', $element->getName() . '-id');
        }
        $element->setAttrib('onclick', '$(this).datepicker("hide")');
        $element->setAttrib('attr-trigger', '1');
        $element->setAttrib('attr-changemonth', '1');
        $element->setAttrib('attr-changeyear', '1');

        $output = '<div class="input-group">';
        $output .= "<span data-toggle='tooltip' data-placement='bottom' data-original-title='" 
        		. Ccsd_Form::getDefaultTranslator()->translate("Cliquer ici pour ouvrir le calendrier") . "' class=\"input-group-addon calendar-trigger\" onclick=\"" . $this->init . "('" . $element->getAttrib('id') . "');\"><i class=\"glyphicon glyphicon-calendar\"></i></span>";

        $class = 'form-control input-sm datepicker';
        if (strpos($element->getAttrib('class'), 'meta-complete') !== false) {
            $class .= ' meta-complete';
        }
        $element->setAttrib('class', $class);
        $output .= $view->{$element->helper}($element->getName(),
            $element->getValue(),
            $element->getAttribs());

        $output .= '</div>';
        return $output;
    }
}