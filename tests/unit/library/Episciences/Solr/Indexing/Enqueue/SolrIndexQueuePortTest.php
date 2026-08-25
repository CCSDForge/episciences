<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SpyMessageBus.php';
require_once __DIR__ . '/FlakyMessageBus.php';
require_once __DIR__ . '/SpyEnqueueFailureStore.php';

/**
 * Unit tests for SolrIndexQueuePort's bounded dispatch retry and its
 * fallback to EnqueueFailureStoreInterface once that retry is exhausted —
 * the producer-side durability gap Messenger's own retry/failure transport
 * cannot cover (no message row exists yet to retry).
 */
class SolrIndexQueuePortTest extends TestCase
{
    public function testEnqueueIndexDispatchesImmediatelyOnSuccess(): void
    {
        $bus = new SpyMessageBus();
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort($bus, $failureStore);

        $port->enqueueIndex(42, 3);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $bus->dispatched[0]);
        self::assertSame([], $failureStore->recorded);
    }

    public function testEnqueueIndexSucceedsAfterATransientFailure(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 1);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort($bus, $failureStore);

        $port->enqueueIndex(42);

        self::assertCount(1, $bus->dispatched);
        self::assertSame([], $failureStore->recorded);
    }

    public function testEnqueueIndexRecordsFailureOnceRetryIsExhaustedAndDoesNotThrow(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort($bus, $failureStore);

        $port->enqueueIndex(42, 5);

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('index', $failureStore->recorded[0]['action']);
        self::assertSame(42, $failureStore->recorded[0]['docId']);
        self::assertSame(5, $failureStore->recorded[0]['priority']);
        self::assertStringContainsString('transient failure', $failureStore->recorded[0]['errorMessage']);
    }

    public function testEnqueueDeleteRecordsFailureOnceRetryIsExhausted(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort($bus, $failureStore);

        $port->enqueueDelete(7, null);

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('delete', $failureStore->recorded[0]['action']);
        self::assertSame(7, $failureStore->recorded[0]['docId']);
    }

    public function testEnqueueDeleteDispatchesAsDeletePaperMessageOnSuccess(): void
    {
        $bus = new SpyMessageBus();
        $port = new SolrIndexQueuePort($bus, new SpyEnqueueFailureStore());

        $port->enqueueDelete(null, '*:*');

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(DeletePaperMessage::class, $bus->dispatched[0]);
        self::assertSame('*:*', $bus->dispatched[0]->solrQuery);
    }
}
