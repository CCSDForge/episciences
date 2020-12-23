<?php

/**
 * Class Ccsd_View_Helper_Sidebar
 *
 * Helper de view pour afficher un menu dependant du style de menu souhaite: tabs, list accodeon,...
 * Attention: seul vraiment teste: tabs! :-(
 */
class Ccsd_View_Helper_Sidebar extends Zend_View_Helper_Abstract
{
    /**
     * @param string $type
     * @param Ccsd_Website_Navigation $nav  ??
     * @param string $prefix
     */
    public function sidebar($type, $nav, $prefix = '/') {
        $fileName = __DIR__ . '/Sidebar/' . $type . '.phtml';
        if (is_file(($fileName)) && (is_readable($fileName))) {
            require_once $fileName;
        }

    }
}