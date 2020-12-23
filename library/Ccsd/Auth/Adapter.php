<?php


/**
 * Adapter générique parent aux adapter CAS,database, et fédération (idp)
 *
 * @author ccsd
 *
 */

abstract class Ccsd_Auth_Adapter  implements Zend_Auth_Adapter_Interface {


    /**
     * Liste des authentifications autorisées
     *
     * @var array
     */
    protected $_accepted_auth_list = ['DB' ,'CAS','IDP'];



    /**
     * Traitements préalables à l'authentification auprès du service
     * @param $controller Zend_Controller_Action
     */
    public function pre_auth($controller){

    }

    /**
     * Traitements postérieurs à l'authentification auprès du service
     *
     */
    
    public function post_auth(){
        
    }

    /**
     * Traitements préalables à l'initialisation de l'utilisateur
     * @param \Ccsd_User_Models_User | NULL User object Ccsd
     */
    public function pre_login($ccsdUser){

    }

    /**
     * Traitements postérieurs à l'initialisation de l'utilisateur
     * @param $loginUser \Ccsd_User_Models_User logged user
     * @param $array_attr array authentication result and account association
     */
    public function post_login($loginUser,$array_attr){
        
    }

    abstract public function authenticate();

    static public function getTypedAdapter($authType) {
        switch ($authType)
        {
            case 'DB': $authAdapter = new Hal_Auth_Adapter_DbTable();
                break;
            case 'CAS' : $authAdapter = new Ccsd_Auth_Adapter_Cas();
                break;
            case 'IDP': $authAdapter = new Ccsd_Auth_Adapter_Idp();
                break;
            case 'ORCID': $authAdapter = new Ccsd_Auth_Adapter_Orcid();
                break;

            default : $authAdapter = new Ccsd_Auth_Adapter_Cas();
        }

        return $authAdapter;
    }
}