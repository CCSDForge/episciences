<?php

/**
 * Vérifications des droits d'accès à une ressource
 *
 */
class Episciences_Auth_Plugin extends Ccsd_Auth_Plugin
{


    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        // Récupération des règles d'accès
        $this->_acl = $this->getAcl();
        // Récupération de l'id de la ressource (à modifier)
        $resource = $request->getControllerName() . '-' . $request->getActionName();
        if (APPLICATION_MODULE == OAI) {
            return true;
        }
        if ($this->_acl->has($resource)) {
            //La ressource demandée existe
            if (!$this->isAllowed($resource)) {
                //L'utilisateur ne peut pas accéder à la page
                if (!Episciences_Auth::isLogged()) {
                    //L'utilisateur n'est pas connecté
                    $request->setParam('forward-action', $request->getActionName());
                    $request->setParam('forward-controller', $request->getControllerName());
                    $request->setControllerName('user');
                    $request->setActionName('login');
                } else {
                    $request->setControllerName(self::FAIL_AUTH_CONTROLLER);
                    $request->setActionName(self::FAIL_AUTH_ACTION);
                    $request->setParam('error_message', "Accès refusé");
                    $request->setParam('error_description', "Vous ne disposez pas des droits nécessaires pour accéder à cette page.");
                }
            } else if (Episciences_Auth::isLogged() && $resource !== 'user-edit' && $resource !== 'user-logout' && $resource !== 'user-photo') {
                //L'utilisateur a le droit d'accéder à la ressource, on vérifie si son compte est complété sur Episciences
                $epiUser = new Episciences_User();
                if (!$epiUser->hasLocalData(Episciences_Auth::getUid())) {
                    //Le compte doit être complété
                    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                    $redirector->gotoUrl('user/edit');
                }
            }
        } else if (!$request->isXmlHttpRequest()) {
            //La ressource demandée n'existe pas (pas définie dans les ACL)
            $request->setControllerName('index');
            $request->setActionName('notfound');
        }
    }

    public function getAcl()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('acl');
    }

    public function isAllowed($resource): bool
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