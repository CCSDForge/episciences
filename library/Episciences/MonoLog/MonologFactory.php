<?php

namespace Episciences\MonoLog;



use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class MonologFactory
{
    /**
     * @throws Exception
     */
    public static function createLogger(): Logger
    {
        $logger = new Logger('appLogger');
        $handler = new StreamHandler(sprintf('%s.app.monolog.log', EPISCIENCES_EXCEPTIONS_LOG_PATH . RVCODE), Logger::DEBUG);
        $logger->pushHandler($handler);
        return $logger;
    }
}
