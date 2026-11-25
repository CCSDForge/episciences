<?php

declare(strict_types=1);

namespace Episciences\Messenger\Bus;

use Episciences\Messenger\ArrayContainer;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

final class BusFactory
{
    /**
     * Bus used by producers to enqueue a message without running its handler
     * in the current process. Every class in $messageClasses is routed to the
     * same single transport — a queue never mixes producers with different
     * routing needs.
     *
     * @param list<class-string> $messageClasses
     */
    public static function createSendBus(string $transportName, DoctrineTransport $transport, array $messageClasses): MessageBusInterface
    {
        $sendersLocator = new SendersLocator(
            array_fill_keys($messageClasses, [$transportName]),
            new ArrayContainer([$transportName => $transport]),
        );

        return new MessageBus([new SendMessageMiddleware($sendersLocator)]);
    }

    /**
     * Bus used internally by a worker to actually run handlers for envelopes
     * it receives from the transport.
     *
     * @param array<class-string, iterable<callable>> $handlers
     */
    public static function createHandleBus(array $handlers): MessageBusInterface
    {
        return new MessageBus([new HandleMessageMiddleware(new HandlersLocator($handlers))]);
    }
}
