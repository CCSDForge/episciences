<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Singleton logger for the citations enrichment pipeline.
 * Centralizes both file logging and CLI output, avoiding repeated Logger instantiation.
 */
class Episciences_Paper_Citations_Logger
{
    private const LOGGER_CHANNEL = 'CitationsManager';
    private const LOG_FILE_PREFIX = 'getcitationsdata_';

    private static ?Logger $logger = null;

    /**
     * Log a message at INFO level, and echo it to stdout when running from CLI.
     */
    public static function log(string $msg): void
    {
        if (PHP_SAPI === 'cli') {
            echo PHP_EOL . $msg . PHP_EOL;
        }

        self::getLogger()->info($msg);
    }

    /**
     * Alias for log(), provided for semantic clarity.
     */
    public static function info(string $msg): void
    {
        self::log($msg);
    }

    /**
     * Return the singleton Logger instance, creating it on first call.
     */
    private static function getLogger(): Logger
    {
        if (!self::$logger instanceof \Monolog\Logger) {
            self::$logger = new Logger(self::LOGGER_CHANNEL);
            self::$logger->pushHandler(
                new StreamHandler(
                    EPISCIENCES_LOG_PATH . self::LOG_FILE_PREFIX . date('Y-m-d') . '.log',
                    Logger::INFO
                )
            );
        }

        return self::$logger;
    }
}
