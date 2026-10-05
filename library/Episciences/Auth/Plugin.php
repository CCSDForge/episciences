<?php

/**
 * Access rights verification for a resource
 *
 */
class Episciences_Auth_Plugin extends Ccsd_Auth_Plugin
{


    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        // Retrieve access rules
        $this->_acl = $this->getAcl();
        // Retrieve resource ID (to be modified)
        if (APPLICATION_MODULE == OAI) {
            return true;
        }
        $resource = $this->resolveResource($request->getControllerName(), $request->getActionName());
        if ($this->_acl->has($resource)) {
            // The requested resource exists
            if (!$this->isAllowed($resource)) {
                // The user cannot access the page
                if (!Episciences_Auth::isLogged()) {
                    // The user is not logged in
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
                // The user is allowed to access the resource, we check if their account is completed on Episciences
                $epiUser = new Episciences_User();
                if (!$epiUser->hasLocalData(Episciences_Auth::getUid())) {
                    // The account must be completed
                    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                    $redirector->gotoUrl('user/edit');
                }
            }
        } else if (!$request->isXmlHttpRequest()) {
            // The requested resource does not exist (not defined in the ACL)
            $request->setControllerName('index');
            $request->setActionName('notfound');
        }
    }

    /**
     * Normalize a controller or action name the way the dispatcher does before resolving the method
     * (lowercase, non-alphanumeric characters removed), so that every spelling reaching the same
     * action maps to the same string.
     */
    public static function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    /**
     * Return the ACL resource matching the requested controller/action.
     *
     * An exact match wins. Otherwise the names are compared in their dispatcher-normalized form, so that
     * a case or delimiter variant of a protected action is checked against the rules of that action.
     * When nothing matches, the raw "controller-action" key is returned (unknown resource).
     */
    public function resolveResource(string $controller, string $action): string
    {
        $raw = $controller . '-' . $action;
        if ($this->_acl->has($raw)) {
            return $raw;
        }

        $wanted = self::normalizeName($controller) . '-' . self::normalizeName($action);
        foreach ($this->_acl->getResources() as $candidate) {
            $parts = explode('-', (string)$candidate, 2);
            if (count($parts) === 2 && self::normalizeName($parts[0]) . '-' . self::normalizeName($parts[1]) === $wanted) {
                return (string)$candidate;
            }
        }

        return $raw;
    }

    public function getAcl(): ?Episciences_Acl
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
