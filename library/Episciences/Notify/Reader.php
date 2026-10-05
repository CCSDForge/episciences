<?php

declare(strict_types=1);

use Episciences\Log\LoggerFactory;
use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Monolog\Logger;

class Episciences_Notify_Reader
{

    public const NOTIFY_READER_LOGGER = 'notifyReaderLogger';
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
        $cnLogger = LoggerFactory::rotating(
            self::NOTIFY_READER_LOGGER,
            EPISCIENCES_LOG_PATH . self::NOTIFY_READER_LOGGER . 'log'
        );

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
