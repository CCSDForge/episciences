<?php

declare(strict_types=1);

// Entry point for processing inbound COAR Notify submissions from the HAL inbox.
// @see https://notify.coar-repositories.org/patterns/request-review/
// @see https://notify.coar-repositories.org/patterns/request-endorsement/

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ProcessInboxNotificationsCommand.php';

use Symfony\Component\Console\Application;

$application = new Application('Episciences CLI', '1.0.0');
$command     = new ProcessInboxNotificationsCommand();
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), true);

$application->run();
