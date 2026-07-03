<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger;

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as BridgeConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * Hand-wires Symfony Messenger since this app has no Symfony Kernel/DI
 * container (see scripts/console.php) — everything a normal Symfony app gets
 * for free from FrameworkBundle's MessengerPass is assembled here explicitly.
 *
 * The retry/failure-transport pairing directly fixes the two confirmed legacy
 * reliability bugs: Ccsd_Search_Solr_Indexer's zero-retry behaviour on genuine
 * Solr/network failures (Indexer.php:517-544 swallows the exception, leaving
 * INDEX_QUEUE rows stuck in STATUS='locked' forever), and the resulting lack of
 * any audit trail for what failed (failed envelopes persist in the failure
 * transport table instead of just disappearing on success like INDEX_QUEUE
 * rows do).
 */
final class MessengerFactory
{
    public const TRANSPORT_NAME = 'solr_index';

    public const MESSAGES_TABLE = 'messenger_messages';
    public const FAILED_TABLE = 'messenger_failed';

    // Prevents a crashed worker from blocking a message indefinitely (fixes
    // the legacy bug where INDEX_QUEUE rows got stuck in STATUS='locked' forever).
    private const REDELIVER_TIMEOUT_SECONDS = 3600;

    private const RETRY_MAX_RETRIES = 5;
    private const RETRY_DELAY_MS = 5000;
    private const RETRY_MULTIPLIER = 3.0;
    private const RETRY_MAX_DELAY_MS = 1_800_000;

    public static function createTransport(DbalConnection $connection, bool $autoSetup = false): DoctrineTransport
    {
        return self::buildTransport($connection, self::MESSAGES_TABLE, $autoSetup);
    }

    public static function createFailureTransport(DbalConnection $connection, bool $autoSetup = false): DoctrineTransport
    {
        return self::buildTransport($connection, self::FAILED_TABLE, $autoSetup);
    }

    /**
     * Bus used by producers (the solr:index / solr:delete commands) to enqueue
     * a message without running its handler in the current process.
     */
    public static function createSendBus(DoctrineTransport $transport): MessageBusInterface
    {
        $sendersLocator = new SendersLocator(
            [
                IndexPaperMessage::class => [self::TRANSPORT_NAME],
                DeletePaperMessage::class => [self::TRANSPORT_NAME],
            ],
            new ArrayContainer([self::TRANSPORT_NAME => $transport]),
        );

        return new MessageBus([new SendMessageMiddleware($sendersLocator)]);
    }

    /**
     * Bus used internally by the solr:worker Worker to actually run handlers
     * for envelopes it receives from the transport.
     */
    public static function createHandleBus(
        IndexPaperMessageHandler $indexHandler,
        DeletePaperMessageHandler $deleteHandler,
    ): MessageBusInterface {
        $handlersLocator = new HandlersLocator([
            IndexPaperMessage::class => [$indexHandler],
            DeletePaperMessage::class => [$deleteHandler],
        ]);

        return new MessageBus([new HandleMessageMiddleware($handlersLocator)]);
    }

    /**
     * Event dispatcher wired with the retry-then-dead-letter pipeline, to pass
     * to the solr:worker command's Worker instance.
     */
    public static function createWorkerEventDispatcher(
        DoctrineTransport $transport,
        DoctrineTransport $failureTransport,
        ?LoggerInterface $logger = null,
    ): EventDispatcher {
        $retryStrategy = new MultiplierRetryStrategy(
            self::RETRY_MAX_RETRIES,
            self::RETRY_DELAY_MS,
            self::RETRY_MULTIPLIER,
            self::RETRY_MAX_DELAY_MS,
        );

        $dispatcher = new EventDispatcher();

        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            new ArrayContainer([self::TRANSPORT_NAME => $transport]),
            new ArrayContainer([self::TRANSPORT_NAME => $retryStrategy]),
            $logger,
        ));

        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ArrayContainer([self::TRANSPORT_NAME => $failureTransport]),
            $logger,
        ));

        return $dispatcher;
    }

    private static function buildTransport(DbalConnection $connection, string $tableName, bool $autoSetup): DoctrineTransport
    {
        $bridgeConnection = new BridgeConnection(
            [
                'table_name' => $tableName,
                'redeliver_timeout' => self::REDELIVER_TIMEOUT_SECONDS,
                'auto_setup' => $autoSetup,
            ],
            $connection,
        );

        return new DoctrineTransport($bridgeConnection, new PhpSerializer());
    }
}
