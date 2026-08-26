<?php

declare(strict_types=1);

namespace Episciences\Messenger\Enqueue;

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\Messenger\Bus\BusFactory;
use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Messenger\Transport\TransportFactory;
use Psr\Log\LoggerInterface;

/**
 * Shared send-bus + BoundedRetryDispatcher wiring for the enqueue-side
 * facades (Episciences\Solr\Indexing\Enqueue\SolrIndexing,
 * Episciences\Next\RevalidationService): both built the same
 * DbalConnectionFactory -> TransportFactory -> BusFactory ->
 * BoundedRetryDispatcher chain by hand, differing only in the
 * TransportConfig/message classes/failure store passed in.
 */
final class DispatcherFactory
{
    /** @param list<class-string> $messageClasses */
    public static function create(
        DbalConnection $connection,
        TransportConfig $config,
        array $messageClasses,
        EnqueueFailureStoreInterface $failureStore,
        ?LoggerInterface $logger = null,
    ): BoundedRetryDispatcher {
        $transport = TransportFactory::createTransport($connection, $config);
        $sendBus = BusFactory::createSendBus($config->name, $transport, $messageClasses);

        return new BoundedRetryDispatcher($sendBus, $failureStore, $logger);
    }
}
