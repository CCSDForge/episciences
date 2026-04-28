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
require_once __DIR__ . '/GetClassificationJelCommand.php';
require_once __DIR__ . '/GetClassificationMscCommand.php';
require_once __DIR__ . '/GetZbReviewsCommand.php';
require_once __DIR__ . '/GenerateSitemapCommand.php';
require_once __DIR__ . '/MergePdfVolCommand.php';
require_once __DIR__ . '/CreateDoajVolumeExportsCommand.php';
require_once __DIR__ . '/ZbjatsZipperCommand.php';
require_once __DIR__ . '/ImportSectionsCommand.php';
require_once __DIR__ . '/ImportVolumesCommand.php';
require_once __DIR__ . '/UpdateCounterRobotsListCommand.php';
require_once __DIR__ . '/ProcessStatTempCommand.php';
require_once __DIR__ . '/UpdateGeoIpCommand.php';

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
$application->add(new GetClassificationJelCommand());
$application->add(new GetClassificationMscCommand());
$application->add(new GetZbReviewsCommand());

// Sitemap commands
$application->add(new GenerateSitemapCommand());

// Volume commands
$application->add(new MergePdfVolCommand());

// DOAJ commands
$application->add(new CreateDoajVolumeExportsCommand());

// zbJATS commands
$application->add(new ZbjatsZipperCommand());

// Import commands
$application->add(new ImportSectionsCommand());
$application->add(new ImportVolumesCommand());

// Stats commands
$application->add(new UpdateCounterRobotsListCommand());
$application->add(new ProcessStatTempCommand());

// GeoIP commands
$application->add(new UpdateGeoIpCommand());

$application->run();
