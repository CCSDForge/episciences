<?php

namespace unit\library\Episciences\notify;

use Episciences\Notify\CoarNotifyHttpResponse;
use PHPUnit\Framework\TestCase;

class CoarNotifyHttpResponseTest extends TestCase
{
    public function testGetHeaderReturnsValueWhenPresent(): void
    {
        $response = new CoarNotifyHttpResponse(201, ['Location' => 'https://hal.example/inbox/123']);

        self::assertSame('https://hal.example/inbox/123', $response->getHeader('Location'));
    }

    /**
     * coarnotify\client\COARNotifyClient::send() unconditionally calls getHeader("Location")
     * on every 201 response, even when the inbox didn't send one. This must not warn/crash.
     */
    public function testGetHeaderReturnsNullWhenMissing(): void
    {
        $response = new CoarNotifyHttpResponse(201, ['Content-Type' => 'application/ld+json']);

        self::assertNull($response->getHeader('Location'));
    }

    public function testGetStatusCode(): void
    {
        $response = new CoarNotifyHttpResponse(202, []);

        self::assertSame(202, $response->getStatusCode());
    }
}