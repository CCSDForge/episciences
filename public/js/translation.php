<?php

header("content-type: application/x-javascript");

require_once '../const.php';
define_simple_constants();
define_table_constants();
define_app_constants();
define_review_constants();


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