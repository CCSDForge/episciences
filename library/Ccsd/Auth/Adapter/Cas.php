<?php

/**
 * Adapter Zend_Auth pour l'authentification via CAS
 *
 * @see https://wiki.jasig.org/display/CASC/phpCAS JASIG phpCAS library
 * @see https://github.com/Jasig/phpCAS
 * @author ccsd
 *
 */
class Ccsd_Auth_Adapter_Cas implements \Ccsd\Auth\Adapter\AdapterInterface
{

    /**
     * Nom par défaut de l'action pour le login
     *
     * @var string
     */
    const DEFAULT_LOGIN_ACTION = 'login';

    /**
     * Nom par défaut de l'action pour le logout
     *
     * @var string
     */
    const DEFAULT_LOGOUT_ACTION = 'logout';

    /**
     * Nom par défaut du controller d'authentification
     *
     * @var string
     */
    const DEFAULT_AUTH_CONTROLLER = 'user';

    /**
     * Nom de l'action pour le login
     *
     * @var string
     */
    protected $_loginAction = null;

    /**
     * Nom de l'action pour le logout
     *
     * @var string
     */
    protected $_logoutAction = null;

    /**
     * Nom du controller d'authentification
     *
     * @var string
     */
    protected $_authController = null;

    /**
     * Version du protocole CAS
     *
     * @var string
     */
    protected $_casVersion;

    /**
     * Nom d'hôte du serveur CAS
     *
     * @var string
     */
    protected $_casHostname;

    /**
     * Port serveur CAS
     *
     * @var int
     */
    protected $_casPort;

    /**
     * URL du serveur CAS
     *
     * @var string
     */
    protected $_casUrl;

    /**
     * Définit si PhpCAS doit démarrer les sessions : non si c'est déjà géré par
     * l'application
     *
     * @var string
     */
    protected $_casStartSessions;

    /**
     * Définit si on doit faire la validation SSL du serveur CAS
     *
     * @var bool
     */
    protected $_casSslValidation;

    /**
     * Chemin vers le certificat de l'autorité de certification
     * @var string
     */
    protected $_casCACert;

    /**
     * URL du service pour lequel on s'authentifie * et sur lequel on reviendra
     * *
     *
     * @var string
     */
    protected $_serviceURL;

    /**
     * Structure de l'identité d'un utilisateur
     *
     * @var Ccsd_User_Models_User
     */
    protected $_identity = null;

    /**
     * @var Ccsd_User_Models_User
     */
    protected $_identityStructure = null;


    /**
     * Ccsd_Auth_Adapter_Cas constructor.
     */
    public function __construct()
    {
        $this->setCasOptions();
    }

    /**
     * Définit les options par défaut du serveur CAS
     * @return Ccsd_Auth_Adapter_Cas
     */
    private function setCasOptions()
    {

        $this->setCasVersion(CAS_SERVICE_VERSION)
            ->setCasHostname(CAS_SERVICE_HOST)
            ->setCasPort(CAS_SERVICE_PORT)
            ->setCasUrl(CAS_SERVICE_URL)
            ->setCasStartSessions(false)
            ->setCasSslValidation(CAS_SERVICE_SSLVALIDATION)
            ->setCasCACert(CAS_SERVICE_SSLCACERT);
        return $this;
    }

    /**
     * Retourne le nom d'hôte que l'application CAS va utiliser
     * Pour redirection après login et logout
     *
     * @return string Nom de l'hôte
     */
    static function getCurrentHostname()
    {
        if ((isset($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] != '')) {
            $scheme = 'https://';
        } else {
            $scheme = 'http://';
        }

            $hostname = $scheme . $_SERVER['SERVER_NAME'];


        if ((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] != '')) {
            switch ($_SERVER['SERVER_PORT']) {
                case '443':
                case '':
                case '80':
                    break;
                default:
                    $hostname .= ":" . $_SERVER['SERVER_PORT'];
                    break;
            }
        }

        return $hostname;
    }

    /**
     * @return Ccsd_User_Models_User
     */
    public function getIdentityStructure()
    {
        return $this->_identityStructure;
    }

    /**
     * Initialisation de la structure de l'identité utilisateur
     *
     * @param $identity
     */
    public function setIdentityStructure($identity)
    {
        // Par compat, on met la structure dans identity aussi
        $this->_identity = $identity;
        $this->_identityStructure = $identity;
    }

    /**
     * Nouvelle méthode d'authentification
     * @see Zend_Auth_Adapter_Interface::authenticate()
     */

    public function authenticate2()
    {

        //initialisation du connecteur CAS
        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getCasStartSessions());
        }


        if (defined('CAS_SERVICE_LOG_PATH')) {
            if (CAS_SERVICE_LOG_PATH !== '') {
                phpCAS::setDebug(CAS_SERVICE_LOG_PATH);
            } else {
                phpCAS::setDebug(realpath(sys_get_temp_dir()) . '/cas.log');
            }
        }

        if ($this->getCasSslValidation() === false) {
            // no SSL validation for the CAS server
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }
        // Url de retour/service après authentification
        if (null !== $this->getServiceURL()) {
            phpCAS::setFixedServiceURL($this->getServiceURL());
        }

        // force CAS authentication
        try {
            $resultOfAuth = phpCAS::forceAuthentication();
        } catch (Exception $e) {
            $resultOfAuth = false;
        }

        if ($resultOfAuth) {
            // retour des informations d'authentification
            $uid = phpCAS::getAttribute('UID');
            return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $uid, []);
        }

        return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, new Ccsd_User_Models_User(), ["Échec de l'authentification depuis CAS"]);

    }

    /**
     *
     * @return string $_casVersion
     */
    public function getCasVersion()
    {
        return $this->_casVersion;
    }

    /**
     *
     * @param string $_casVersion
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasVersion($_casVersion)
    {
        $this->_casVersion = $_casVersion;
        return $this;
    }

    /**
     *
     * @return string $_casHostname
     */
    public function getCasHostname()
    {
        return $this->_casHostname;
    }

    /**
     *
     * @param string $_casHostname
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasHostname($_casHostname)
    {
        $this->_casHostname = $_casHostname;
        return $this;
    }

    /**
     *
     * @return string $_casPort
     */
    public function getCasPort()
    {
        return $this->_casPort;
    }

    /**
     *
     * @param string $_casPort
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasPort($_casPort)
    {
        $this->_casPort = (int)$_casPort;
        return $this;
    }

    /**
     *
     * @return string $_casUrl
     */
    public function getCasUrl()
    {
        return $this->_casUrl;
    }

    /**
     *
     * @param string $_casUrl
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasUrl($_casUrl)
    {
        $this->_casUrl = $_casUrl;
        return $this;
    }

    /**
     *
     * @return string $_casStartSessions
     */
    public function getCasStartSessions()
    {
        return $this->_casStartSessions;
    }

    /**
     *
     * @param bool $_casStartSessions
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasStartSessions($_casStartSessions = false)
    {
        $this->_casStartSessions = (bool)$_casStartSessions;
        return $this;
    }

    /**
     *
     * @return bool $_casSslValidation
     */
    public function getCasSslValidation()
    {
        return $this->_casSslValidation;
    }

    /**
     *
     * @param bool $_casSslValidation
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasSslValidation($_casSslValidation)
    {
        $this->_casSslValidation = $_casSslValidation;
        return $this;
    }

    /**
     *
     * @return string $_casCACert
     */
    public function getCasCACert()
    {
        return $this->_casCACert;
    }

    /**
     *
     * @param string $_casCACert
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasCACert($_casCACert)
    {
        $this->_casCACert = $_casCACert;
        return $this;
    }

    /**
     *
     * @return string $_serviceURL
     */
    public function getServiceURL()
    {
        return $this->_serviceURL;
    }

    /**
     *
     * @param string[] $params
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setServiceURL($params = [])
    {
        $_serviceURL = $this->buildLoginDestinationUrl($params);

        if (isset($_serviceURL)) {
            $this->_serviceURL = $_serviceURL;
        }

        return $this;
    }

    /**
     * Authentification d'un utilisateur
     * @see Zend_Auth_Adapter_Interface::authenticate()
     */
    public function authenticate()
    {

        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getCasStartSessions());
        }

        if (defined('CAS_SERVICE_LOG_PATH')) {
            if (CAS_SERVICE_LOG_PATH !== '') {
                phpCAS::setDebug(CAS_SERVICE_LOG_PATH);
            } else {
                phpCAS::setDebug(realpath(sys_get_temp_dir()) . '/cas.log');
            }
        }

        if (!$this->getCasSslValidation()) {
            // no SSL validation for the CAS server
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }
        // Url de retour/service après authentification
        if (null != $this->getServiceURL()) {
            phpCAS::setFixedServiceURL($this->getServiceURL());
        }


        try {
           $userLanguage = Zend_Registry::get('lang');
        } catch (Zend_Exception $exception) {
            $userLanguage = Episciences_Translation_Plugin::LANG_EN;
        }

        // force CAS authentication
        try {
            phpCAS::setServerLoginURL(phpCAS::getServerLoginURL() . '&locale=' . $userLanguage);
            $resultOfAuth = phpCAS::forceAuthentication();
        } catch (Exception $e) {
            $resultOfAuth = false;
        }

        if ($resultOfAuth) {

            if ($this->_identity instanceof Ccsd_User_Models_User) {
                $user = $this->_identity;
            } else {
                $user = new Ccsd_User_Models_User();
            }
            /**
             * These attributes must be sent by CAS server
             */
            $user->setEmail(phpCAS::getAttribute('EMAIL'));
            $user->setUid(phpCAS::getAttribute('UID'));
            $user->setFirstname(phpCAS::getAttribute('FIRSTNAME'));
            $user->setLastname(phpCAS::getAttribute('LASTNAME'));

            // at this step, the user has been authenticated by the CAS server
            // and the user's login name can be read with phpCAS::getUser().

            return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $user, []);
        }

        return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, new Ccsd_User_Models_User(), ["Échec de l'authentification depuis CAS"]);
    }

    /**
     * Déconnexion de l'utilisateur, avec URL de retour/destination facultative
     *
     * @param string $urlDeDestination
     *            URL de retour/destination
     */
    public function logout($urlDeDestination = null)
    {
        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getCasStartSessions());
        }

        if ($this->getCasSslValidation() === false) {
            // no SSL validation for the CAS server
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }

        if (null == $urlDeDestination) {
            phpCAS::logout(); // logout et reste sur la page CAS
        } else {
            phpCAS::logoutWithRedirectService($urlDeDestination);
        }
    }

    /**
     *
     * @return string $_loginAction
     */
    public function getLoginAction()
    {
        if ($this->_loginAction == null) {
            return self::DEFAULT_LOGIN_ACTION;
        }
        return $this->_loginAction;
    }

    /**
     *
     * @param string $_loginAction
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setLoginAction($_loginAction)
    {
        $this->_loginAction = $_loginAction;
        return $this;
    }

    /**
     *
     * @return string $_logoutAction
     */
    public function getLogoutAction()
    {
        if ($this->_logoutAction == null) {
            return self::DEFAULT_LOGOUT_ACTION;
        }
        return $this->_logoutAction;
    }

    /**
     *
     * @param string $_logoutAction
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setLogoutAction($_logoutAction)
    {
        $this->_logoutAction = $_logoutAction;
        return $this;
    }

    /**
     *
     * @return string $_authController
     */
    public function getAuthController()
    {
        if ($this->_authController == null) {
            return self::DEFAULT_AUTH_CONTROLLER;
        }
        return $this->_authController;
    }

    /**
     *
     * @param string $_authController
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setAuthController($_authController)
    {
        $this->_authController = $_authController;
        return $this;
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le préalable à l'authentification de l'utilisateur
     * @param Hal_Controller_Action $controller
     */
    public function pre_auth($controller)
    {
        $halUser = new Hal_User ();
        /** @var  $request */
        $request = $controller->getRequest();
        $authAdapter = new Ccsd_Auth_Adapter_Cas ();
        $authAdapter->setIdentityStructure($halUser);
        $authAdapter->setServiceURL($request->getParams());
        return true;
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le postérieur à l'authentification de l'utilisateur
     * recuperation d'attributs par exemple
     * @param \Zend_Controller_Action $controller
     * @param Zend_Auth_Result $authinfo
     * @return ArrayAccess (array of attribute)
     */
    public function post_auth($controller, $authinfo)
    {
        $userMapper = new Ccsd_User_Models_UserMapper();
        $attrs = $userMapper->find(phpCAS::getAttribute('UID'))->toArray();
        return $attrs;
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le préalable à l'identification et instanciation de l'utilisateur
     * Cherche le user correspondant dans l'application locale
     * @param ArrayAccess $attrs
     * @return string
     */
    public function pre_login($attrs)
    {
        if (isset($attrs['UID'])) {
            $user = new \Ccsd_User_Models_User();
            $refUser = new \Ccsd_User_Models_UserMapper();
            $refUser->find($attrs['UID'], $user);
            return $user;
        } else {
            Ccsd_Tools::panicMsg(__FILE__, __LINE__, 'Authenticated user without username Attribute...');
            return null;
        }
    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le postérieur à l'identification et instanciation de l'utilisateur
     * @param \Ccsd_User_Models_User $user
     * @param array $attrs
     */
    public function post_login($user, $attrs)
    {
    }

    /**
     * @param \Ccsd_User_Models_User $loginUser
     * @param ArrayAccess $array_attr
     * @return bool
     */
    public function alt_login($loginUser, $array_attr)
    {
        return true;
    }

    /**
     * fonction permettant de forcer la creation d'un compte utilisateur
     * à partir des informations du fournisseur d'identité
     * @param array $array_attr tableau d'informations fournies par le fournisseur d'identité
     * @param boolean $forceCreate
     * @return bool
     */
    public function createUserFromAdapter($array_attr, $forceCreate)
    {
        return false;
    }

    /**
     * @param array $params
     * @return bool|string
     */
    private function buildLoginDestinationUrl($params = [])
    {
        if (empty($params)) {
            return null;
        }

        $hostname = self::getCurrentHostname();

        // si defined('PREFIX_URL') de HAL, eg '/LKB/'
        $hostname = rtrim($hostname, '/');

        $uri = $hostname . '/user/login';
        $forwardController = null;
        if (array_key_exists('forward-controller', $params)) {
            $forwardController = $params['forward-controller'];
        }
        $forwardAction = null;
        if (array_key_exists('forward-action', $params)) {
            $forwardAction = $params['forward-action'];
        }

        // Si pas de controller ou si controller == user/logout
        if (($forwardController == null) || (($forwardController == 'user') && ($forwardAction == 'logout'))) {
            // destination par défaut
            $uri .= '/forward-controller/user';
        } else {
            if ($forwardAction) {

                $uri .= '/forward-controller/' . urlencode($forwardController);
                $uri .= '/forward-action/' . urlencode($forwardAction);

                // Concaténation des paramètres supplémentaires à l'uri de retour
                foreach ($params as $name => $value) {
                    switch ($name) {
                        case 'forward-controller':
                        case 'forward-action':
                        case 'controller':
                        case 'action':
                        case 'module':
                        case 'ticket':
                            continue 2;
                        default:
                            $uri .= '/' . urlencode($name) . '/';

                            if (is_array($value)) {
                                $uri .= urlencode($value[0]);
                            } else {
                                $uri .= urlencode($value);
                            }
                    }
                }
            } else {
                $uri .= '/forward-controller/' . urlencode($forwardController);
            }
        }
        return $uri;
    }
}
