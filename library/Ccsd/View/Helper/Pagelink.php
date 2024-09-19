<?php

/**
 * Création d'un lien pour la navigation
 * @author yannick
 *
 */
class Ccsd_View_Helper_Pagelink
{
    public const PREFIX = '/';
    /**
     * Créé le lien d'une page
     * @param Zend_Navigation_Page $page
     * @param string $prefixUrl
     * @return string
     */
    public function pagelink(Zend_Navigation_Page $page, string $prefixUrl = self::PREFIX): string
    {
        $controller = $page->getController();
        $action = $page->getAction();

        if ($controller === '') {
            return $action;
        }

        if ($controller === 'index' && $action === 'index') {
            return $prefixUrl;
        }

        return $prefixUrl . $controller . '/' . $action;

    }

}