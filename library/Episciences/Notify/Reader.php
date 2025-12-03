<?php


use cottagelabs\coarNotifications\COARNotificationManager;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class Episciences_Notify_Reader
{

    public const NOTIFY_READER_LOGGER = 'notifyReaderLogger';
    public const FILE_PERMISSION_LOGGER = 0664;
    public const MAX_FILE_LOGGER = 0;
    protected Logger $logger;
    private ?COARNotificationManager $coarNotificationManager;

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

    public function __construct()
    {

        $this->initLogging();

        try {
            $this->coarNotificationManager = new COARNotificationManager(
                $this->getConnectionParamsArray(),
                $this->getLogger(),
                INBOX_ID,
                INBOX_URL,
                10,
                EPISCIENCES_USER_AGENT
            );
        } catch (Exception $e) {
            $this->coarNotificationManager = null;
        }

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
     * @param string $direction
     * @return array
     */
    public function getNotifications(string $direction = 'in'): array
    {
        return $this->coarNotificationManager->getNotifications($direction);
    }

    /**
     * @return array
     */
    public function getConnectionParamsArray(): array
    {
        return [
            'host' => INBOX_DB_HOST,
            'driver' => INBOX_DB_DRIVER,
            'user' => INBOX_DB_USER,
            'password' => INBOX_DB_PASSWORD,
            'dbname' => INBOX_DB_NAME,
            'port' => INBOX_DB_PORT,
        ];
    }

    /**
     * @return COARNotificationManager|null
     */
    public function getCoarNotificationManager(): ?COARNotificationManager
    {
        return $this->coarNotificationManager;
    }

    /**
     * @param COARNotificationManager|null $coarNotificationManager
     * @return Episciences_Notify_Reader
     */
    public function setCoarNotificationManager(?COARNotificationManager $coarNotificationManager): self
    {
        $this->coarNotificationManager = $coarNotificationManager;
        return $this;
    }


}