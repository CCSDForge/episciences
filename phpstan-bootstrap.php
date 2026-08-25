<?php

// PHPStan bootstrap file - minimal setup for static analysis
// Loads constants and autoloader without database connection

// Set APPLICATION_ENV for PHPStan analysis
putenv('APPLICATION_ENV=development');

// Load constants and basic setup
require_once __DIR__ . '/public/const.php';
defineProtocol();
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineJournalConstants();
defineVendorCssLibraries();
defineVendorJsLibraries();

// Load database constants
require_once __DIR__ . '/public/bdd_const.php';

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array_merge([__DIR__ . '/library'], [get_include_path()])));

// Load composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load Zend Framework autoloader
require_once __DIR__ . '/vendor/shardj/zf1-future/library/Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();