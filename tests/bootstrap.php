<?php

/**
 * Aborts the test run with a real failure exit code.
 *
 * Both `exit($string)` and `die($string)` return exit code 0. Bailing out that way made the
 * whole suite look successful to CI while not a single test had run, so every missing
 * prerequisite must go through this function instead.
 */
function abortTestBootstrap(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$configPath = dirname(__DIR__) . '/tests/config/';
$envFile = 'env-test.php'; // needed to define review constants

if (!file_exists($configPath . $envFile)) {
    abortTestBootstrap(
        sprintf('** File not found : %s **', $envFile) . PHP_EOL .
        'See /tests/config/env-test.php.dist, or run: php .github/scripts/setup-test-config.php'
    );
}

require $configPath . $envFile;

// public/bdd_const.php reads this file to define every database constant. It reports a missing
// or invalid file with die() -- harmless for a web request, but a silent success on the CLI --
// so the case is caught here, before it can be swallowed.
$credentialsPath = dirname(__DIR__) . '/config/pwd.json';

if (!file_exists($credentialsPath)) {
    abortTestBootstrap(
        sprintf('** File not found : %s **', $credentialsPath) . PHP_EOL .
        'See /config/dist-pwd.json, or run: php .github/scripts/setup-test-config.php'
    );
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