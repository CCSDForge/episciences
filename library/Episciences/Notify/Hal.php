<?php
use cottagelabs\coarNotifications\COARNotificationActor;
use cottagelabs\coarNotifications\COARNotificationContext;
use cottagelabs\coarNotifications\COARNotificationManager;
use cottagelabs\coarNotifications\COARNotificationObject;
use cottagelabs\coarNotifications\COARNotificationTarget;
use cottagelabs\coarNotifications\COARNotificationURL;
use cottagelabs\coarNotifications\orm\COARNotificationException;
use cottagelabs\coarNotifications\orm\COARNotificationNoDatabaseException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;


class Episciences_Notify_Hal
{

    protected Episciences_Review $journal;
    protected Episciences_Paper $paper;
    protected Logger $logger;

    /**
     * @return Episciences_Review
     */
    public function getJournal(): Episciences_Review
    {
        return $this->journal;
    }

    /**
     * @param Episciences_Review $journal
     */
    public function setJournal(Episciences_Review $journal): void
    {
        $this->journal = $journal;
    }


    /**
     * @return Episciences_Paper
     */
    public function getPaper(): Episciences_Paper
    {
        return $this->paper;
    }

    /**
     * @param Episciences_Paper $paper
     */
    public function setPaper(Episciences_Paper $paper): void
    {
        $this->paper = $paper;
    }

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

    /**
     * @param Episciences_Paper $paper
     * @param Episciences_Review $journal
     */
    public function __construct(Episciences_Paper $paper, Episciences_Review $journal)
    {
        $this->setJournal($journal);
        $this->setPaper($paper);
        $this->initLogging();
    }

    /**
     * @return void
     */
    private function initLogging(): void
    {
        $cn_logger = new Logger('NotifyCOARLogger');

        $handler = new RotatingFileHandler(EPISCIENCES_LOG_PATH . 'NotifyCOARLogger.log',
            0, Logger::DEBUG, true, 0664);
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);
        $cn_logger->pushHandler($handler);

        $this->setLogger($cn_logger);
    }

    /**
     * @return string
     * @throws COARNotificationException
     * @throws COARNotificationNoDatabaseException
     */
    public function announceEndorsement(): string
    {

        $cn_logger = $this->getLogger();
        $cn_paper = $this->getPaper();
        $cn_journal = $this->getJournal();

        $conn = ['host' => INBOX_DB_HOST,
            'driver' => INBOX_DB_DRIVER,
            'user' => INBOX_DB_USER,
            'password' => INBOX_DB_PASSWORD,
            'dbname' => INBOX_DB_NAME,
            'port' => INBOX_DB_PORT,
        ];


        $coarNotificationManager = new COARNotificationManager(
            $conn,
            $cn_logger,
            $cn_journal->getUrl(),
            INBOX_URL,
            5,
            EPISCIENCES_USER_AGENT
        );


        // Sender Episciences
        $actor = new COARNotificationActor(
            $cn_journal->getUrl(),
            $cn_journal->getName(),
            'Service');

        $paperUrl = sprintf('%s/%s', $cn_journal->getUrl(), $cn_paper->getPaperid());

        // Article published
        if ($cn_paper->hasDoi()) {
            $paperPid = $cn_paper->getDoi(true);
        } else {
            $paperPid = $paperUrl;
        }

        $object = new COARNotificationObject(
            $paperUrl,
            $paperPid,
            ['Page', 'sorg:WebPage']
        );


        // Preprint
        $inRepositoryUrl = Episciences_Repositories::getDocUrl($cn_paper->getRepoid(), $cn_paper->getIdentifier(), $cn_paper->getVersion());
        $inRepositoryUrlPdf = sprintf('%s/pdf', $inRepositoryUrl);
        $inRepositoryPid = $inRepositoryUrl;
        $url = new COARNotificationURL(
            $inRepositoryUrlPdf,
            'application/pdf',
            ['Article', 'sorg:ScholarlyArticle']
        );


        $context = new COARNotificationContext(
            $inRepositoryUrl,
            $inRepositoryPid,
            ['Announce,coar-notify:EndorsementAction'],
            $url);

        // Recipient HAL
        $target = new COARNotificationTarget(
            NOTIFY_TARGET_HAL_URL,
            NOTIFY_TARGET_HAL_INBOX);

        try {
            $notification = $coarNotificationManager->createOutboundNotification($actor, $object, $context, $target);
        } catch (COARNotificationException|Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }

        $coarNotificationManager->announceEndorsement($notification);

        return $notification->getId();
    }


}