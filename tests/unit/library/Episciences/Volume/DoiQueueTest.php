<?php

declare(strict_types=1);

namespace unit\library\Episciences\Volume;

use Episciences\Volume\DoiQueue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Volume\DoiQueue
 */
final class DoiQueueTest extends TestCase
{
    public function testFromRow(): void
    {
        $doiQueue = DoiQueue::fromRow(['id' => '3', 'vid' => '1134', 'doi_status' => 'requested', 'date_init' => '2026-11-01 10:00:00']);

        self::assertSame(3, $doiQueue->getId());
        self::assertSame(1134, $doiQueue->getVid());
        self::assertSame(DoiQueue::STATUS_REQUESTED, $doiQueue->getStatus());
    }

    public function testEmptyStoredStatusMeansNotAssigned(): void
    {
        self::assertSame(DoiQueue::STATUS_NOT_ASSIGNED, DoiQueue::fromRow(['vid' => '1', 'doi_status' => ''])->getStatus());
        self::assertSame(DoiQueue::STATUS_NOT_ASSIGNED, (new DoiQueue(1))->getStatus());
        self::assertNull((new DoiQueue(1))->getId());
    }

    /**
     * @dataProvider lockProvider
     */
    public function testIsLocked(string $status, bool $locked): void
    {
        self::assertSame($locked, (new DoiQueue(1, $status))->isLocked());
        self::assertSame($locked, DoiQueue::isLockedStatus($status));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function lockProvider(): array
    {
        return [
            'not assigned' => [DoiQueue::STATUS_NOT_ASSIGNED, false],
            'assigned' => [DoiQueue::STATUS_ASSIGNED, false],
            'requested' => [DoiQueue::STATUS_REQUESTED, true],
            'public' => [DoiQueue::STATUS_PUBLIC, true],
        ];
    }

    public function testSetStatus(): void
    {
        $doiQueue = new DoiQueue(1, DoiQueue::STATUS_ASSIGNED);

        self::assertSame($doiQueue, $doiQueue->setStatus(DoiQueue::STATUS_REQUESTED));
        self::assertSame(DoiQueue::STATUS_REQUESTED, $doiQueue->getStatus());
    }

    public function testUnknownStatusIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DoiQueue(1, 'manual');
    }

    public function testEveryStoredStatusHasABadge(): void
    {
        self::assertSame(DoiQueue::STORED_STATUSES, array_keys(DoiQueue::STATUS_BADGES));
    }
}
