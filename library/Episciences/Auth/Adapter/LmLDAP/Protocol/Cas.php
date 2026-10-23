<?php

use Episciences\Log\LoggerFactory;

/**
 * Adapter Zend_Auth for authentication via LemonLDAP using the CAS protocol
 *
 * @see https://wiki.jasig.org/display/CASC/phpCAS JASIG phpCAS library
 * @see https://github.com/Jasig/phpCAS
 * @author ccsd
 *
 */
class Episciences_Auth_Adapter_LmLDAP_Protocol_Cas extends Ccsd_Auth_Adapter_CasAbstract
{
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
    protected function setCasOptions(): self
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

    protected function setLogger(): void
    {


        // Default first so $logPath is always defined, even when the constant is absent
        // (otherwise RotatingFileHandler below received an undefined variable).
        $logPath = realpath(sys_get_temp_dir()) . '/lm-cas.log';
        if (defined('LEMON_LDAP_SERVICE_LOG_PATH') && LEMON_LDAP_SERVICE_LOG_PATH !== '') {
            $logPath = LEMON_LDAP_SERVICE_LOG_PATH;
        }

        $casLogger = LoggerFactory::rotating('lmCASLogger', $logPath);

        phpCAS::setLogger($casLogger);

    }
}
