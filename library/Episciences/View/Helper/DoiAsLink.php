<?php

/**
 * Helper for formatting a DOI as link
 */
class Episciences_View_Helper_DoiAsLink extends Zend_View_Helper_Abstract
{
    public static function DoiAsLink($doi = '', $text = '', $asHtml = true)
    {
        if (!$doi) {
            return '';
        }
        if ($asHtml) {
            if (!$text) {
                $text = $doi;
            }
            $format = '<a href="https://doi.org/%s">https://doi.org/%s</a>';
            return sprintf($format, $doi, $text);
        } else {
            $format = 'https://doi.org/%s';
            return sprintf($format, $doi);
        }
    }
}
