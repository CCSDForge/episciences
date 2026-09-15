<?php

declare(strict_types=1);

namespace Episciences\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use RuntimeException;

final class LoggerFactory
{
    public const FILE_PERMISSION = 0664;

    /**
     * Rotating file logger: one file per day, unlimited retention.
     * Used by the application bootstrap, the CAS adapters and the COAR Notify classes.
     */
    public static function rotating(
        string $channel,
        ?string $file = null,
        Level $level = Level::Debug
    ): Logger
    {
        $logger = new Logger($channel);

        $handler = new RotatingFileHandler(
            $file ?? self::logPath() . $channel . '.log',
            0,
            $level,
            true,
            self::FILE_PERMISSION
        );
        $handler->setFormatter(new LineFormatter(null, null, false, true));
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * CLI logger: one dated file plus an optional stdout stream.
     * Used by the Symfony Console commands.
     */
    public static function cli(
        string $channel,
        bool $alsoStdout = true,
        Level $fileLevel = Level::Info,
        Level $stdoutLevel = Level::Info
    ): Logger
    {
        $logger = new Logger($channel);

        $logger->pushHandler(new StreamHandler(
            self::logPath() . $channel . '_' . date('Y-m-d') . '.log',
            $fileLevel
        ));

        if ($alsoStdout) {
            $logger->pushHandler(new StreamHandler('php://stdout', $stdoutLevel));
        }

        return $logger;
    }

    /**
     * Resolves EPISCIENCES_LOG_PATH, with an explicit override for tests.
     */
    public static function logPath(): string
    {
        if (!defined('EPISCIENCES_LOG_PATH')) {
            throw new RuntimeException('EPISCIENCES_LOG_PATH is not defined.');
        }

        return EPISCIENCES_LOG_PATH;
    }
}
