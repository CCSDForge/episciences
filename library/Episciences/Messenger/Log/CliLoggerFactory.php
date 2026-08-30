<?php

declare(strict_types=1);

namespace Episciences\Messenger\Log;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Builds a CLI-oriented Monolog logger writing to EPISCIENCES_LOG_PATH and,
 * optionally, stdout — the shape every Messenger queue command needs
 * regardless of transport, without coupling this library to a Console
 * dependency (SymfonyStyle stays in scripts/, only the "should this also
 * print to stdout" boolean crosses the boundary).
 */
final class CliLoggerFactory
{
    public static function create(string $channel, bool $alsoStdout = true): Logger
    {
        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . $channel . '_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));

        if ($alsoStdout) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        return $logger;
    }
}
