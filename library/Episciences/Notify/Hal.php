<?php

declare(strict_types=1);

use coarnotify\client\COARNotifyClient;
use coarnotify\client\NotifyResponse;
use coarnotify\exceptions\NotifyException;
use coarnotify\patterns\announce_endorsement\AnnounceEndorsement;
use coarnotify\patterns\announce_endorsement\AnnounceEndorsementContext;
use coarnotify\patterns\announce_endorsement\AnnounceEndorsementItem;
use coarnotify\core\notify\NotifyActor;
use coarnotify\core\notify\NotifyObject;
use coarnotify\core\notify\NotifyService;
use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Ramsey\Uuid\Uuid;


class Episciences_Notify_Hal
{

    protected Episciences_Review $journal;
    protected Episciences_Paper $paper;
    protected Logger $logger;
    private NotificationsRepository $repository;
    private ?COARNotifyClient $client;

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
     * @param NotificationsRepository|null $repository
     * @param COARNotifyClient|null $client
     */
    public function __construct(
        Episciences_Paper $paper,
        Episciences_Review $journal,
        ?NotificationsRepository $repository = null,
        ?COARNotifyClient $client = null
    ) {
        $this->setJournal($journal);
        $this->setPaper($paper);
        $this->initLogging();
        $this->repository = $repository ?? NotificationsRepository::createFromConstants();
        $this->client = $client;
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
     */
    public function announceEndorsement(): string
    {
        $cn_paper = $this->getPaper();
        $cn_journal = $this->getJournal();
        $cn_logger = $this->getLogger();

        $notificationId = 'urn:uuid:' . Uuid::uuid4()->toString();

        // Build the AnnounceEndorsement notification (disable stream validation on construct)
        $announcement = new AnnounceEndorsement(null, false);
        $announcement->setId($notificationId);

        // Sender: Episciences journal acting as Service
        $actor = new NotifyActor();
        $actor->setId($cn_journal->getUrl());
        $actor->setName($cn_journal->getName());

        // Origin: this journal's inbox
        $origin = new NotifyService();
        $origin->setId($cn_journal->getUrl());
        $origin->setInbox(INBOX_URL);

        // Target: HAL repository
        $target = new NotifyService();
        $target->setId(NOTIFY_TARGET_HAL_URL);
        $target->setInbox(NOTIFY_TARGET_HAL_INBOX);

        // Object: the published paper page
        $paperUrl = sprintf('%s/%s', $cn_journal->getUrl(), $cn_paper->getPaperid());
        if ($cn_paper->hasDoi()) {
            $paperPid = (string) $cn_paper->getDoi(true);
        } else {
            $paperPid = $paperUrl;
        }

        $object = new NotifyObject();
        $object->setId($paperUrl);
        $object->setCiteAs($paperPid);
        $object->setType(['Page', 'sorg:WebPage']);

        // Context: the preprint in HAL
        $inRepositoryUrl = (string) Episciences_Repositories::getDocUrl(
            $cn_paper->getRepoid(),
            $cn_paper->getIdentifier(),
            $cn_paper->getVersion()
        );
        $inRepositoryUrlPdf = sprintf('%s/pdf', $inRepositoryUrl);
        $inRepositoryPid = $inRepositoryUrl;

        $item = new AnnounceEndorsementItem();
        $item->setId($inRepositoryUrlPdf);
        $item->setType(['Article', 'sorg:ScholarlyArticle']);
        $item->setMediaType('application/pdf');

        $context = new AnnounceEndorsementContext();
        $context->setId($inRepositoryUrl);
        $context->setCiteAs($inRepositoryPid);
        $context->setItem($item->getDoc());

        $announcement->setActor($actor);
        $announcement->setOrigin($origin);
        $announcement->setTarget($target);
        $announcement->setObject($object);
        $announcement->setContext($context);

        // Capture the full JSON-LD payload before sending
        $originalJson = json_encode($announcement->toJSONLD()) ?: '';

        // Send via COAR Notify client
        $status = Notification::STATUS_PENDING;
        try {
            $client = $this->client ?? new COARNotifyClient(NOTIFY_TARGET_HAL_INBOX);
            $response = $client->send($announcement);
            $status = ($response->getAction() === NotifyResponse::CREATED) ? 201 : 202;
        } catch (NotifyException $e) {
            $cn_logger->error('COARNotify send failed: ' . $e->getMessage());
            $status = Notification::STATUS_FAILED;
        }

        // Persist the outbound notification
        $notification = new Notification();
        $notification->setId($notificationId);
        $notification->setFromId($cn_journal->getUrl());
        $notification->setToId(NOTIFY_TARGET_HAL_URL);
        $notification->setType(json_encode(['Announce', 'coar-notify:EndorsementAction']) ?: '');
        $notification->setStatus($status);
        $notification->setOriginal($originalJson);
        $notification->setDirection(Notification::DIRECTION_OUTBOUND);

        try {
            $this->repository->save($notification);
        } catch (\PDOException $e) {
            $cn_logger->error('Failed to save notification: ' . $e->getMessage());
        }

        return $notificationId;
    }


}
