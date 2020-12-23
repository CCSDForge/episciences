<?php

/**
 * Class Ccsd_Log
 * Log messages des scripts en ligne de commande
 */
class Ccsd_Log
{
    /** @const string */
    const REGISTRY_LOGGER_NAME = __CLASS__;

    /** @const string */
    const LOG_FILENAME_SUFFIX = '.log';

    /** @const string */
    const ERROR_LOG_FILENAME_SUFFIX = '.err';

    /** @const string */
    const DEFAULT_LOG_LEVEL = Zend_Log::INFO;

    /** @const string */
    const DEFAULT_MESSAGE = ' ** WARNING : Message vide';

    /**
     * Log un message
     *
     * @param string $message
     * @param boolean $print afficher dans la console
     * @param string $level Level {NOTICE,WARN,ERR,CRIT}
     * @param string $filename le nom du fichier de log
     */
    static function message($message = self::DEFAULT_MESSAGE, $print = true, $level = '', $filename = '')
    {

        if ($filename == '') {
            $filename = self::initDefaultFilename();
        }

        $logger = self::getLogger($filename);

        if ($print === true) {
            $logger = self::initWriter('php://output', $logger);
        }

        switch ($level) {

            case 'NOTICE':
                $logger->log($message, Zend_Log::NOTICE);
                break;

            case 'WARN':
                $logger->log($message, Zend_Log::WARN);
                break;
            case 'ERR':
                // uniquement pour les ERR + CRIT
                $logger = self::initWriter($filename . self::ERROR_LOG_FILENAME_SUFFIX, $logger);
                $logger->log($message, Zend_Log::ERR);
                break;
            case 'CRIT':
                // uniquement pour les ERR + CRIT
                $logger = self::initWriter($filename . self::ERROR_LOG_FILENAME_SUFFIX, $logger);
                $logger->log($message, Zend_Log::CRIT);
                break;
            default:
                $logger->log($message, self::DEFAULT_LOG_LEVEL);
                break;
        }
    }

    /**
     * Init a default filename that will not change when date changes
     */
    public static function initDefaultFilename()
    {
        try {
            $filename = Zend_Registry::get(__CLASS__ . '_defaultFilename');
        } catch
        (Exception $exception) {
            $filename = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . date("Y-m-d") . '_' . __CLASS__;
            Zend_Registry::set(__CLASS__ . '_defaultFilename', $filename);
        }

        return $filename;
    }

    /**
     * Init base logger and writer
     * @param $filename
     * @return mixed|Zend_Log
     */
    static function getLogger($filename)
    {
        try {
            $logger = Zend_Registry::get(self::REGISTRY_LOGGER_NAME);

        } catch (Exception $exception) {
            $logger = new Zend_Log();
            $logger = self::initWriter($filename . self::LOG_FILENAME_SUFFIX, $logger);


            Zend_Registry::set(self::REGISTRY_LOGGER_NAME, $logger);
        }
        return $logger;
    }

    /**
     * add a writer to a logger
     * @param $filename
     * @param $logger
     * @return mixed
     */
    static function initWriter($filename, $logger)
    {
        $registryName = $filename;
        try {
            /** @var  Zend_Log $logger */
            $logger = Zend_Registry::get($registryName);

        } catch (Exception $exception) {
            $redacteur = new Zend_Log_Writer_Stream($filename);
            $logger->addWriter($redacteur);


            Zend_Registry::set($registryName, $logger);
        }

        return $logger;
    }

}

