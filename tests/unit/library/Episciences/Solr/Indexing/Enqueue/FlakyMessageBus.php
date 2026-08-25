<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * MessageBusInterface test double that throws on the first N dispatch() calls
 * before succeeding — used to exercise SolrIndexQueuePort's bounded retry.
 */
final class FlakyMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    private int $callCount = 0;

    public function __construct(private readonly int $failuresBeforeSuccess)
    {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->callCount++;

        if ($this->callCount <= $this->failuresBeforeSuccess) {
            throw new RuntimeException(sprintf('transient failure #%d', $this->callCount));
        }

        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }
}
