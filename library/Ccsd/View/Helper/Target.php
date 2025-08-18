<?php
class Ccsd_View_Helper_Target extends Zend_View_Helper_Abstract
{
    const BLANK_TARGET   = '_blank';
    const SELF_TARGET    = '_self';
    const PARENT_TARGET  = '_parent';
    const TOP_TARGET     = '_top';

    public function target($target) {
        if (!isset($target) || ($target=='')) {
            return "";
        }
        return "onclick=\"this.target='$target'\"";
    }
}