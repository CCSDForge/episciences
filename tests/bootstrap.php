<?php
$configPath = dirname(__DIR__) . '/tests/config/';
$envFile = 'env-test.php'; // needed to define review constants

if (file_exists($configPath . $envFile)) {
    require $configPath . $envFile;
} else {

    $message = sprintf('** File not found : %s **', $envFile);
    $message .= PHP_EOL;
    $message .= 'See /tests/config/env-test.php.dist';
    $message .= PHP_EOL;

    exit($message);
}

require_once dirname(__DIR__) . '/public/const.php';
require_once dirname(__DIR__) . '/public/bdd_const.php';
defineProtocol();
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineJournalConstants();

// Define application environment
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: ENV_DEV));
defined('APPLICATION_MODULE') || define('APPLICATION_MODULE', (getenv('APPLICATION_MODULE') ?: PORTAL));


set_include_path(implode(PATH_SEPARATOR, array_merge([__DIR__ . '/library'], [get_include_path()])));

require_once dirname(__DIR__) . '/vendor/autoload.php';


try {
    $application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
    $application->getBootstrap()->bootstrap();

} catch (Zend_Application_Exception $e) {
    trigger_error($e->getMessage(), E_USER_ERROR);
}