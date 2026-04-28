<?php

/**
 * Class Ccsd_Form_Decorator_FormJavascript
 * @property array[] $_javascript
 * @property array   $_replace
 */
class Ccsd_Form_Decorator_FormJavascript extends Zend_Form_Decorator_HtmlTag
{
    /**
     * @param Ccsd_Form_Interface_Javascript $item
     */
    private function processItem($item) {
        $javascript = $item->getJavascript();
        foreach (array('var', 'function', 'ready') as $type) {
            foreach ($javascript[$type] as $i => $js) {
                if (!in_array ($js, $this->_javascript[$type])) {
                    if ('ready' == $type) {
                        $this->_javascript[$type][] = $js;
                    } else {
                        $this->_javascript[$type][$i] = $js;
                    }
                } else if (!in_array ($type, array ('ready', 'var'))) {
                    $this->_replace[$i] = array_search($js, $this->_javascript[$type]);
                }   // else {
                    // Hum... What to do here???
                    // Script deja present et pas a considerer
                    // }
            }
        }

    }
    /**
     * @param Zend_Form $form
     */
    private function processForm ($form) {
        foreach ($form as $item) {
            if ($item instanceof Zend_Form_SubForm) {
                $this->processForm ($item);
            } else if ($item instanceof Zend_Form_DisplayGroup) {
                $this->processForm ($item);
            } else {
                if ($item instanceof Ccsd_Form_Interface_Javascript) {
                    $this->processItem($item);
                }
            }
        }
    }

    /**
     * @param string $content
     * @return string
     */
    public function render($content)
    {
        $form    = $this->getElement();
        if (!$form instanceof Ccsd_Form) {
            return $content;
        }

        $output = "";
 
        $this->_javascript = array ('var' => array(), 'function' => array(), 'ready' => array ());
        
        $this->_replace = array ();

        $this->processForm($form);

        if (!empty($this->_javascript['ready'])) {
            $output .= " $(document).ready(function() { ";
            foreach ($this->_javascript['ready'] as $code) {
                $output .= $code;
            }
            $output .= " }); ";
        }

        if (!empty($this->_javascript['function'])) {
            foreach ($this->_javascript['function'] as $name => $code) {
                $output .= str_replace ("%%FCT_NAME%%", $name, $code);
            }
        }

        foreach ($this->_replace as $i => $j) {
            $content = str_replace($i, $j, $content);
            $output = str_replace($i, $j, $output);
        }

        return ($output ? ("<script type='text/javascript'>" . $output . "</script>") : "") . $content;
    }
}