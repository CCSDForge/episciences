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

// public/bdd_const.php reads a pwd.json to define every database constant. It reports a missing
// or invalid file with die() -- harmless for a web request, but a silent success on the CLI --
// so the case is caught here, before it can be swallowed.
//
// Its location follows the same resolution order as best_define(): an already defined PWD_PATH
// constant, then a PWD_PATH environment variable, then config/. CI sets the environment variable
// so that the generated test configuration never overwrites a developer's own config/pwd.json.
if (defined('PWD_PATH')) {
    $credentialsDir = PWD_PATH;
} else {
    $credentialsDir = getenv('PWD_PATH') ?: dirname(__DIR__) . '/config';
}

$credentialsPath = rtrim($credentialsDir, '/') . '/pwd.json';

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

// Review id under test. Defined once here rather than by whichever test case happens to run
// first: a constant cannot be redefined, so per-test values leak into every later test.
defined('RVID') || define('RVID', 1);


set_include_path(implode(PATH_SEPARATOR, array_merge([__DIR__ . '/library'], [get_include_path()])));

require_once dirname(__DIR__) . '/vendor/autoload.php';


try {
    $application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
    $application->getBootstrap()->bootstrap();

} catch (Zend_Application_Exception $e) {
    trigger_error($e->getMessage(), E_USER_ERROR);
}

// Episciences_Translation_Plugin only registers Zend_Translate on a real HTTP dispatch,
// which never happens in unit tests. Without it, any code path calling Ccsd_Tools::translate()
// (or similar) hits Zend_Registry::get('Zend_Translate') and logs a "Panic: ... not defined"
// line per call. Register a real array-adapter instance once here — same pattern individual
// test files already used — so tests get a working translator instead of that noisy fallback.
if (!Zend_Registry::isRegistered('Zend_Translate')) {
    Zend_Registry::set('Zend_Translate', new Zend_Translate([
        'adapter' => Zend_Translate::AN_ARRAY,
        'content' => ['' => ''],
        'locale'  => 'en',
    ]));
}
// Ccsd_Tools::translate() falls back to Zend_Registry::get('lang') when called without
// an explicit language, which panics the same way if unset. 'en' matches what code reading
// this key already assumes when it's absent (e.g. GetAvatar::asPaperStatusSvg's own catch).
Zend_Registry::isRegistered('lang') || Zend_Registry::set('lang', 'en');
