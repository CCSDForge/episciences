<?php

namespace unit\library\Episciences\notify;

use Episciences\Notify\Notification;
use Episciences\Notify\NotificationsRepository;
use Episciences_Notify_Reader;
use PHPUnit\Framework\TestCase;

class Episciences_Notify_ReaderTest extends TestCase
{
    public function testGetRepositoryReturnsInjectedRepository(): void
    {
        $repository = $this->createMock(NotificationsRepository::class);
        $reader = new Episciences_Notify_Reader($repository);

        self::assertSame($repository, $reader->getRepository());
    }

    public function testGetNotificationsDelegatesToRepository(): void
    {
        $notification = $this->createMock(Notification::class);

        $repository = $this->createMock(NotificationsRepository::class);
        $repository
            ->expects(self::once())
            ->method('findInbound')
            ->willReturn([$notification]);

        $reader = new Episciences_Notify_Reader($repository);
        $result = $reader->getNotifications();

        self::assertCount(1, $result);
        self::assertSame($notification, $result[0]);
    }

    public function testGetNotificationsReturnsEmptyArray(): void
    {
        $repository = $this->createMock(NotificationsRepository::class);
        $repository->method('findInbound')->willReturn([]);

        $reader = new Episciences_Notify_Reader($repository);
        $result = $reader->getNotifications();

        self::assertSame([], $result);
    }

    public function testGetNotificationsCallsFindInbound(): void
    {
        $repository = $this->createMock(NotificationsRepository::class);
        $repository
            ->expects(self::once())
            ->method('findInbound')
            ->willReturn([]);

        $reader = new Episciences_Notify_Reader($repository);
        $reader->getNotifications();
    }
}
