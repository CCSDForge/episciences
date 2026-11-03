<?php
/**
 * Router for PHP's built-in web server, used by the E2E workflow (.github/workflows/e2e.yml).
 *
 * Mirrors public/.htaccess: an existing file of public/ is served as it is (PHP files such as
 * js/translation.php are executed), every other request goes through the front controller.
 *
 * Usage: php -S 127.0.0.1:80 -t public .github/scripts/e2e-router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (is_string($path) && $path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}

chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
