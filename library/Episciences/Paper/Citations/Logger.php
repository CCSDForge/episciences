<?php
declare(strict_types=1);
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
     * Log a message at INFO level.
     * In CLI mode, output goes to stdout via the Monolog stdout handler (see getLogger()).
     */
    public static function log(string $msg): void
    {
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
     * Expose the Monolog Logger instance for injection into API clients.
     */
    public static function getMonologInstance(): Logger
    {
        return self::getLogger();
    }

    /**
     * Return the singleton Logger instance, creating it on first call.
     */
    private static function getLogger(): Logger
    {
        if (!self::$logger instanceof Logger) {
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
