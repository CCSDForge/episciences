<?php

/**
 * MySQL Authentication Adapter for Episciences
 * Authenticates users against the CAS database T_UTILISATEURS table
 * using SHA2-512 password hashing without requiring JASIG CAS server
 *
 * @author CCSD
 */

namespace Ccsd\Auth\Adapter;

/**
 * Class Mysql
 * @package Ccsd\Auth\Adapter
 */
class Mysql implements AdapterInterface
{
    const ADAPTER_NAME = 'MYSQL';

    /**
     * User identity structure
     * @var \Ccsd_User_Models_User
     */
    protected $_identity = null;

    /**
     * User identity structure (compatibility with CAS adapter)
     * @var \Ccsd_User_Models_User
     */
    protected $_identityStructure = null;

    /**
     * Username for authentication
     * @var string
     */
    protected $_username = null;

    /**
     * Password for authentication (plain text, will be hashed in SQL)
     * @var string
     */
    protected $_password = null;

    /**
     * Authenticates user against CAS database
     *
     * @return \Zend_Auth_Result
     */
    public function authenticate()
    {
        // Get CAS database adapter
        $casDb = \Ccsd_Db_Adapter_Cas::getAdapter();

        // Prepare SQL query with SHA2-512 hashing
        // Note: Cannot use placeholder inside SHA2() function, must use quote()
        $select = $casDb->select()
            ->from('T_UTILISATEURS', [
                'UID', 'USERNAME', 'EMAIL', 'CIV',
                'LASTNAME', 'FIRSTNAME', 'MIDDLENAME',
                'TIME_REGISTERED', 'TIME_MODIFIED', 'VALID'
            ])
            ->where('USERNAME = ?', $this->_username)
            ->where('PASSWORD = SHA2(' . $casDb->quote($this->_password) . ', 512)')
            ->where('VALID = 1');

        // Execute query
        $row = $casDb->fetchRow($select);

        // Check result
        if (!$row) {
            return new \Zend_Auth_Result(
                \Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                null,
                ['Invalid credentials or account not validated']
            );
        }

        // Create user object with CAS data
        // Use identity structure if set (Episciences_User), otherwise create new Episciences_User
        if ($this->_identityStructure instanceof \Ccsd_User_Models_User) {
            $user = $this->_identityStructure;
        } else {
            $user = new \Episciences_User();
        }
        $user->setUid($row['UID'])
            ->setUsername($row['USERNAME'])
            ->setEmail($row['EMAIL'])
            ->setCiv($row['CIV'])
            ->setLastname($row['LASTNAME'])
            ->setFirstname($row['FIRSTNAME'])
            ->setMiddlename($row['MIDDLENAME'])
            ->setTime_registered($row['TIME_REGISTERED'])
            ->setTime_modified($row['TIME_MODIFIED'])
            ->setValid($row['VALID']);

        // Store identity
        $this->_identity = $user;

        // Return success with user object
        return new \Zend_Auth_Result(
            \Zend_Auth_Result::SUCCESS,
            $user,
            []
        );
    }

    /**
     * Pre-authentication processing
     * Displays login form if credentials are not provided
     *
     * @param \Zend_Controller_Action $controller
     * @return bool
     */
    public function pre_auth($controller)
    {
        $request = $controller->getRequest();
        $params = $request->getParams();

        // Check if credentials are provided and not empty
        if (!isset($params['username']) || !isset($params['password']) ||
            empty(trim($params['username'])) || empty(trim($params['password']))) {
            // Display login form with user icon (not envelope)
            $form = new \Ccsd_User_Form_Login();
            $form->setAction($controller->view->url());
            $form->setActions(true)->createSubmitButton("Connexion");
            $controller->view->form = $form;
            $controller->renderScript('user/login.phtml');
            return false;
        }

        // Store credentials for authentication
        $this->setUsername($params['username']);
        $this->setCredential($params['password']);
        return true;
    }

    /**
     * Post-authentication processing
     * Returns user object from authentication result
     *
     * @param \Zend_Controller_Action $controller
     * @param \Zend_Auth_Result $authinfo
     * @return \Ccsd_User_Models_User
     */
    public function post_auth($controller, $authinfo)
    {
        // Return user object from authentication result
        // It already contains CAS database data
        return $authinfo->getIdentity();
    }

    /**
     * Pre-login processing
     * Prepares user object for session creation
     *
     * @param \ArrayAccess $array_attr
     * @return \Ccsd_User_Models_User
     */
    public function pre_login($array_attr)
    {
        // $array_attr is the User object returned by post_auth
        // Return directly to allow session creation
        return $array_attr;
    }

    /**
     * Post-login processing
     * Executed after session creation
     *
     * @param \Ccsd_User_Models_User $loginUser
     * @param string[] $array_attr
     */
    public function post_login($loginUser, $array_attr)
    {
        // Nothing to do - synchronization is handled by controller
    }

    /**
     * Alternative login processing
     * For account association
     *
     * @param \Ccsd_User_Models_User $loginUser
     * @param string[] $array_attr
     * @return bool
     */
    public function alt_login($loginUser, $array_attr)
    {
        return true;
    }

    /**
     * Logout processing
     * Clears session and identity
     *
     * @param array $params
     * @return bool
     */
    public function logout($params)
    {
        \Zend_Auth::getInstance()->clearIdentity();
        \Zend_Session::destroy();
        return true;
    }

    /**
     * Set user identity
     *
     * @param \Ccsd_User_Models_User $_identity
     * @return $this
     */
    public function setIdentity($_identity)
    {
        $this->_identity = $_identity;
        return $this;
    }

    /**
     * Get user identity
     *
     * @return \Ccsd_User_Models_User
     */
    public function getIdentity()
    {
        return $this->_identity;
    }

    /**
     * Set identity structure (compatibility with CAS adapter)
     *
     * @param \Ccsd_User_Models_User $identity
     * @return $this
     */
    public function setIdentityStructure($identity)
    {
        $this->_identity = $identity;
        $this->_identityStructure = $identity;
        return $this;
    }

    /**
     * Get identity structure
     *
     * @return \Ccsd_User_Models_User
     */
    public function getIdentityStructure()
    {
        return $this->_identityStructure;
    }

    /**
     * Set username for authentication
     *
     * @param string $username
     * @return $this
     * @throws \Zend_Auth_Exception
     */
    protected function setUsername($username)
    {
        if (empty($username)) {
            throw new \Zend_Auth_Exception(
                "No username provided",
                \Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND
            );
        }
        $this->_username = $username;
        return $this;
    }

    /**
     * Set password for authentication
     *
     * @param string $password
     * @return $this
     * @throws \Zend_Auth_Exception
     */
    protected function setCredential($password)
    {
        if (empty($password)) {
            throw new \Zend_Auth_Exception(
                "No password provided",
                \Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID
            );
        }
        $this->_password = $password;
        return $this;
    }

    /**
     * Set service URL (compatibility with CAS adapter)
     *
     * @param array $params
     * @return $this
     */
    public function setServiceURL($params)
    {
        // No-op for MySQL adapter (CAS compatibility)
        return $this;
    }

    /**
     * Create user from adapter
     * Not implemented for MySQL adapter
     *
     * @param array $array_attr
     * @param boolean $forceCreate
     * @return bool
     */
    public function createUserFromAdapter($array_attr, $forceCreate)
    {
        return false;
    }

    /**
     * Convert to HTML representation
     *
     * @param \ArrayAccess $attr
     * @return string
     */
    public function toHtml($attr)
    {
        return self::ADAPTER_NAME;
    }
}
