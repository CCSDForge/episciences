<?php

namespace unit\library\Episciences\notify;

use coarnotify\client\COARNotifyClient;
use coarnotify\client\NotifyResponse;
use coarnotify\exceptions\NotifyException;
use coarnotify\http\HttpLayer;
use coarnotify\http\HttpResponse;
use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Episciences_Notify_Hal;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Review;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Episciences_Notify_HalTest extends TestCase
{
    private MockObject $paper;
    private MockObject $journal;
    private MockObject $repository;

    protected function setUp(): void
    {
        $this->paper = $this->createMock(Episciences_Paper::class);
        $this->journal = $this->createMock(Episciences_Review::class);
        $this->repository = $this->createMock(NotificationsRepository::class);

        $this->paper->method('getPaperid')->willReturn(42);
        $this->paper->method('hasDoi')->willReturn(false);
        $this->paper->method('getRepoid')->willReturn((int) Episciences_Repositories::HAL_REPO_ID);
        $this->paper->method('getIdentifier')->willReturn('hal-12345678');
        $this->paper->method('getVersion')->willReturn(1);

        $this->journal->method('getUrl')->willReturn('https://test-journal.episciences.org');
        $this->journal->method('getName')->willReturn('Test Journal');
    }

    private function buildClientWithStatus(int $httpStatus): COARNotifyClient
    {
        $httpResponse = $this->createMock(HttpResponse::class);
        $httpResponse->method('getStatusCode')->willReturn($httpStatus);
        $httpResponse->method('getHeader')->willReturn(null);

        $httpLayer = $this->createMock(HttpLayer::class);
        $httpLayer->method('post')->willReturn($httpResponse);

        return new COARNotifyClient(NOTIFY_TARGET_HAL_INBOX, $httpLayer);
    }

    public function testAnnounceEndorsementReturnUrnUuidId(): void
    {
        $client = $this->buildClientWithStatus(201);

        $this->repository->expects(self::once())->method('save');

        $hal = new Episciences_Notify_Hal($this->paper, $this->journal, $this->repository, $client);
        $id = $hal->announceEndorsement();

        self::assertNotEmpty($id);
        self::assertStringStartsWith('urn:uuid:', $id);
    }

    public function testAnnounceEndorsementSavesOutboundNotification(): void
    {
        $client = $this->buildClientWithStatus(202);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Notification $n): bool {
                return $n->getDirection() === Notification::DIRECTION_OUTBOUND
                    && $n->getStatus() === 202
                    && str_starts_with($n->getId(), 'urn:uuid:');
            }));

        $hal = new Episciences_Notify_Hal($this->paper, $this->journal, $this->repository, $client);
        $hal->announceEndorsement();
    }

    public function testAnnounceEndorsementSavesWithStatus201OnCreated(): void
    {
        $client = $this->buildClientWithStatus(201);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(fn(Notification $n): bool => $n->getStatus() === 201));

        $hal = new Episciences_Notify_Hal($this->paper, $this->journal, $this->repository, $client);
        $hal->announceEndorsement();
    }

    public function testAnnounceEndorsementOnHttpErrorSavesStatusFailedAndReturnsId(): void
    {
        // Non-201/202 response triggers NotifyException inside COARNotifyClient
        $client = $this->buildClientWithStatus(500);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(fn(Notification $n): bool => $n->getStatus() === Notification::STATUS_FAILED));

        $hal = new Episciences_Notify_Hal($this->paper, $this->journal, $this->repository, $client);
        $id = $hal->announceEndorsement();

        self::assertNotEmpty($id);
        self::assertStringStartsWith('urn:uuid:', $id);
    }

    public function testAnnounceEndorsementWithDoiUsesDoi(): void
    {
        $this->paper->method('hasDoi')->willReturn(true);
        $this->paper->method('getDoi')->willReturn('https://doi.org/10.1234/test');

        $client = $this->buildClientWithStatus(201);

        $savedNotification = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (Notification $n) use (&$savedNotification) {
                $savedNotification = $n;
            });

        $hal = new Episciences_Notify_Hal($this->paper, $this->journal, $this->repository, $client);
        $hal->announceEndorsement();

        self::assertNotNull($savedNotification);
        $original = json_decode($savedNotification->getOriginal(), true);
        self::assertIsArray($original);
    }
}
