<?php

use Ccsd\Auth\Adapter\AdapterInterface;

abstract class Ccsd_Auth_Adapter_CasAbstract implements AdapterInterface
{

    /**
     * Default name of the login action
     *
     * @var string
     */
    const DEFAULT_LOGIN_ACTION = 'login';

    /**
     * Default name of the logout action
     *
     * @var string
     */
    const DEFAULT_LOGOUT_ACTION = 'logout';

    /**
     * Default name of the authentication controller
     *
     * @var string
     */
    const DEFAULT_AUTH_CONTROLLER = 'user';

    /**
     * Name of the login action
     *
     * @var string
     */
    protected $_loginAction = null;

    /**
     * Name of the logout action
     *
     * @var string
     */
    protected $_logoutAction = null;

    /**
     * Name of the authentication controller
     *
     * @var string
     */
    protected $_authController = null;

    /**
     * CAS protocol version
     *
     * @var string
     */
    protected $_casVersion;

    /**
     * CAS server hostname
     *
     * @var string
     */
    protected $_casHostname;

    /**
     * CAS server port
     *
     * @var int
     */
    protected $_casPort;

    /**
     * CAS server URL
     *
     * @var string
     */
    protected $_casUrl;

    /**
     * Defines whether PhpCAS should start sessions: false if it's already managed by
     * the application
     *
     * @var bool
     */
    protected $_casStartSessions = false;

    /**
     * Defines if SSL validation of the CAS server should be performed
     *
     * @var bool
     */
    protected $_casSslValidation;

    /**
     * Path to the CA certificate
     * @var string
     */
    protected $_casCACert;

    /**
     * URL of the service for which we authenticate and to which we will return
     *
     * @var string
     */
    protected $_serviceURL = '';

    /**
     * Structure of a user's identity
     *
     * @var Ccsd_User_Models_User
     */
    protected $_identity = null;

    /**
     * @var Ccsd_User_Models_User
     */
    protected $_identityStructure = null;

    /**
     * User logout, with optional return/destination URL
     *
     * @param string $urlDeDestination
     */
    public function logout($urlDeDestination = null)
    {
        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $urlDeDestination, $this->getCasStartSessions());
        }

        if ($this->getCasSslValidation() === false) {
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }

        if (!is_string($urlDeDestination)) {
            phpCAS::logout(); // logout and stay on the CAS page
        } else {
            phpCAS::logoutWithRedirectService($urlDeDestination);
        }
    }

    // Sets the default options for the CAS server

    /**
     *
     * @return string | null $_casVersion
     */
    public function getCasVersion()
    {
        return $this->_casVersion;
    }

    /**
     * @param string $_casVersion
     * @return $this
     */
    public function setCasVersion(string $_casVersion): self
    {
        $this->_casVersion = $_casVersion;
        return $this;
    }

    /**
     *
     * @return string | null $_casHostname
     */
    public function getCasHostname()
    {
        return $this->_casHostname;
    }

    /**
     * @param string $_casHostname
     * @return $this
     */
    public function setCasHostname(string $_casHostname): self
    {
        $this->_casHostname = $_casHostname;
        return $this;
    }

    /**
     *
     * @return int $_casPort
     */
    public function getCasPort(): int
    {
        return $this->_casPort;
    }

    /**
     * @param int $_casPort
     * @return $this
     */
    public function setCasPort(int $_casPort): self
    {
        $this->_casPort = $_casPort;
        return $this;
    }

    /**
     *
     * @return string $_casUrl
     */
    public function getCasUrl(): string
    {
        return $this->_casUrl;
    }

    /**
     * @param string $_casUrl
     * @return $this
     */
    public function setCasUrl(string $_casUrl): self
    {
        $this->_casUrl = $_casUrl;
        return $this;
    }

    /**
     *
     * @return bool $_casStartSessions
     */
    public function getCasStartSessions(): bool
    {
        return $this->_casStartSessions;
    }

    /**
     * @param bool $_casStartSessions
     * @return $this
     */
    public function setCasStartSessions(bool $_casStartSessions): self
    {
        $this->_casStartSessions = (bool)$_casStartSessions;
        return $this;
    }

    /**
     *
     * @return bool $_casSslValidation
     */
    public function getCasSslValidation(): bool
    {
        return $this->_casSslValidation;
    }

    /**
     * @param bool $_casSslValidation
     * @return $this
     */
    public function setCasSslValidation(bool $_casSslValidation): self
    {
        $this->_casSslValidation = $_casSslValidation;
        return $this;
    }

    /**
     *
     * @return string $_casCACert
     */
    public function getCasCACert(): string
    {
        return $this->_casCACert;
    }

    /**
     * @param string $_casCACert
     * @return $this
     */
    public function setCasCACert(string $_casCACert): self
    {
        $this->_casCACert = $_casCACert;
        return $this;
    }

    /**
     * Authenticate a user
     * @see Zend_Auth_Adapter_Interface::authenticate()
     */
    public function authenticate(): Zend_Auth_Result
    {

        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getServiceURL(), $this->getCasStartSessions());

        }

        $this->setLogger();


        if (!$this->getCasSslValidation()) {
            // no SSL validation for the CAS server
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }
        // Return/service URL after authentication
        if ('' !== $this->getServiceURL()) {
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
     *
     * @return string $_serviceURL
     */
    public function getServiceURL(): string
    {
        return $this->_serviceURL;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setServiceURL(array $params = []): self
    {
        $_serviceURL = '';

        if (!empty($params)) {
            $_serviceURL = $this->buildLoginDestinationUrl($params);
        }

        $this->_serviceURL = $_serviceURL;

        return $this;
    }

    abstract protected function setLogger(): void;

    /**
     * @param array $params
     * @return string
     */
    protected function buildLoginDestinationUrl(array $params = []): string
    {

        $hostname = self::getCurrentHostname();

        $hostname = rtrim($hostname, '/');
        $uri = $hostname;
        $uri .= PREFIX_URL . 'user/login';
        $forwardController = $params['forward-controller'] ?? null;
        $forwardAction = $params['forward-action'] ?? null;

        // If no controller or if controller == user/logout
        if (($forwardController === null) || (($forwardController === 'user') && ($forwardAction === 'logout'))) {
            // default destination
            $uri .= '/forward-controller/user';
        } else {
            $uri .= '/forward-controller/' . urlencode($forwardController);
            if ($forwardAction) {

                $uri .= '/forward-action/' . urlencode($forwardAction);

                // Concatenation of additional parameters to the return URI
                foreach ($params as $name => $value) {
                    switch ($name) {
                        case 'forward-controller':
                        case 'forward-action':
                        case 'controller':
                        case 'action':
                        case 'module':
                        case 'ticket':
                        case PREFIX_ROUTE:
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
            }
        }
        return $uri;
    }

    /**
     * Returns the hostname that the CAS application will use
     * For redirection after login and logout
     *
     * @return string Hostname
     */
    public final static function getCurrentHostname(): string
    {
        $scheme = SERVER_PROTOCOL . '://';
        $hostname = $scheme . $_SERVER['SERVER_NAME'];

        if ((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] !== '')) {
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

    public function getIdentityStructure(): ?Ccsd_User_Models_User
    {
        return $this->_identityStructure;
    }

    /**
     * Initialization of the user identity structure
     *
     * @param $identity
     */
    public function setIdentityStructure($identity): void
    {
        // For compatibility, we set the structure in identity as well
        $this->_identity = $identity;
        $this->_identityStructure = $identity;
    }

    public function pre_auth($controller)
    {
        // TODO: Implement pre_auth() method.
    }

    public function post_auth($controller, $authinfo)
    {
        // TODO: Implement post_auth() method.
    }

    public function pre_login($array_attr)
    {
        // TODO: Implement pre_login() method.
    }

    public function post_login($loginUser, $array_attr)
    {
        // TODO: Implement post_login() method.
    }

    public function alt_login($loginUser, $array_attr): bool
    {
        return true;
    }

    abstract protected function setCasOptions(): self;

}
