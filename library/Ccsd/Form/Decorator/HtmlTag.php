<?php

class Ccsd_Form_Decorator_HtmlTag extends Zend_Form_Decorator_HtmlTag
{ 
    /**
     * Render content wrapped in an HTML tag
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $tag       = $this->getTag();
        $placement = $this->getPlacement();
        $noAttribs = $this->getOption('noAttribs');
        $openOnly  = $this->getOption('openOnly');
        $closeOnly = $this->getOption('closeOnly');

        $attribs = null;
        if (!$noAttribs) {
            $this->removeOption('noAttribs');
            $this->removeOption('openOnly');
            $this->removeOption('closeOnly');
            
            $attribs = $this->getOptions();
            
            $this->setOption('noAttribs', $noAttribs);
            $this->setOption('openOnly', $openOnly);
            $this->setOption('closeOnly', $closeOnly);
        }
    
        switch ($placement) {
            case self::APPEND:
                if ($closeOnly) {
                    return $content . $this->_getCloseTag($tag);
                }
                if ($openOnly) {
                    return $content . $this->_getOpenTag($tag, $attribs);
                }
                return $content
                . $this->_getOpenTag($tag, $attribs)
                . $this->_getCloseTag($tag);
            case self::PREPEND:
                if ($closeOnly) {
                    return $this->_getCloseTag($tag) . $content;
                }
                if ($openOnly) {
                    return $this->_getOpenTag($tag, $attribs) . $content;
                }
                return $this->_getOpenTag($tag, $attribs)
                . $this->_getCloseTag($tag)
                . $content;
            default:
                return (($openOnly || !$closeOnly) ? $this->_getOpenTag($tag, $attribs) : '')
                . $content
                . (($closeOnly || !$openOnly) ? $this->_getCloseTag($tag) : '');
        }
    }
}