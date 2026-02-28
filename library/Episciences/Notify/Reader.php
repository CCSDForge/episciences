<?php

declare(strict_types=1);

use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class Episciences_Notify_Reader
{

    public const NOTIFY_READER_LOGGER = 'notifyReaderLogger';
    public const FILE_PERMISSION_LOGGER = 0664;
    public const MAX_FILE_LOGGER = 0;
    protected Logger $logger;
    private NotificationsRepository $repository;

    /**
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    public function __construct(?NotificationsRepository $repository = null)
    {
        $this->initLogging();
        $this->repository = $repository ?? NotificationsRepository::createFromConstants();
    }

    /**
     * @return void
     */
    private function initLogging(): void
    {
        $cnLogger = new Logger(self::NOTIFY_READER_LOGGER);

        $handler = new RotatingFileHandler(
            EPISCIENCES_LOG_PATH . self::NOTIFY_READER_LOGGER . 'log' ,
            self::MAX_FILE_LOGGER,
            Logger::DEBUG,
            true,
            self::FILE_PERMISSION_LOGGER
        );
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $cnLogger->pushHandler($handler);

        $this->setLogger($cnLogger);
    }


    /**
     * @return Notification[]
     */
    public function getNotifications(): array
    {
        return $this->repository->findInbound();
    }

    /**
     * @return NotificationsRepository
     */
    public function getRepository(): NotificationsRepository
    {
        return $this->repository;
    }


}
