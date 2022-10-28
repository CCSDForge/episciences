<?php
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Adapter Zend_Auth for authentication via LemonLDAP using the CAS protocol
 *
 * @see https://wiki.jasig.org/display/CASC/phpCAS JASIG phpCAS library
 * @see https://github.com/Jasig/phpCAS
 * @author ccsd
 *
 */
class Episciences_Auth_Adapter_LmLDAP_Protocol_Cas implements \Ccsd\Auth\Adapter\AdapterInterface
{

    /**
     * Default name of the action for the login
     *
     * @var string
     */
    const DEFAULT_LOGIN_ACTION = 'login';

    /**
     * Default name of the action for the logout
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
     * Name of the action for the login
     *
     * @var string|null
     */
    protected ?string $_loginAction = null;

    /**
     *  Name of the action for the ogout
     *
     * @var string|null
     */
    protected ?string $_logoutAction = null;

    /**
     * Name of the authentication controller
     *
     * @var string|null
     */
    protected ?string $_authController = null;

    /**
     * CAS protocol version
     *
     * @var string
     */
    protected string $_casVersion;

    /**
     * Host name of the CAS server
     *
     * @var string
     */
    protected string $_casHostname;

    /**
     * CAS server port
     *
     * @var int
     */
    protected int $_casPort;

    /**
     *  CAS server URL
     *
     * @var string
     */
    protected string $_casUrl;

    /**
     * Defines if PhpCAS should start the sessions : no if it is already managed by
     * the application
     *
     * @var bool
     */
    protected bool $_casStartSessions = false;

    /**
     * Defines whether to perform SSL validation of the CAS server
     *
     * @var bool
     */
    protected bool $_casSslValidation;

    /**
     * Path to the certificate of the certification authority
     * @var string
     */
    protected string $_casCACert;

    /**
     * URL of the service for which you authenticate and on which you will return
     *
     * @var string
     */
    protected string $_serviceURL = '';

    /**
     * Identity structure of a user
     *
     * @var Ccsd_User_Models_User|null
     */
    protected ?Ccsd_User_Models_User $_identity = null;

    /**
     * @var Ccsd_User_Models_User|null
     */
    protected ?Ccsd_User_Models_User $_identityStructure = null;


    /**
     * Episciences_Auth_Adapter_LemonLDAP constructor.
     */
    public function __construct()
    {
        $this->setCasOptions();
    }

    /**
     * Sets the default options for the CAS server
     * @return $this
     */
    private function setCasOptions(): self
    {

        $this->setCasVersion(LEMON_LDAP_SERVICE_VERSION)
            ->setCasHostname(LEMON_LDAP_SERVICE_HOST)
            ->setCasPort(LEMON_LDAP_SERVICE_PORT)
            ->setCasUrl(LEMON_LDAP_SERVICE_URL)
            ->setCasStartSessions(false)
            ->setCasSslValidation(LEMON_LDAP_SERVICE_SSLVALIDATION)
            ->setCasCACert(LEMON_LDAP_SERVICE_SSLCACERT);
        return $this;
    }

    /**
     * Returns the hostname that the CAS application will use
     * For redirection after login and logout
     *
     * @return string
     */
    public static function getCurrentHostname(): string
    {
        if ((isset($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] !== '')) {
            $scheme = 'https://';
        } else {
            $scheme = 'http://';
        }

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

    /**
     * @return Ccsd_User_Models_User
     */
    public function getIdentityStructure(): ?Ccsd_User_Models_User
    {
        return $this->_identityStructure;
    }

    /**
     * Initialisation de la structure de l'identité utilisateur
     *
     * @param $identity
     */
    public function setIdentityStructure($identity): void
    {
        // Par compat, on met la structure dans identity aussi
        $this->_identity = $identity;
        $this->_identityStructure = $identity;
    }


    /**
     *
     * @return string $_casVersion
     */
    public function getCasVersion(): string
    {
        return $this->_casVersion;
    }

    /**
     *
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
     * @return string $_casHostname
     */
    public function getCasHostname(): string
    {
        return $this->_casHostname;
    }

    /**
     *
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
     *
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
     *
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
     *
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
     *
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
     *
     * @param string $_casCACert
     * @return $this
     */
    public function setCasCACert(string $_casCACert): self
    {
        $this->_casCACert = $_casCACert;
        return $this;
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
     *
     * @param string[] $params
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

    /**
     * Authentification d'un utilisateur
     * @see Zend_Auth_Adapter_Interface::authenticate()
     */
    public function authenticate(): Zend_Auth_Result
    {

        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getCasStartSessions());

        }

        $this->setLogger();

        if (!$this->getCasSslValidation()) {
            // no SSL validation for the CAS server
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }
        // Url de retour/service après authentification
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
     * Déconnexion de l'utilisateur, avec URL de retour/destination facultative
     *
     * @param string $urlDeDestination
     */
    public function logout($urlDeDestination = null)
    {
        if (!isset($PHPCAS_CLIENT)) {
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $this->getCasStartSessions());
        }

        if ($this->getCasSslValidation() === false) {
            phpCAS::setNoCasServerValidation();
        } else {
            phpCAS::setCasServerCACert($this->getCasCACert());
        }

        if (!is_string($urlDeDestination)) {
            phpCAS::logout(); // logout et reste sur la page CAS
        } else {
            phpCAS::logoutWithRedirectService($urlDeDestination);
        }
    }


    /**
     * @param Ccsd_User_Models_User $loginUser
     * @param ArrayAccess $array_attr
     * @return bool
     */
    public function alt_login($loginUser, $array_attr)
    {
        return true;
    }

    /**
     * @param array $params
     * @return string
     */
    private function buildLoginDestinationUrl(array $params = []): string
    {

        $hostname = self::getCurrentHostname();

        $hostname = rtrim($hostname, '/');

        $uri = $hostname . '/user/login';

        $forwardController = $params['forward-controller'] ?? null;
        $forwardAction = $params['forward-action'] ?? null;

        // Si pas de controller ou si controller == user/logout
        if (($forwardController === null) || (($forwardController === 'user') && ($forwardAction === 'logout'))) {
            // destination par défaut
            $uri .= '/forward-controller/user';
        } else {
            $uri .= '/forward-controller/' . urlencode($forwardController);
            if ($forwardAction) {

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
            }
        }
        return $uri;
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


    private function setLogger(): void{


        if (defined('LEMON_LDAP_SERVICE_LOG_PATH')) {
            if (LEMON_LDAP_SERVICE_LOG_PATH  !== '') {
                $logPath = LEMON_LDAP_SERVICE_LOG_PATH;
            } else {
                $logPath = realpath(sys_get_temp_dir()) . '/lm-cas.log';
            }
        }

        $casLogger = new Logger('lmCASLogger');
        $handler = new RotatingFileHandler($logPath,0, Logger::DEBUG, true, 0664);

        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $casLogger->pushHandler($handler);

        phpCAS::setLogger($casLogger);

    }
}
