<?php

class Episciences_View_Helper_Pagelink extends Ccsd_View_Helper_Pagelink
{
    public function pagelink(Zend_Navigation_Page $page, string $prefixUrl = PREFIX_URL): string{
        return parent::pagelink($page,$prefixUrl);
    }
}