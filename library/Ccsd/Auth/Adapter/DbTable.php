<?php

/**
 * Adapter Zend_Auth pour l'authentification via Mysql
 *
 * @author ccsd
 *
 */

/** Authentification par delegation a un Zend_Auth_adapter
 *
 */
namespace Ccsd\Auth\Adapter;

/**
 * Class DbTable
 * @package Ccsd\Auth\Adapter
 */
class DbTable extends \Zend_Auth_Adapter_DbTable implements AdapterInterface {


    const AdapterName = 'DB';
    /**
     *
     */
    public function __construct()
    {
        $dbAdapter = \Ccsd\Db\Adapter\DbTable::getAdapter();
        parent::__construct($dbAdapter, 'T_UTILISATEURS', 'USERNAME', 'PASSWORD', 'SHA2(?, 512) AND VALID=1');
    }

    /**
     * Permet de passer l'objet Hal_User a l'authentification pour récupérer l'ensemble des informations de l'utilisateur
     * @return \Zend_Auth_Result
     * @throws \Zend_Auth_Adapter_Exception
     */
    public function authenticate()
    {
        $authResult = parent::authenticate();

        return $authResult;
    }


    /**
     * Fonction d'authentification version 2
     * avec non plus retour d'un objet Hal User mais retour d'un Ccsd User
     * permettant par la suite de construire l'objet Hal User
     * (redécoupage des processus 13/02/2019 - JB)
     * @throws \Zend_Auth_Adapter_Exception
     */

    public function authenticate2()
    {
        $authResult = $this->authenticate();
        return $authResult;
    }


    /**
     * before authentication
     * @param \Hal_Controller_Action $controller
     * @return bool
     */
    public function pre_auth($controller){
        $request = $controller->getRequest();
        $params = $request->getParams();
        if (!isset($params['username'])) {
            # Il faut renvoyer vers le formulaire de login
            $form = new \Ccsd_User_Form_Accountlogin();
            $form->setAction($controller->view->url());
            $form->setActions(true)->createSubmitButton("Connexion");
            $controller->view->form = $form;
            $controller->renderScript('user/login.phtml');
            return false;
        } else {
            $login    = $params['username'];
            $password = $params['password'];
            $this->setIdentity($login)->setCredential($password);
            return true;
        }
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter (A voir 13/02/2019 - JB)
     * organise le postérieur à l'authentification
     *
     * Recuperation des informations suite a authentification
     * @param \Zend_Controller_Action $controller
     * @param \Zend_Auth_Result $authinfo
     * @return \ArrayAccess :  (array of attribute)
     * @throws \Zend_Auth_Adapter_Exception
     */
    public function post_auth($controller, $authinfo)
    {
        //$userMapper = new Ccsd_User_Models_UserMapper();
        //$username = $authinfo->getIdentity();
        //$rows = $userMapper->findByUsername($username);
        //if (count($rows) > 1) {
        //   throw new Zend_Auth_Adapter_Exception('Deux username ($username) present dans la table!');
        //}
        //$row = $rows[0];
        //return $row;

        // Si le resultat est Ok, on change le type de identity dans le resultat pour faire que ce soit un User applicatif

        // Hum: dans preLogin ???
        $userMapper = new \Ccsd_User_Models_UserMapper();
        $username = $authinfo->getIdentity();
        $rows = $userMapper->findByUsername($username);
        if (count($rows) > 1) {
            throw new \Zend_Auth_Adapter_Exception('Deux utilisateurs ($username) presents dans la table!');
        }
        $row = $rows[0];
        return $row;
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter (A voir 13/02/2019 - JB)
     * organise le préalable à l'identification et instanciation de l'utilisateur
     * @param \ArrayAccess $array_attr  array of attribute
     * @return \Ccsd_User_Models_User
     */

    public function pre_login($array_attr)
    {
        $user = new \Hal_User();
        $user->setUid($array_attr['UID'])
            ->setUsername($array_attr['USERNAME'])
            ->setEmail($array_attr['EMAIL'])
            ->setCiv($array_attr['CIV'])
            ->setLastname($array_attr['LASTNAME'])
            ->setFirstname($array_attr['FIRSTNAME'])
            ->setMiddlename($array_attr['MIDDLENAME']);
        $user->setTime_registered($array_attr['TIME_REGISTERED'])
            ->setTime_modified($array_attr['TIME_MODIFIED'])
            ->setValid($array_attr['VALID']);

        return $user;
    }

    /**
     * @param \Ccsd_User_Models_User $loginUser
     * @param \ArrayAccess $array_attr
     * @return bool
     */
    public function alt_login($loginUser, $array_attr)
    {
        return true;
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter (A voir 13/02/2019 - JB)
     * organise le postérieur à l'identification et instanciation de l'utilisateur
     * @param \Ccsd_User_Models_User $loginUser
     * @param string[] $array_attr
     */
    public function post_login($loginUser,$array_attr)
    {
    }
    /**
     * @param string $foobar unused parameter
     */
    public function logout($foobar) {
        \Zend_Auth::getInstance()->clearIdentity();
        \Zend_Session::destroy();
    }

    /**
     * @param \ArrayAccess $attr
     * @return string
     */
    public function toHtml($attr) {
        return self::AdapterName;
    }

    /**
     * fonction permettant de forcer la creation d'un compte utilisateur
     * à partir des informations du fournisseur d'identité
     * @param array $array_attr tableau d'informations fournies par le fournisseur d'identité
     * @param boolean $forceCreate
     * @return bool
     */
    public function createUserFromAdapter($array_attr,$forceCreate)
    {
        return false;
    }
}
