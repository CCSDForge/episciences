<?php

class Ccsd_Form_Decorator_FormElements extends Zend_Form_Decorator_FormElements
{
    /**
     * Character encoding to use when escaping attributes
     * @var string
     */
    protected $_encoding;

    /**
     * Placement; default to surround content
     * @var string
     */
    protected $_placement = null;

    /**
     * HTML tag to use
     * @var string
     */
    protected $_tag = "div";

    /**
     * @var Zend_Filter
     */
    protected $_tagFilter;
    /**
     * Set tag to use
     *
     * @param  string $tag
     * @return Zend_Form_Decorator_HtmlTag
     */
    public function setTag($tag)
    {
        $this->_tag = $this->normalizeTag($tag);
        return $this;
    }
    
    /**
     * Get tag
     *
     * If no tag is registered, either via setTag() or as an option, uses 'div'.
     *
     * @return string
     */
    public function getTag()
    {
        if (null === $this->_tag) {
            if (null === ($tag = $this->getOption('tag'))) {
                $this->setTag('div');
            } else {
                $this->setTag($tag);
                $this->removeOption('tag');
            }
        }
    
        return $this->_tag;
    }
    
    /**
     * Convert options to tag attributes
     *
     * @return string
     */
    protected function _htmlAttribs(array $attribs)
    {
        $xhtml = '';
        $enc   = $this->_getEncoding();
        foreach ((array) $attribs as $key => $val) {
            $key = htmlspecialchars($key, ENT_COMPAT, $enc);
            if (is_array($val)) {
                if (array_key_exists('callback', $val)
                        && is_callable($val['callback'])
                ) {
                    $val = call_user_func($val['callback'], $this);
                } else if (is_array ($val)) {
                    $val = @implode(' ', $val);
                }
            }
            $val    = htmlspecialchars($val, ENT_COMPAT, $enc);
            $xhtml .= " $key=\"$val\"";
        }
        return $xhtml;
    }
    
    /**
     * Normalize tag
     *
     * Ensures tag is alphanumeric characters only, and all lowercase.
     *
     * @param  string $tag
     * @return string
     */
    public function normalizeTag($tag)
    {
        if (!isset($this->_tagFilter)) {
            require_once 'Zend/Filter.php';
            require_once 'Zend/Filter/Alnum.php';
            require_once 'Zend/Filter/StringToLower.php';
            $this->_tagFilter = new Zend_Filter();
            $this->_tagFilter->addFilter(new Zend_Filter_Alnum())
            ->addFilter(new Zend_Filter_StringToLower());
        }
        return $this->_tagFilter->filter($tag);
    }
    
    /**
     * Get encoding for use with htmlspecialchars()
     *
     * @return string
     */
    protected function _getEncoding()
    {
        if (null !== $this->_encoding) {
            return $this->_encoding;
        }
    
        if (null === ($element = $this->getElement())) {
            $this->_encoding = 'UTF-8';
        } elseif (null === ($view = $element->getView())) {
            $this->_encoding = 'UTF-8';
        } elseif (!$view instanceof Zend_View_Abstract
                && !method_exists($view, 'getEncoding')
        ) {
            $this->_encoding = 'UTF-8';
        } else {
            $this->_encoding = $view->getEncoding();
        }
        return $this->_encoding;
    }
    
    /**
     * Get the formatted open tag
     *
     * @param  string $tag
     * @param  array $attribs
     * @return string
     */
    protected function _getOpenTag(array $attribs = null)
    {
        $html = '<' . $this->_tag;
        if (null !== $attribs) {
            $html .= $this->_htmlAttribs($attribs);
        }
        $html .= '>';
        return $html;
    }
    
    /**
     * Get formatted closing tag
     *
     * @param  string $tag
     * @return string
     */
    protected function _getCloseTag()
    {
        return '</' . $this->_tag . '>';
    }
    
    /**
     * Render form elements
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $form    = $this->getElement();
        if ((!$form instanceof Zend_Form) && (!$form instanceof Zend_Form_DisplayGroup)) {
            return $content;
        }

        $belongsTo      = ($form instanceof Zend_Form) ? $form->getElementsBelongTo() : null;
        $elementContent = '';
        $displayGroups  = ($form instanceof Zend_Form) ? $form->getDisplayGroups() : array();
        $separator      = $this->getSeparator();
        $translator     = $form->getTranslator();
        $items          = array();
        $view           = $form->getView();

        foreach ($form as $item) {
            /* @var $item Zend_Form_Element */
            $item->setView($view)->setTranslator($translator);
            
            if ($item instanceof Zend_Form_Element) {
                foreach ($displayGroups as $group) {
                    $elementName = $item->getName();
                    $element     = $group->getElement($elementName);
                    if ($element) {
                        // Element belongs to display group; only render in that
                        // context.
                        continue 2;
                    }
                }
                $item->setBelongsTo($belongsTo);
            } elseif (!empty($belongsTo) && ($item instanceof Zend_Form)) {
                if ($item->isArray()) {
                    $name = $this->mergeBelongsTo($belongsTo, $item->getElementsBelongTo());
                    $item->setElementsBelongTo($name, true);
                } else {
                    $item->setElementsBelongTo($belongsTo, true);
                }
            } elseif (!empty($belongsTo) && ($item instanceof Zend_Form_DisplayGroup)) {
                foreach ($item as $element) {
                    $element->setBelongsTo($belongsTo);
                }
            }

            $render = $item->render();
            if ($render) {
                $attribs = $this->getOptions();

                $attribs['class'] = 'form-group row ' ;
                $matches = [];
                if (preg_match('/(meta\-(section|complete))/', $item->getAttrib('class'), $matches) &&isset($matches[1])) {
                    //pour le dépôt simplifié
                    $attribs['class'] .= ' ' . $matches[1];
                }

                /* @var $item Zend_Form_Element */
                if (method_exists ($item, 'hasErrors') && $item->hasErrors()) {
                    $attribs['class'] .= ' has-error';
                }
                
                $attribs['id'] = array('callback' => array(get_class($item), 'resolveElementId'));
                
                $this->setElement($item);

                if (!$item instanceof Zend_Form_SubForm && 
                    !$item instanceof Zend_Form_DisplayGroup && 
                    !$item instanceof Ccsd_Form_Element_Hidden && 
                    !$item instanceof Ccsd_Form_Element_Hr &&
                    !$item instanceof Zend_Form_Element_Button &&
                    !$item instanceof Ccsd_Form_Element_Submit) {
                    
                    $d = $item->getDecorator('HtmlTag');

                    if (isset ($d) && is_object($d) && $d instanceof Zend_Form_Decorator_HtmlTag) {
                        $openOnly  = $d->getOption('openOnly');
                        $closeOnly = $d->getOption('closeOnly');
                    } else {
                        $openOnly = $closeOnly = false;
                    }
                    
                    $items[] = ($closeOnly ? "" : $this->_getOpenTag($attribs)) . $render . ($openOnly ? "" : $this->_getCloseTag());
                    
                    //$items[] = $this->_getOpenTag($attribs) . $render . $this->_getCloseTag();
                } else {
                    $items[] = $render;
                }
            }

            if (($item instanceof Zend_Form_Element_File)
                || (($item instanceof Zend_Form)
                    && (Zend_Form::ENCTYPE_MULTIPART == $item->getEnctype()))
                || (($item instanceof Zend_Form_DisplayGroup)
                    && (Zend_Form::ENCTYPE_MULTIPART == $item->getAttrib('enctype')))
            ) {
                if ($form instanceof Zend_Form) {
                    $form->setEnctype(Zend_Form::ENCTYPE_MULTIPART);
                } elseif ($form instanceof Zend_Form_DisplayGroup) {
                    $form->setAttrib('enctype', Zend_Form::ENCTYPE_MULTIPART);
                }
            }
        }
        $elementContent = implode($separator, $items);

        switch ($this->getPlacement()) {
            case self::PREPEND:
                return $elementContent . $separator . $content;
            case self::APPEND:
            default:
                return $content . $separator . $elementContent;
        }
    }
}
