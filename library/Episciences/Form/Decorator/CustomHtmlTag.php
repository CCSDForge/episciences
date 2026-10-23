<?php

declare(strict_types=1);

/**
 * Like HtmlTag but preserves hyphens in tag names (needed for HTML5 custom elements like <altcha-widget>).
 * ZF1's HtmlTag::normalizeTag() strips all non-alphanumeric characters via Zend_Filter_Alnum,
 * which would turn "altcha-widget" into "altchawidget".
 */
class Episciences_Form_Decorator_CustomHtmlTag extends Zend_Form_Decorator_HtmlTag
{
    public function normalizeTag($tag): string
    {
        return strtolower($tag);
    }
}
