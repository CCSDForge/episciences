<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 12/01/18
 * Time: 10:33
 */

require_once __DIR__ . '/' . '../Ccsd/Script.php';

abstract class Episciences_Script extends Ccsd_Script
{

    /** @var  Zend_Db_Adapter_Pdo_Mysql */
    private $db = null;
    /** @var Zend_Application */
    private $application = null;

    public function initApp() {
        $this->println('Enter HalScript InitApp');
        $APPLICATION_PATH =  __DIR__ . '/../../application';
        $libraries = realpath($APPLICATION_PATH . '/../library');
        print "Include Path = " . $libraries . $APPLICATION_PATH . "\n";
        set_include_path($libraries . $APPLICATION_PATH . ":" . get_include_path());
        define('APPLICATION_PATH', $APPLICATION_PATH);
    }

    public function setupApp() {
        $this->debug('Enter HalScript SetupApp');
        if ($this->environment && !in_array($this->environment, $this->_valid_envs))  {
            $this->displayError("Incorrect application environment: " . $this->environment . PHP_EOL . "Should be one of these: " . implode(', ', $this->_valid_envs));
        }
        // require_once 'Episciences/constantes.php';

        define('APPLICATION_INI', APPLICATION_PATH . '/configs/application.ini');

        try {
            /*---------  Création de la Zend Application -----------*/
            $application = new Zend_Application(APPLICATION_ENV, APPLICATION_INI);
            $this -> application = $application;
            $application->getBootstrap()->bootstrap(array('db'));
            $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
            foreach ($application->getOption('consts') as $const => $value) {
                if(!defined($const)) {
                    define($const, $value);
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
        /*---------  Choix de la langue -----------*/
        Zend_Registry::set('languages', array('fr','en','es','eu'));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('fr'));
    }

}