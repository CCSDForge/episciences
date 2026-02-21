<?php
$configPath = dirname(__DIR__) . '/tests/config/';
$envFile = 'env-test.php';

if (file_exists($configPath . $envFile)) {
    require $configPath . $envFile;
}

require_once dirname(__DIR__) . '/public/const.php';

defineProtocol();
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineJournalConstants();

defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: ENV_TEST));
defined('APPLICATION_MODULE') || define('APPLICATION_MODULE', (getenv('APPLICATION_MODULE') ?: PORTAL));

set_include_path(implode(PATH_SEPARATOR, array_merge([dirname(__DIR__) . '/library'], [get_include_path()])));

// Create temp log dir
$logDir = dirname(__DIR__) . '/tmp/logs/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Load configuration from pwd.json
$pwdFile = dirname(__DIR__) . '/config/pwd.json';
if (file_exists($pwdFile)) {
    $pwd = json_decode(file_get_contents($pwdFile), true);

    if (isset($pwd['EPISCIENCES'])) {
        $epi = $pwd['EPISCIENCES'];
        // Override log paths with local writable dir
        define('EPISCIENCES_LOG_PATH', $logDir);
        define('EPISCIENCES_EXCEPTIONS_LOG_PATH', $logDir);
        define('EPISCIENCES_SOLR_LOG_PATH', $logDir);

        if (!defined('EPISCIENCES_API_URL')) define('EPISCIENCES_API_URL', $epi['API_URL'] ?? '');
        if (!defined('EPISCIENCES_API_SECRET_KEY')) define('EPISCIENCES_API_SECRET_KEY', $epi['API_SECRET_KEY'] ?? '');
        if (!defined('EPISCIENCES_UID')) define('EPISCIENCES_UID', $epi['UID'] ?? 0);
        if (!defined('EPISCIENCES_USER_AGENT')) define('EPISCIENCES_USER_AGENT', $epi['USER_AGENT'] ?? '');
        if (!defined('EPISCIENCES_SUPPORT')) define('EPISCIENCES_SUPPORT', $epi['SUPPORT'] ?? '');
        if (!defined('EPISCIENCES_MAIL_PATH')) define('EPISCIENCES_MAIL_PATH', $logDir); // Use log dir for mail path too if it expects a dir
    }

    if (isset($pwd['FUSION'])) {
        if (!defined('FUSION_TOKEN_AUTH')) define('FUSION_TOKEN_AUTH', $pwd['FUSION']['TOKEN_AUTH'] ?? '');
    }
}

// Define other missing constants with defaults
if (!defined('CACHE_PATH')) define('CACHE_PATH', dirname(__DIR__) . '/cache/');
if (!defined('APPLICATION_VERSION')) define('APPLICATION_VERSION', 'test');
if (!defined('PWD_PATH')) define('PWD_PATH', dirname(__DIR__) . '/config/');
if (!defined('MANAGER_APPLICATION_URL')) define('MANAGER_APPLICATION_URL', 'http://localhost');
if (!defined('EPISCIENCES_Z_SUBMIT')) define('EPISCIENCES_Z_SUBMIT', 0);

// Just in case these were not defined above due to missing pwd.json
if (!defined('EPISCIENCES_LOG_PATH')) define('EPISCIENCES_LOG_PATH', $logDir);
if (!defined('EPISCIENCES_EXCEPTIONS_LOG_PATH')) define('EPISCIENCES_EXCEPTIONS_LOG_PATH', $logDir);
if (!defined('EPISCIENCES_SOLR_LOG_PATH')) define('EPISCIENCES_SOLR_LOG_PATH', $logDir);


require_once dirname(__DIR__) . '/vendor/autoload.php';

require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Episciences_');
$autoloader->registerNamespace('Ccsd_');

if (!class_exists('Zend_Registry')) {
    // Mock Zend_Registry
}
