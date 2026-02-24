<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GenerateUsersCommand.php';
require_once __DIR__ . '/InitDevUsersCommand.php';
require_once __DIR__ . '/CreateBotUserCommand.php';
require_once __DIR__ . '/GetCitationsDataCommand.php';
require_once __DIR__ . '/GetCreatorDataCommand.php';
require_once __DIR__ . '/GetLicenceDataCommand.php';
require_once __DIR__ . '/GetLinkDataCommand.php';
require_once __DIR__ . '/GetFundingDataCommand.php';

use Symfony\Component\Console\Application;

$application = new Application('Episciences CLI', '1.0.0');

// Register commands
$application->add(new GenerateUsersCommand());
$application->add(new InitDevUsersCommand());
$application->add(new CreateBotUserCommand());

// Enrichment commands
$application->add(new GetCitationsDataCommand());
$application->add(new GetCreatorDataCommand());
$application->add(new GetLicenceDataCommand());
$application->add(new GetLinkDataCommand());
$application->add(new GetFundingDataCommand());

$application->run();
