<?php

/**
 * Vérifications des droits d'accès à une ressource
 *
 */
class Episciences_Acl_Plugin extends Ccsd_Acl_Plugin
{

    public function getAcl()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('acl');
    }

    /**
     * @param string $resource
     * @return bool
     */
    public function isAllowed($resource):bool
    {
        $allow = false;
        if ($this->_acl->has($resource)) {
            foreach (Episciences_Auth::getRoles() as $role) {
                if ($this->_acl->isAllowed($role, $resource)) {
                    $allow = true;
                    break;
                }
            }
        } else {
            $allow = true;
        }
        return $allow;
    }
}