<?php
namespace scripts;

use Episciences\Log\LoggerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Script;

require_once "Script.php";

Abstract class AbstractScript extends Script
{
    protected Logger $logger;
    protected function initLogging(): void
    {
        $loggerName = sprintf('%s', strtolower(get_class($this)));

        $logger = LoggerFactory::rotating(
            $loggerName,
            sprintf('%s%s.log', EPISCIENCES_LOG_PATH, $loggerName)
        );
        $logger->pushHandler(new StreamHandler('php://stdout', Level::Critical));
        $this->logger = $logger;
    }

    protected function getLogger(): Logger
    {
        return $this->logger;
    }

}