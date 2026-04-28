<?php

/**
 * Vérifications des droits d'accès à une ressource
 *
 */
class Ccsd_Acl_Plugin extends Zend_Controller_Plugin_Abstract
{

    /**
     * Controller permettant la redirection de l'utilisateur lorsqu'il n'est pas
     * autorisé à accéder à la ressource
     *
     * @var string
     */
    const FAIL_AUTH_CONTROLLER = 'error';

    /**
     * Action permettant la redirection de l'utilisateur lorsqu'il n'est pas
     * autorisé à accéder à la ressource
     *
     * @var unknown_type
     */
    const FAIL_AUTH_ACTION = 'error';
    
    const PAGENOTFOUND_ACTION = 'pagenotfound';

    protected $_acl = null;
    
    /**
     *
     * @see Zend_Controller_Plugin_Abstract::preDispatch()
     */
    public function preDispatch (Zend_Controller_Request_Abstract $request)
    {
        // Récupération des règles d'accès
    	$this->_acl = $this->getAcl();
        // Récupération de l'id de la ressource (à modifier)
        $resource = $request->getControllerName() . '-' . $request->getActionName();
        
        if (! $this->isAllowed($resource)) {
            $request->setControllerName(self::FAIL_AUTH_CONTROLLER);
            $request->setActionName(self::FAIL_AUTH_ACTION);
            $request->setParam('error_message', "Erreur d'autorisation");
            $request->setParam('error_description', "L'accès à la ressource n'est pas autorisé");
        }

    }
    
    /**
     * Méthode permettant de récupérer les ACL d'une application
     * A redéfinir pour chaque application
     * @return multitype:
     */
    public function getAcl()
    {
    	return array();
    }
    
    /**
     * Méthode spécifique par application pour définir si une ressource peut être consultée par une personne
     * @param string $resource
     * @return boolean
     */
    public function isAllowed($resource)
    {
        return true;
    }
    
}