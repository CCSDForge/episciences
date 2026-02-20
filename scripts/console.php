<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GenerateUsersCommand.php';
require_once __DIR__ . '/InitDevUsersCommand.php';
require_once __DIR__ . '/CreateBotUserCommand.php';

use Symfony\Component\Console\Application;

$application = new Application('Episciences CLI', '1.0.0');

// Register your commands here
$application->add(new GenerateUsersCommand());
$application->add(new InitDevUsersCommand());
$application->add(new CreateBotUserCommand());

$application->run();
