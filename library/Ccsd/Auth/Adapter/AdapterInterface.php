<?php


namespace Ccsd\Auth\Adapter;

/**
 * Class AdapterInterface
 * @package Ccsd\Auth\Adapter
 */
interface AdapterInterface extends \Zend_Auth_Adapter_Interface
{
    /**
     * Processing prior to authentication with the service
     * Allows prior redirection if necessary
     * @param $controller \Zend_Controller_Action
     * @return bool
     */
    public function pre_auth($controller);

    /**
     * Processing after authentication with the service
     * Allows transmitting attributes obtained during authentication or from the
     * authentication engine
     * @param \Zend_Controller_Action $controller
     * @param \Zend_Auth_Result $authinfo
     * @return \ArrayAccess (array of attribute)
     */
    public function post_auth($controller, $authinfo);

    /**
     * Processing prior to user initialization
     * Must, from the information provided by the authentication,
     * determine an application user
     * False: if failure
     * True: if user already logged in, but logging in again for account association.
     * @param \ArrayAccess $array_attr array of attributes
     * @return \Ccsd_User_Models_User|bool
     */
    public function pre_login($array_attr);

    /**
     * Processing after user initialization
     * Allows processing of multiple auths, login mapping, ...
     * @param $loginUser \Ccsd_User_Models_User logged user
     * @param string[] $array_attr array authentication result and account association
     */
    public function post_login($loginUser, $array_attr);

    /**
     * Alternative processing allowing account association
     * @param $loginUser \Ccsd_User_Models_User logged user
     * @param $array_attr string[] array of parameters of the account to associate
     */
    public function alt_login($loginUser, $array_attr);

    /**
     * @param array $params
     * @return bool  (true if ok, false if not ok)
     * No exception must be returned
     */
    public function logout($params);

}
