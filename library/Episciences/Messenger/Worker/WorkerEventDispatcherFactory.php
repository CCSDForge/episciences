<?php

declare(strict_types=1);

namespace Episciences\Messenger\Worker;

use Episciences\Messenger\ArrayContainer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

/**
 * Builds the event dispatcher wired with the retry-then-dead-letter pipeline,
 * to pass to a worker command's Worker instance.
 *
 * Invariant (implicit in Symfony Messenger's own listeners, made explicit
 * here): the same $transportName string is used as the receiver name in
 * Worker, the key of the senders container, the key of the retry-strategies
 * container, and the key of the failure-transport container. All four MUST
 * agree, or SendFailedMessageForRetryListener/SendFailedMessageToFailureTransportListener
 * silently fail to find their entry.
 */
final class WorkerEventDispatcherFactory
{
    public static function create(
        string $transportName,
        DoctrineTransport $transport,
        DoctrineTransport $failureTransport,
        RetryStrategyInterface $retryStrategy,
        ?LoggerInterface $logger = null,
    ): EventDispatcher {
        $dispatcher = new EventDispatcher();

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            new ArrayContainer([$transportName => $transport]),
            new ArrayContainer([$transportName => $retryStrategy]),
            $logger,
        ));

        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ArrayContainer([$transportName => $failureTransport]),
            $logger,
        ));

        return $dispatcher;
    }
}
