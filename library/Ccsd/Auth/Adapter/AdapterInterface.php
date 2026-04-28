<?php


namespace Ccsd\Auth\Adapter;

/**
 * Class AdapterInterface
 * @package Ccsd\Auth\Adapter
 */
interface AdapterInterface extends \Zend_Auth_Adapter_Interface
{
    /**
     * Traitements préalables à l'authentification auprès du service
     * Permet la redirection prealable si necessaire
     * @param $controller \Zend_Controller_Action
     * @return bool
     */
    public function pre_auth($controller);
    /**
     * Traitements postérieurs à l'authentification auprès du service
     * Permet de transmettre des attributs obtenus lors de l'authentification ou a partir du
     * moteur d'authentification
     * @param \Zend_Controller_Action $controller
     * @param \Zend_Auth_Result $authinfo
     * @return \ArrayAccess (array of attribute)
     */
    public function post_auth($controller, $authinfo);
    /**
     * Traitements préalables à l'initialisation de l'utilisateur
     * Doit, a partir des informations fournies par l'authentification,
     * determiner un user applicatif
     * False: si echec
     * True: si user deja logue, mais login de nouveau pour association de compte.
     * @param \ArrayAccess $array_attr  array of attributs
     * @return \Ccsd_User_Models_User|bool
     */
    public function pre_login($array_attr);
    /**
     * Traitements postérieurs à l'initialisation de l'utilisateur
     * Permet les traitement de plusieurs auth , mapping de login, ...
     * @param $loginUser \Ccsd_User_Models_User logged user
     * @param string[] $array_attr  array authentication result and account association
     */
    public function post_login($loginUser,$array_attr);
    /**
     * Traitement alternatif permettant l'association de compte
     * @param $loginUser \Ccsd_User_Models_User logged user
     * @param $array_attr string[] tableau de paramètres du compte à associer
     */
    public function alt_login($loginUser,$array_attr);
    /**
     * @param array $params
     * @return bool  (true if ok, false if not ok)
     * No exception must be returned
     */
    public function logout($params) ;

}