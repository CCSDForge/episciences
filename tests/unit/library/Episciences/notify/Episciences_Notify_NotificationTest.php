<?php

namespace unit\library\Episciences\notify;

use Episciences\Notify\Notification;
use PHPUnit\Framework\TestCase;

class Episciences_Notify_NotificationTest extends TestCase
{
    public function testDirectionConstants(): void
    {
        self::assertSame('INBOUND', Notification::DIRECTION_INBOUND);
        self::assertSame('OUTBOUND', Notification::DIRECTION_OUTBOUND);
    }

    public function testGettersAndSetters(): void
    {
        $notification = new Notification();

        $notification->setId('urn:uuid:123e4567-e89b-12d3-a456-426614174000');
        self::assertSame('urn:uuid:123e4567-e89b-12d3-a456-426614174000', $notification->getId());

        $notification->setFromId('https://journal.example.org');
        self::assertSame('https://journal.example.org', $notification->getFromId());

        $notification->setToId('https://hal.science');
        self::assertSame('https://hal.science', $notification->getToId());

        $notification->setInReplyToId('urn:uuid:abc');
        self::assertSame('urn:uuid:abc', $notification->getInReplyToId());

        $notification->setInReplyToId(null);
        self::assertNull($notification->getInReplyToId());

        $notification->setType('["Announce","coar-notify:EndorsementAction"]');
        self::assertSame('["Announce","coar-notify:EndorsementAction"]', $notification->getType());

        $notification->setStatus(201);
        self::assertSame(201, $notification->getStatus());

        $notification->setOriginal('{"@context":"https://www.w3.org/ns/activitystreams"}');
        self::assertSame('{"@context":"https://www.w3.org/ns/activitystreams"}', $notification->getOriginal());

        $notification->setDirection(Notification::DIRECTION_OUTBOUND);
        self::assertSame('OUTBOUND', $notification->getDirection());
    }

    public function testDefaultStatus(): void
    {
        $notification = new Notification();
        // status defaults to 0 — cannot access it before setters are called on typed props
        // but status has a default value of 0
        $notification->setId('urn:uuid:test');
        $notification->setFromId('https://from.example.org');
        $notification->setToId('https://to.example.org');
        $notification->setType('["Announce"]');
        $notification->setOriginal('{}');
        $notification->setDirection(Notification::DIRECTION_INBOUND);

        self::assertSame(0, $notification->getStatus());
    }

    public function testFromRow(): void
    {
        $row = [
            'id'          => 'urn:uuid:550e8400-e29b-41d4-a716-446655440000',
            'fromId'      => 'https://origin.example.org',
            'toId'        => 'https://target.example.org',
            'inReplyToId' => null,
            'type'        => '["Announce","coar-notify:EndorsementAction"]',
            'status'      => '201',
            'original'    => '{"@context":["https://www.w3.org/ns/activitystreams"]}',
            'direction'   => 'OUTBOUND',
        ];

        $notification = Notification::fromRow($row);

        self::assertInstanceOf(Notification::class, $notification);
        self::assertSame('urn:uuid:550e8400-e29b-41d4-a716-446655440000', $notification->getId());
        self::assertSame('https://origin.example.org', $notification->getFromId());
        self::assertSame('https://target.example.org', $notification->getToId());
        self::assertNull($notification->getInReplyToId());
        self::assertSame('["Announce","coar-notify:EndorsementAction"]', $notification->getType());
        self::assertSame(201, $notification->getStatus());
        self::assertSame('{"@context":["https://www.w3.org/ns/activitystreams"]}', $notification->getOriginal());
        self::assertSame('OUTBOUND', $notification->getDirection());
    }

    public function testFromRowWithInReplyToId(): void
    {
        $row = [
            'id'          => 'urn:uuid:abc',
            'fromId'      => 'https://from.example.org',
            'toId'        => 'https://to.example.org',
            'inReplyToId' => 'urn:uuid:original',
            'type'        => '["Announce"]',
            'status'      => '0',
            'original'    => '{}',
            'direction'   => 'INBOUND',
        ];

        $notification = Notification::fromRow($row);

        self::assertSame('urn:uuid:original', $notification->getInReplyToId());
        self::assertSame(0, $notification->getStatus());
        self::assertSame(Notification::DIRECTION_INBOUND, $notification->getDirection());
    }
}
