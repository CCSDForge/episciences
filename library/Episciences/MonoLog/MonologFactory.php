<?php

namespace Episciences\MonoLog;

use Episciences\Log\LoggerFactory;
use Monolog\Logger;

class MonologFactory
{

    public static function createLogger(): Logger
    {
        return LoggerFactory::rotating(
            'appLogger',
            sprintf('%s.monolog.log', LoggerFactory::logPath() . (defined('RVCODE') ? RVCODE : 'app'))
        );
    }
}
