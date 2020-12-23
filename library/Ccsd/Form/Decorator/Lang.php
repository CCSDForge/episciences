<?php

class Ccsd_Form_Decorator_Lang extends Zend_Form_Decorator_HtmlTag
{
    public $indice;
    
   /* public $init;
    
    use Ccsd_Form_Trait_GenerateFunctionJS;
    
    public function setElement($element)
    {
        parent::setElement($element);

        return $this->buildJS ('lang/', array (
            'function' => array ('init')
        ));
    }*/
    
    public function render($content)
    {
        $element = $this->getElement();
        if (!$element instanceof Ccsd_Form_Element_MultiTextLang) {
            return $content;
        }
        
        $view = $element->getView();
        if (!$view instanceof Zend_View_Interface) {
            return $content;
        }
        
        if (!$element->isClone() && Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED == $element->getDisplay()) {
            return $content;
        }

        $xhtml = "";

        $languages = $element->getLanguages();
        
        $isArray = $element->isArray();
        $element->setIsArray(false);

        $style = $this->getOption('style');
        $class = $this->getOption('class');
        $ul_style = $this->getOption('ul_style');
        
        if (!$ul_style) {
        	$ul_style = 'max-height: 140px; overflow:auto;';
        }
        
        if (!$element instanceof Ccsd_Form_Element_MultiTextAreaLang) {
            $style .= " border-left: 0!important; border-right: 0!important;";
        }

        $xhtml .= "<button class='$class dropdown-toggle' style='$style z-index:0!important;' data-toggle='dropdown' type='button'";        
        $xhtml .= " value='" . ($this->indice ? $this->indice : key($languages)) . "'>" . ($this->indice ? $languages[$this->indice] : current($languages));
        $xhtml .= "&nbsp;";
        $xhtml .= "<span class='caret'></span>";
        $xhtml .= "</button>";
        $xhtml .= "<ul class='dropdown-menu' role='menu' style='$ul_style'>";
 
        $xhtml .= $this->renderSelectedChoice($element);

        $element->setIsArray($isArray);

        return $content . $xhtml;
    }
    
    protected function renderSelectedChoice ($element)
    {
        $xhtml = "";
        
        $function = $this->init . "(this, '" . $element->getFullyQualifiedName(). "');";

        $values = $element->getUnprocessedValues();
        
       
        if ($element->isPluriValues()) {
        	$values = array_map('array_filter', $values ? $values : array ());
        }
         
        $values = array_filter($values);

        $languages = $element->getLanguages();

        foreach ($languages as $code => $libelle) {
            $xhtml .= "<li";

            if (isset($values) && $values && array_key_exists($code, $values) && !$element->isPluriValues()) {
            	if ($element->getDisplay() == Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED) {
            		$xhtml .= " class='disabled'";
            	} else if (in_array($code, array_keys($values)) && $this->indice != $code) {
            		$xhtml .= " class='disabled'";
            	}
            }
             
            $xhtml .= ">";
            $xhtml .= "<a val='$code' href='javascript:void(0);' onclick=\"" . $function . "\">$libelle</a></li>";
        }
        
        $xhtml .= "</ul>";
        
        return $xhtml;
    }
}