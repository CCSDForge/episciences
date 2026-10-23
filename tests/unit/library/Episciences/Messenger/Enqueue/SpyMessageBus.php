<?php

namespace unit\library\Episciences\Messenger\Enqueue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Minimal MessageBusInterface test double that records dispatched messages
 * instead of routing them anywhere — used to assert what a facade/trigger
 * call site enqueues without needing a real transport/DB.
 */
final class SpyMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }
}
