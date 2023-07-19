<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Adapter Zend_Auth pour l'authentification via CAS
 *
 * @see https://wiki.jasig.org/display/CASC/phpCAS JASIG phpCAS library
 * @see https://github.com/Jasig/phpCAS
 * @author ccsd
 *
 */
class Ccsd_Auth_Adapter_Cas extends Ccsd_Auth_Adapter_CasAbstract implements \Ccsd\Auth\Adapter\AdapterInterface
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
     * @var bool
     */
    protected $_casStartSessions = false;

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
    protected $_serviceURL = '';

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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasVersion(string $_casVersion): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasHostname(string $_casHostname): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasPort(int $_casPort): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasUrl(string $_casUrl): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasStartSessions(bool $_casStartSessions): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasSslValidation(bool $_casSslValidation): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setCasCACert(string $_casCACert): Ccsd_Auth_Adapter_Cas
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
     * @return Ccsd_Auth_Adapter_Cas
     */
    public function setServiceURL(array $params = []): Ccsd_Auth_Adapter_Cas
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
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(),  $this->getServiceURL(), $this->getCasStartSessions());

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
            phpCAS::client($this->getCasVersion(), $this->getCasHostname(), $this->getCasPort(), $this->getCasUrl(), $urlDeDestination, $this->getCasStartSessions());
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

    private function setLogger(): void
    {


        if (defined('CAS_SERVICE_LOG_PATH')) {
            if (CAS_SERVICE_LOG_PATH !== '') {
                $logPath = CAS_SERVICE_LOG_PATH;
            } else {
                $logPath = realpath(sys_get_temp_dir()) . '/cas.log';
            }
        }

        $casLogger = new Logger('CASLogger');
        $handler = new RotatingFileHandler($logPath, 0, Logger::DEBUG, true, 0664);

        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $casLogger->pushHandler($handler);

        phpCAS::setLogger($casLogger);

    }

}
