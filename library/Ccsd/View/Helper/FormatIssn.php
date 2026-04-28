<?php

/**
 * Helper for formatting an 8 chars ISSN Number
 */
class Ccsd_View_Helper_FormatIssn extends Zend_View_Helper_Abstract
{
    const ISSN_NUMBER_SEPARATOR = '-';

    public static function FormatIssn($issn = null)
    {
        if (!$issn) {
            return '';
        }

        if (strlen($issn) != 8) {
            return $issn;
        }

        return substr($issn, 0, 4) . self::ISSN_NUMBER_SEPARATOR . substr($issn, 4, 8);

    }
}