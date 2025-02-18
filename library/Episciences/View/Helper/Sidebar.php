<?php

class Episciences_View_Helper_Sidebar extends Ccsd_View_Helper_Sidebar
{
    /**
     * @param string $type
     * @param Ccsd_Website_Navigation $nav  ??
     * @param string $prefix
     */
    public function sidebar($type, $nav, $prefix = PREFIX_URL): void
    {
        parent::sidebar($type, $nav, $prefix);
    }
}