<?php
/**
 * Router for PHP's built-in web server, used by the E2E workflow (.github/workflows/e2e.yml).
 *
 * Mirrors public/.htaccess: an existing file of public/ is served as it is (PHP files such as
 * js/translation.php are executed), every other request goes through the front controller.
 *
 * With E2E_APP_MODE=manager, also mirrors the aliases of the manager virtual host
 * (src/apache/vhost.conf on the *-epi-manager branches), where the journal is served under its code:
 * /<rvcode>/public/ and /<rvcode>/resources/ map to data/<rvcode>/public/, /public/ to public/.
 *
 * Usage: php -S 127.0.0.1:80 -t public .github/scripts/e2e-router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (is_string($path) && $path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}

if (is_string($path) && getenv('E2E_APP_MODE') === 'manager') {
    $rvCode = getenv('E2E_RVCODE') ?: 'dev';
    $journalPublic = dirname($_SERVER['DOCUMENT_ROOT']) . "/data/$rvCode/public";
    $aliases = [
        "/$rvCode/public/" => $journalPublic,
        "/$rvCode/resources/" => $journalPublic,
        '/public/' => $_SERVER['DOCUMENT_ROOT'],
    ];
    foreach ($aliases as $prefix => $directory) {
        if (str_starts_with($path, $prefix)) {
            serveAliasedFile($directory, substr($path, strlen($prefix)));
            return true;
        }
    }
}

chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';

/**
 * Sends a static file of an aliased directory, or a 404 when it does not exist or lies outside it.
 */
function serveAliasedFile(string $directory, string $relativePath): void
{
    $root = realpath($directory);
    $file = $root === false ? false : realpath($root . '/' . rawurldecode($relativePath));
    if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        return;
    }

    $types = [
        'css' => 'text/css', 'js' => 'text/javascript', 'json' => 'application/json', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'ico' => 'image/x-icon', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    ];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
}
