<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Messenger\Enqueue\FlakyMessageBus;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;

require_once __DIR__ . '/../../../Messenger/Enqueue/SpyMessageBus.php';
require_once __DIR__ . '/../../../Messenger/Enqueue/FlakyMessageBus.php';
require_once __DIR__ . '/../../../Messenger/Enqueue/SpyEnqueueFailureStore.php';

/**
 * Unit tests for SolrIndexQueuePort: it must turn a (docId, priority) or
 * (docId, solrQuery) pair into the right message and failure-store payload,
 * and forward both to its BoundedRetryDispatcher. The bounded-retry/record
 * mechanics themselves are covered generically by BoundedRetryDispatcherTest.
 */
class SolrIndexQueuePortTest extends TestCase
{
    public function testEnqueueIndexDispatchesImmediatelyOnSuccess(): void
    {
        $bus = new SpyMessageBus();
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, $failureStore));

        $port->enqueueIndex(42, 3);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $bus->dispatched[0]);
        self::assertSame([], $failureStore->recorded);
    }

    public function testEnqueueIndexRecordsFailureOnceRetryIsExhaustedAndDoesNotThrow(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, $failureStore));

        $port->enqueueIndex(42, 5);

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('index', $failureStore->recorded[0]['action']);
        self::assertSame(42, $failureStore->recorded[0]['payload']['docid']);
        self::assertSame(5, $failureStore->recorded[0]['payload']['priority']);
        self::assertStringContainsString('transient failure', $failureStore->recorded[0]['errorMessage']);
    }

    public function testEnqueueDeleteRecordsFailureOnceRetryIsExhausted(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, $failureStore));

        $port->enqueueDelete(7, null);

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('delete', $failureStore->recorded[0]['action']);
        self::assertSame(7, $failureStore->recorded[0]['payload']['docid']);
    }

    public function testEnqueueDeleteDispatchesAsDeletePaperMessageOnSuccess(): void
    {
        $bus = new SpyMessageBus();
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueDelete(null, '*:*');

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(DeletePaperMessage::class, $bus->dispatched[0]);
        self::assertSame('*:*', $bus->dispatched[0]->solrQuery);
    }

    public function testEnqueueDeleteWithNoUsableInputThrowsImmediatelyInsteadOfBeingRetriedAndRecorded(): void
    {
        $bus = new SpyMessageBus();
        $failureStore = new SpyEnqueueFailureStore();
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, $failureStore));

        $this->expectException(InvalidArgumentException::class);

        try {
            $port->enqueueDelete(null, null);
        } finally {
            // A caller bug (no docId, no solrQuery) is not a transient
            // send-bus failure: it must not be retried, must not reach the
            // bus, and must not be recorded as a dispatch failure.
            self::assertCount(0, $bus->dispatched);
            self::assertSame([], $failureStore->recorded);
        }
    }
}
