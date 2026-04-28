<?php

class Ccsd_Form_Decorator_Multi extends Zend_Form_Decorator_HtmlTag
{

    public $values = array();
    
    public function render($content)
    {
        $element = $this->getElement();
        if (!$element instanceof Ccsd_Form_Element_MultiText) {
            return $content;
        }

        $view = $element->getView();
        if (!$view instanceof Zend_View_Interface) {
            return $content;
        }

        $isArray = $element->isArray();
        $element->setIsArray(false);
        
        $mode = $this->getOption('mode');
        if (!$mode) {
            $mode = 'default';
        }

        $xhtml = "";
        
        $class = $this->getOption('class');
        $style = $this->getOption('style');

        $xhtml .= $this->{"render_" . $mode}($element, $class, $style);
        
        $element->setIsArray($isArray);

        return $content . $xhtml;
    }

    /** @param Ccsd_Form_Element_MultiText $element */
    protected function render_default ($element, $class, $style) 
    {
        $xhtml = "<button type='button' class='$class'  style='$style' ";
        
        if ($element->isClone()) {
            $xhtml .= "onclick='" . $this->add . "(this, \"" . $element->getFullyQualifiedName() . "\");'";
        } else {
            $xhtml .= "onclick='" . $this->delete . "(this, \"" . $element->getFullyQualifiedName() . "\");'";
        }
        
        if ($element->isClone()) {
            $xhtml .= "data-toggle='tooltip' data-placement='right' data-original-title='" . Ccsd_Form::getDefaultTranslator()->translate("Ajouter") . "'><i class='glyphicon glyphicon-plus'></i>";
        } else {
            $xhtml .= "data-toggle='tooltip' data-placement='right' data-original-title='" . Ccsd_Form::getDefaultTranslator()->translate("Supprimer") . "'><i class='glyphicon glyphicon-trash'></i>";
        }
        
        $xhtml .= "</button>";
        
        return $xhtml;
    }
    
    protected function render_edit ($element, $class, $style)
    {
        $xhtml = "<button type='button' class='$class'  style='$style' ";
        $xhtml .= "onclick='" . $this->modify . "(this, \"" . $element->getFullyQualifiedName() . "\");'";
        $xhtml .= ">";
        $xhtml .= "<i class='glyphicon glyphicon-pencil'></i>";
        $xhtml .= "</button>";
        return $xhtml;
    }
}