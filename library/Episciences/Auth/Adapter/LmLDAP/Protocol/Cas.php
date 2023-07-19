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


        if (defined('LEMON_LDAP_SERVICE_LOG_PATH')) {
            if (LEMON_LDAP_SERVICE_LOG_PATH !== '') {
                $logPath = LEMON_LDAP_SERVICE_LOG_PATH;
            } else {
                $logPath = realpath(sys_get_temp_dir()) . '/lm-cas.log';
            }
        }

        $casLogger = new Logger('lmCASLogger');
        $handler = new RotatingFileHandler($logPath, 0, Logger::DEBUG, true, 0664);

        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $casLogger->pushHandler($handler);

        phpCAS::setLogger($casLogger);

    }
}
