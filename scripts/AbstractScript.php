<?php

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once "Script.php";

Abstract class AbstractScript extends Script
{
    protected Logger $logger;
    protected function initLogging(): void
    {
        $loggerName = sprintf('%s', strtolower(get_class($this)));

        $logger = new Logger($loggerName);

        $handler = new RotatingFileHandler(
            sprintf('%s%s.log', EPISCIENCES_LOG_PATH, $loggerName),
            0, // unlimited
            Logger::DEBUG,
            true,
            0664
        );

        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::CRITICAL));
        $this->logger = $logger;
    }

    protected function getLogger(): Logger
    {
        return $this->logger;
    }

}