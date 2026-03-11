<?php

namespace unit\library\Episciences\notify;

use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use PHPUnit\Framework\TestCase;

class Episciences_Notify_NotificationsRepositoryTest extends TestCase
{
    public function testFindInboundQueriesCorrectly(): void
    {
        $rows = [
            [
                'id'          => 'urn:uuid:abc',
                'fromId'      => 'https://from.example.org',
                'toId'        => 'https://to.example.org',
                'inReplyToId' => null,
                'type'        => '["Offer","coar-notify:ReviewAction"]',
                'status'      => '202',
                'original'    => '{}',
                'direction'   => 'INBOUND',
            ]
        ];

        // bindValue is called twice: once for :direction, once for :limit
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(self::exactly(2))
            ->method('bindValue');
        $stmt->expects(self::once())
            ->method('execute');
        $stmt->expects(self::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($rows);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('WHERE direction = :direction'))
            ->willReturn($stmt);

        $repository = new NotificationsRepository($pdo);
        $result = $repository->findInbound();

        self::assertCount(1, $result);
        self::assertInstanceOf(Notification::class, $result[0]);
        self::assertSame('urn:uuid:abc', $result[0]->getId());
        self::assertSame(Notification::DIRECTION_INBOUND, $result[0]->getDirection());
    }

    public function testFindInboundReturnsEmptyArray(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('bindValue');
        $stmt->method('execute');
        $stmt->expects(self::once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = new NotificationsRepository($pdo);
        $result = $repository->findInbound();

        self::assertSame([], $result);
    }

    public function testSaveExecutesInsert(): void
    {
        $notification = new Notification();
        $notification->setId('urn:uuid:save-test');
        $notification->setFromId('https://from.example.org');
        $notification->setToId('https://to.example.org');
        $notification->setInReplyToId(null);
        $notification->setType('["Announce","coar-notify:EndorsementAction"]');
        $notification->setStatus(201);
        $notification->setOriginal('{"id":"urn:uuid:save-test"}');
        $notification->setDirection(Notification::DIRECTION_OUTBOUND);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(self::exactly(8))
            ->method('bindValue');
        $stmt->expects(self::once())
            ->method('execute');

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('INSERT INTO notifications'))
            ->willReturn($stmt);

        $repository = new NotificationsRepository($pdo);
        $repository->save($notification);
    }

    public function testDeleteByIdExecutesDelete(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(self::once())
            ->method('bindValue')
            ->with(':id', 'urn:uuid:del-test', \PDO::PARAM_STR);
        $stmt->expects(self::once())
            ->method('execute');

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('DELETE FROM notifications WHERE id = :id'))
            ->willReturn($stmt);

        $repository = new NotificationsRepository($pdo);
        $repository->deleteById('urn:uuid:del-test');
    }
}
