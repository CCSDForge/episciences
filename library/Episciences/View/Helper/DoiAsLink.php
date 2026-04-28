<?php

/**
 * Helper for formatting a DOI as link
 */
class Episciences_View_Helper_DoiAsLink extends Zend_View_Helper_Abstract
{
    public static function DoiAsLink($doi = '', $text = '', $asHtml = true): string
    {
        if (!$doi) {
            return '';
        }
        if ($asHtml) {
            $label = $text ?: 'https://doi.org/' . $doi;
            return sprintf('<a rel="noopener noreferrer" href="https://doi.org/%s">%s</a>', $doi, $label);
        } else {
            $format = 'https://doi.org/%s';
            return sprintf($format, $doi);
        }
    }
}
