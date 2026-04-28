<?php

class Ccsd_Form_Decorator_ButtonDropdowns extends Zend_Form_Decorator_HtmlTag
{
    public $values = array();
    public $indice;
    
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

        $xhtml = "";

        $languages = $element->getLanguages();
        
        $isArray = $element->isArray();
        $element->setIsArray(false);

        /*$xhtml .= "<div class='btn-group'";
        
        if (Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED == $element->getDisplay() && !$element->isClone()) {
            $xhtml .= " style='display: none;'";
        }
        
        $xhtml .= ">";*/
        
        $class = $this->getOption('class');
        
        $xhtml .= "<button class='$class' data-toggle='dropdown'";

        if ($element->isClone()) {
            $lang  = array_slice ((isset($this->values) ? array_diff_key ($languages, $this->values) : $languages), 0, 1);
            $key   = key($lang) ? key($lang) : key(array_slice ($languages, 0, 1));
            $value = current($lang) ? current($lang) : current(array_slice ($languages, 0, 1));
        } else {
            $key = $this->indice;
            $value = isset ($languages[$this->indice]) ? $languages[$this->indice] : "";
        }
        
        $diff = isset($this->values) ? array_diff_key($languages, $this->values) : $languages;

        if (!$element->isClone()) {
            $xhtml .= " disabled='disabled'";
        }

        $xhtml .= " value='" . $key . "'><span>" . $value;
        
        $xhtml .= "</span>&nbsp;";

        if ($element->isClone()) {
            $xhtml .= "<span class='caret'></span>";
        }
        
        $xhtml .= "</button>";
  
        $xhtml .= "<ul class='dropdown-menu' style='text-align: center;'>";
        
        $function = $element->addFunction ($this->buildJSchange()) . "(this, '" . $element->getFullyQualifiedName(). "');";

        foreach ($languages as $code => $libelle) {
            $xhtml .= "<li";
            
            if (isset($this->values) && array_key_exists($code, $this->values)) {
                $xhtml .= " class='disabled'";
            }
   
            $xhtml .= ">";
            $xhtml .= "<a val='$code' href='javascript:void(0);' onclick=\"" . $function . "\">$libelle</a></li>";
        }

        $xhtml .= "</ul>";

        $element->setIsArray($isArray);

        return $content . $xhtml;
    }
    
    public function buildJSchange ()
    {
        return <<<JAVASCRIPT
function %%FCT_NAME%% (elm, name) {
    var code = $(elm).attr('val');
    var libelle = $(elm).html();
    $(elm).closest("div").find("button").val(code).find("span:first").html(libelle);          
    $(elm).closest("div.input-append").parent().find('.input-prepend:first').find('input:first, textarea:first').attr('name', name + '[' + code + ']');    
    return false;
}
JAVASCRIPT;
        
    }
}