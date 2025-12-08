<?php

namespace Episciences\MonoLog;



use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class MonologFactory
{

    public static function createLogger(): Logger
    {

        $logger = new Logger('appLogger');

        $handler = new RotatingFileHandler(
            sprintf('%s.monolog.log', EPISCIENCES_LOG_PATH . (defined('RVCODE') ? RVCODE : 'app')),
            0, // unlimited
            Logger::DEBUG,
            true,
            0664
        );
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        return $logger;
    }
}
