#!/usr/bin/php
<?php
ini_set('xdebug.cli_color',1);

set_include_path(__DIR__ . '/../library');

require_once(__DIR__ . '/../public/bdd_const.php');


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
// Autoloader
require_once('Zend/Loader/Autoloader.php');
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);


$mail = new Episciences_Mail_Sender();
$mail->sendAll();
