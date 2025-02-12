<?php

require_once 'const.php';
require_once __DIR__ . '/../vendor/autoload.php';
defineProtocol();
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineVendorCssLibraries();
defineVendorJsLibraries();


require_once 'bdd_const.php';

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array_merge([__DIR__ . '/../library'], [get_include_path()])));
defineJournalConstants();

// Create application, bootstrap, and run
try {
    $application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
} catch (Zend_Application_Exception $e) {
    error_log($e->getMessage());
    die('Fatal Error during init.');
}
$application->bootstrap()->run();

