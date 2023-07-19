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
class Ccsd_Auth_Adapter_Cas extends Ccsd_Auth_Adapter_CasAbstract
{


    public function __construct()
    {
        $this->setCasOptions();
    }

    /**
     * Sets the default options for the CAS server
     * @return Ccsd_Auth_Adapter_Cas
     */
    protected function setCasOptions() : self
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
    protected function setLogger(): void
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
