<?php

class Ccsd_Form_Decorator_FormCss extends Zend_Form_Decorator_HtmlTag
{
    private function process ($form) {
        $output = "";
        
        foreach ($form as $item) {
            if ($item instanceof Zend_Form_SubForm) {
                $output .= $this->process ($item);
            }  else if ($item instanceof Zend_Form_DisplayGroup) {
                $output .= $this->process ($item);
            } else {
                if ($item instanceof Ccsd_Form_Interface_Css) {
                    $output .= implode (" ", $item->getStylesheets());
                }
            }
        }
        
        return $output;
    }
    
    public function render($content)
    {
        $form    = $this->getElement();
        if (!$form instanceof Ccsd_Form) {
            return $content;
        }

        $output = $this->process($form);

        return ($output ? ("<style>" . $output . "</style>") : "") . $content;
    }
}