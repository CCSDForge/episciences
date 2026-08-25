<?php

/**
 * Access rights verification for a resource
 *
 */
class Ccsd_Auth_Plugin extends Zend_Controller_Plugin_Abstract
{

    /**
     * Controller allowing user redirection when they are not
     * authorized to access the resource
     *
     * @var string
     */
    const FAIL_AUTH_CONTROLLER = 'error';

    /**
     * Action allowing user redirection when they are not
     * authorized to access the resource
     *
     */
    const FAIL_AUTH_ACTION = 'error';

    const PAGENOTFOUND_ACTION = 'pagenotfound';
    const NODEPOSIT_ACTION = 'nodeposit';
    protected $_acl = null;

    /**
     *
     * @see Zend_Controller_Plugin_Abstract::preDispatch()
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        // Retrieve access rules
        $this->_acl = $this->getAcl();
        // Retrieve resource ID (to be modified)
        $resource = $request->getControllerName() . '-' . $request->getActionName();

        if (!$this->isAllowed($resource)) {
            $request->setControllerName(self::FAIL_AUTH_CONTROLLER);
            $request->setActionName(self::FAIL_AUTH_ACTION);
            $request->setParam('error_message', "Erreur d'authentification");
            $request->setParam('error_description', "L'accès à la ressource vous est interdit");
        }

    }

    /**
     * Method to retrieve an application's ACLs
     * To be redefined for each application
     */
    public function getAcl(): ?Ccsd_Acl
    {
        return null;
    }

    /**
     * Application-specific method to define if a resource can be accessed by a person
     * @param string $resource
     * @return boolean
     */
    public function isAllowed($resource): bool
    {
        return true;
    }

}
