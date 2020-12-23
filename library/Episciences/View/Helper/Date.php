<?php

class Episciences_View_Helper_Date extends Zend_View_Helper_Abstract
{
    /**
     * get date in localized format
     * @param null $date
     * @param null $locale
     * @param null $format
     * @return bool|string
     * @throws Zend_Date_Exception
     * @throws Zend_Exception
     */
    public static function Date($date = null, $locale = null, $format = null)
    {
        if (!$date) {
            return false;
        }

        $oDate = new Zend_Date();
        $date = strtotime($date);

        if (!$date) {
            return false;
        }

        if (!isset($format)) {
            $format = Zend_Date::DATE_LONG;
        }

        if (!isset($locale)) {
            $locale = Zend_Registry::get('Zend_Locale')->toString();
        }

        return $oDate->set($date)->toString($format, $locale);
    }
}