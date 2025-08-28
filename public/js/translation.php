<?php

header("content-type: application/x-javascript");

// Load autoloader first
require_once '../../vendor/autoload.php';

require_once '../const.php';
defineProtocol();
defineSimpleConstants();
defineSQLTableConstants();
defineApplicationConstants();
defineJournalConstants();


$lang = $_GET['lang'] ?? 'en';
$path = APPLICATION_PATH . '/languages/';

$translations = array();

foreach (scandir($path) as $folder) {
    $filepath = $path . $folder . '/js.php';
    if (is_file($filepath)) {
        $translations[$folder] = require_once $filepath;
    }
}

echo "var locale = '" . $lang . "';" . PHP_EOL;
echo "var translations = " . json_encode($translations) . ';' . PHP_EOL;

?>
function translate (key, lang) {
    let i = (lang) ? lang : locale;
    if (translations[i] == undefined || translations[i][key] == undefined)
    {
        return key;
    }
    return translations[i][key];
}