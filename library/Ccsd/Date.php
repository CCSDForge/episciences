<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 05/12/18
 * Time: 09:32
 */

class Ccsd_Date extends Zend_Date
{

    const YM     = 'yyyy' . '-' . Zend_Date::MONTH;
    const YMD    = 'yyyy' . '-' . Zend_Date::MONTH . '-' . Zend_Date::DAY;
    const YMDHMS = 'yyyy-MM-dd HH:mm:ss';
    /**
     * On s'assure que la date donnee va etre lu correctement par Zend date en precisant le format d'entree.
     * Si le format est inconnu ici, on laisse Zend_Date se debrouiller
     *
     * Note: On pourra ajouter des format si necessaire.
     *
     * Il est indispensable de preciser un format correct sinon Zend peut faire n'importe quoi
     *
     * @param string $str
     * @param string $part
     * @param string $locale
     * @return void|Zend_Date
     * @throws Zend_Date_Exception
     */
    public function set($str, $part = null, $locale = null) {
        if ($part == null) {
            if (preg_match('/^\s*\d{4}\s*$/', $str)) {
                $part = 'yyyy';
            } elseif (preg_match('/^\s*\d{4}-\d\d\s*$/', $str)) {
                $part = self::YM;
            } elseif (preg_match('/^\s*\d{4}-\d\d-\d\d\s*$/', $str)) {
                $part = self::YMD;
            } elseif (preg_match('/^\s*\d{4}-\d\d-\d\d\s+\d\d:\d\d:\d\d\s*$/', $str)) {
                $part = self::YMDHMS;
            } else {
                $part = 'yyyy-MM-dd';
            }
        }
        parent::set($str, $part, $locale);
    }
}