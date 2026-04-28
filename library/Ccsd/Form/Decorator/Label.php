<?php

class Ccsd_Form_Decorator_Label extends Zend_Form_Decorator_Label
{
    
    const PREPEND_KEVIN  = 'PREPEND_KEVIN';
    
    /**
     * Whether or not to escape the description
     * @var bool
     */
    protected $_escape = false;
    
    /**
     * Render a label
     *
     * @param  string $content
     * @return string
     */
    public function render($content)
    {
        $element = $this->getElement();
        $view    = $element->getView();
        if (null === $view) {
            return $content;
        }

        $label     = $this->getLabel();
        $separator = $this->getSeparator();
        $placement = $this->getPlacement();
        $tag       = $this->getTag();
        $tagClass  = $this->getTagClass();
        $id        = $this->getId();
        $class     = $this->getClass();
        $options   = $this->getOptions();

        if (empty($label) && empty($tag)) {
            return $content;
        }

        if (!empty($label)) {
            $options['class'] = $class;
            $label            = trim($label);

            switch ($placement) {
                case self::IMPLICIT:
                    // Break was intentionally omitted

                case self::IMPLICIT_PREPEND:
                    $options['escape']     = false;
                    $options['disableFor'] = true;

                    $label = $view->formLabel(
                        $element->getFullyQualifiedName(),
                        $label . $separator . $content,
                        $options
                    );
                    break;

                case self::IMPLICIT_APPEND:
                    $options['escape']     = false;
                    $options['disableFor'] = true;

                    $label = $view->formLabel(
                        $element->getFullyQualifiedName(),
                        $content . $separator . $label,
                        $options
                    );
                    break;
                    
                case self::PREPEND_KEVIN:
                    $options['escape']     = false;
                    $options['disableFor'] = true;
                
                    $label = $view->formLabel(
                            $element->getFullyQualifiedName(),
                            $label,
                            $options
                    ) . $content;
                    break;

                case self::APPEND:
                    // Break was intentionally omitted
                    $options['escape']     = $this->_escape;
                case self::PREPEND:
                    // Break was intentionally omitted
                    $options['escape']     = $this->_escape;
                default:
                    $label = $view->formLabel(
                        $element->getFullyQualifiedName(),
                        $label,
                        $options
                    );
                    break;
            }
        } else {
            $label = '&#160;';
        }

        switch ($placement) {
            case self::APPEND:
                return $content . $separator . $label;

            case self::PREPEND:
                return $label . $separator . $content;

            case self::IMPLICIT:
                // Break was intentionally omitted

            case self::IMPLICIT_PREPEND:
                // Break was intentionally omitted

            case self::PREPEND_KEVIN:
                // Break was intentionally omitted

            case self::IMPLICIT_APPEND:
                return $label;
        }
    }
}
