<?php

/**
 * Adapter Zend_Auth pour l'authentification via Mysql
 *
 * @author ccsd
 *
 */
class Ccsd_Auth_Adapter_Mysql  implements Zend_Auth_Adapter_Interface
{

    /**
     * Structure de l'identité d'un utilisateur
     *
     * @var Ccsd_User_Models_User
     */
    protected $_identity = null;

    /**
     *
     * @var string
     */
    protected $_password = null;

    /**
     *
     * @var string
     */
    protected $_username = null;

    public function __construct ($username, $password)
    {
        $this->setPassword($password);
        $this->setUsername($username);
    }

    /*
     * Authentification d'un utilisateur @see
     * Zend_Auth_Adapter_Interface::authenticate()
     */
    public function authenticate ()
    {
        $userMapper = new Ccsd_User_Models_UserMapper();

        $userResult = $userMapper->findByUsernamePassword($this->getUsername(), $this->getPassword());

        if ($userResult == null) {
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, null, ['HTTP/1.1 401 Unauthorized']);
        }

        if ($userResult->UID != '') {
            if ($this->_identity instanceof Ccsd_User_Models_User) {
                $user = $this->_identity;
            } else {
                $user = new Ccsd_User_Models_User($userResult->toArray());
                $this->setIdentity($user);
            }
            $user->setOptions($userResult->toArray());
            return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $user);
        } else {
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, null, null);
        }
    }

    /**
     *
     * @return the $_password
     */
    public function getPassword ()
    {
        return $this->_password;
    }

    /**
     *
     * @param field_type $_password
     */
    public function setPassword ($_password)
    {
        if ($_password == null) {
            throw new Zend_Auth_Exception("No password", Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID);
        }
        $this->_password = $_password;
        return $this;
    }

    /**
     *
     * @return the $_username
     */
    public function getUsername ()
    {
        return $this->_username;
    }

    /**
     *
     * @param field_type $_username
     */
    public function setUsername ($_username = null)
    {
        if ($_username == null) {
            throw new Zend_Auth_Exception("No username", Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID);
        }
        $this->_username = $_username;
        return $this;
    }

    /**
     *
     * @return the $_identity
     */
    public function getIdentity ()
    {
        return $this->_identity;
    }

    /**
     *
     * @param Ccsd_User_Models_User $_identity
     */
    public function setIdentity ($_identity)
    {
        $this->_identity = $_identity;
        return $this;
    }


    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le préalable à l'identification et instanciation de l'utilisateur
     */

    public function pre_login()
    {
        parent::pre_login();
    }


    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le postérieur à l'identification et instanciation de l'utilisateur
     */
    public function post_login()
    {
        parent::post_login();
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



