<?php

class Ccsd_Form_Decorator_Liste extends Ccsd_Form_Decorator_Thesaurus
{
    /* Variables d'initialisation pour la génération du Javascript */
    protected function _initViewVariables () 
    {
        parent::_initViewVariables();

        $this->_code    = $this->_element->getTagcode();
        $this->_label   = $this->_element->getTaglabel();
    }

    /* Variables pour le fonctionnement interne */
    protected function _initInternVariables ()
    {
        parent::_initInternVariables();
        
        $this->levels         = array ();
        $this->sep            = $this->_element->getSeparator();
        $this->display_tag    = $this->_element->getTagdisplay();
        $this->tag_label      = $this->_element->getTaglabel();
    }

    public function init ()
    {
        $items = $this->items;
        $sep   = $this->sep;
        
        $hasChildren = function ($code) use ($items, $sep)
        {
            foreach (array_keys($items) as $item) {
                if (strpos($item, $code  . $sep) === 0) {
                    return true;
                }
            }
        
            return false;
        };
        
        $createLevels = function ($aCode)
        {
            $str = "";
            end ($aCode);
            $str .= '"' . current($aCode) . '":{}';
            while (prev($aCode)) {
                $str = '"' . current($aCode) . '":{' . $str . '}';
            }
            $str = Zend_Json::decode("{" . $str . "}");
            return $str;
        };

        foreach ($this->items as $code => $item) {
            $childrens = $hasChildren ($code);
        
            $this->levels = array_merge_recursive ($this->levels, $createLevels (explode($this->sep, $code)));
        
            if (!empty($this->displayed_status)) 
                $shift = array_shift($this->displayed_status);

            $isDisplayed = $this->isBtnDisplayed && isset($shift) ? $shift : true;
            $isOpened    = $this->isBtnDisplayed && isset($shift) ? $shift : false;
        
            if ($this->isBtnDisplayed && !empty($this->displayed_status)) {
                if ($isOpened && $shift == '2') {
                    $this->endJavascript .= '$(\'ul.tree input[value="' . $code . '"]\').click();';
                }
            }

            $str = "<li " . ($isDisplayed ? "" : ($this->typeahead_v ? "style='display:none;'" : "")  )  . " >";
            $str .= "<input id='$code' value='$code' style='display: none;' type='" . ($childrens ? "checkbox" : "hidden") . "'></input>";
            $str .= "<label>";
        
            if ($childrens) {
                $str .= "<label  for='$code' style='margin-bottom: 2px; margin-right: 5px;'>";
        
                if ($this->isShowing_caret) 
                    $str .= "<span class='caret'></span>";

                if ($this->isShowing_icons)
                    $str .= "<span class='" . $this->icon_close_pa . "' aria-hidden='true'></span>";

                $str .= "</label>";
            } else if ($this->isShowing_icons) {
                $str .= "<label  for='$code' style='margin-bottom: 2px; margin-right: 5px;'>";
        
                if ($this->isShowing_icons) 
                    $str .= "<span class='" . $this->icon_child . "' aria-hidden='true' style='margin-left: 13px; cursor: default;'></span>";

                $str .= "</label>";
            }
        
            $str .= "<span class='libelle'>";

            if (array_key_exists($this->display_tag, $item)) {
                $str .= $item[$this->display_tag];
            } else {
                $str .= $item[$this->tag_label];
            }

            $str .= "</span>";
            $str .= "</label>";

            $this->items[$code] = $str;
        }

        if (!function_exists("display")) {
            function display ($array, $items, &$output, $code = null, $sep) {
                foreach ($array as $key => $value) {
                    if ($code) 
                        $key = isset ($code) ? "$code$sep$key" : $key;

                    $output .= $items[$key];
                     
                    if (is_array ($value) && !empty($value)) {
                        $childrens = "";
                        display ($value, $items, $childrens, $key, $sep);
                        $output .= "<ul>" . $childrens . "</ul>";
                    }
        
                    $output .= "</li>";
                }
            };
        }

        
        display ($this->levels, $this->items, $this->_output, null, $this->sep);
    }
    
    protected function _update ()
    {
        return $this->items;
    }
}