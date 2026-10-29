<?php
$ret = best_define('PWD_PATH', __DIR__ . '/../config');

//BDD Login / PWD
$path = PWD_PATH . '/pwd.json';

if (file_exists($path)) {
    $string = file_get_contents($path);
    $fileContent = json_decode($string, true);

    if (!$fileContent) {
        error_log('APPLICATION PANIC: ' . $path . ' is invalid.');
        header('HTTP/1.0 503 Service Unavailable');
        header('Retry-After: 120');
        die('Unexpected Application Error: the configuration file is invalid. We cannot continue until it is fixed. Please try again in 12O seconds.');
    }

    // Création des constantes d'accès aux bases de données et services
    foreach ($fileContent as $const => $current) {

        if (!is_array($current)) {
            defined($const) || define($const , $current);
        } else {
            foreach ($current as $key => $value) {
                defined($const . '_' . $key) || define($const . '_' . $key, $value);
            }

            if (isset($current['NAME'])) {
                defined($const . '_PDO_URL') || define($const . '_PDO_URL', "mysql:host=" . $current['HOST'] . (isset($current['PORT']) ? ";port=" . $current['PORT'] : '') . ";dbname=" . $current['NAME']);
            }

        }
    }
} else {
    error_log('APPLICATION PANIC: ' . $path . ' is missing.');
    header('HTTP/1.0 503 Service Unavailable');
    header('Retry-After: 120');
    die('Unexpected Application Error: the configuration file is missing. We cannot continue until it is fixed. Please try again in 12O seconds.');
}

// Define default values for ALTCHA if not provided in pwd.json
defined('CAPTCHA_BRAND') || define('CAPTCHA_BRAND', '');
defined('ALTCHA_HMAC_SECRET') || define('ALTCHA_HMAC_SECRET', '');

// Reviewer invitation link token secret: left empty, token generation and checks fail closed
defined('REVIEWER_INVITATION_HMAC_SECRET') || define('REVIEWER_INVITATION_HMAC_SECRET', '');

// Uploaded file token secret (see Episciences\Files\UploadedFileToken): left empty, uploads are refused (fail closed)
defined('UPLOADED_FILE_HMAC_SECRET') || define('UPLOADED_FILE_HMAC_SECRET', '');

// Read-only maintenance mode (no database write from the web application), disabled unless set in pwd.json
defined('EPISCIENCES_READ_ONLY_MODE') || define('EPISCIENCES_READ_ONLY_MODE', false);
// Planned start/end of the read-only maintenance, displayed to visitors (ISO 8601, e.g. 2026-10-20T08:00:00+02:00)
defined('EPISCIENCES_READ_ONLY_MODE_START') || define('EPISCIENCES_READ_ONLY_MODE_START', '');
defined('EPISCIENCES_READ_ONLY_MODE_END') || define('EPISCIENCES_READ_ONLY_MODE_END', '');

/**
 * S'assure que la constante de nom $name est definie
 * Si non definie, prendra la valeur de la variable d'environnement du meme nom
 * Sinon, prendra la valeur par defaut propose.
 * La valeur par defaut peut etre null mais un message sera emis
 * Pour eviter le message, passer le parametre warn a False
 * @param string $name : Name of constant to define
 * @param mixed $default : Default value
 * @param bool $warn :
 * @return array|false|string
 */
function best_define(string $name, $default, $warn = true)
{

    if ($warn && ($default === null)) {
        error_log("APPLICATION WARNING: we defined a constant $name as null");
    }
    if (defined($name)) {
        return PWD_PATH;
    }
    $env_value = getenv($name);
    if ($env_value !== false) {
        define($name, $env_value);
        return $env_value;
    }

    define($name, $default);
    return $default;
}