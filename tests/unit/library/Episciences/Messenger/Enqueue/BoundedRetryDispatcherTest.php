<?php

namespace unit\library\Episciences\Messenger\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use PHPUnit\Framework\TestCase;
use stdClass;

require_once __DIR__ . '/SpyMessageBus.php';
require_once __DIR__ . '/FlakyMessageBus.php';
require_once __DIR__ . '/SpyEnqueueFailureStore.php';

/**
 * Unit tests for BoundedRetryDispatcher's bounded dispatch retry and its
 * fallback to EnqueueFailureStoreInterface once that retry is exhausted —
 * the producer-side durability gap Messenger's own retry/failure transport
 * cannot cover (no message row exists yet to retry). Generalized out of the
 * Solr-only SolrIndexQueuePortTest so both Solr indexing and Next.js
 * revalidation enqueue ports share the same coverage.
 */
class BoundedRetryDispatcherTest extends TestCase
{
    public function testDispatchesImmediatelyOnSuccess(): void
    {
        $bus = new SpyMessageBus();
        $failureStore = new SpyEnqueueFailureStore();
        $dispatcher = new BoundedRetryDispatcher($bus, $failureStore);

        $message = new stdClass();
        $dispatcher->dispatch($message, 'index', ['docid' => 42]);

        self::assertCount(1, $bus->dispatched);
        self::assertSame($message, $bus->dispatched[0]);
        self::assertSame([], $failureStore->recorded);
    }

    public function testSucceedsAfterATransientFailure(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 1);
        $failureStore = new SpyEnqueueFailureStore();
        $dispatcher = new BoundedRetryDispatcher($bus, $failureStore);

        $dispatcher->dispatch(new stdClass(), 'index', ['docid' => 42]);

        self::assertCount(1, $bus->dispatched);
        self::assertSame([], $failureStore->recorded);
    }

    public function testRecordsFailureOnceRetryIsExhaustedAndDoesNotThrow(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $dispatcher = new BoundedRetryDispatcher($bus, $failureStore);

        $dispatcher->dispatch(new stdClass(), 'index', ['docid' => 42, 'priority' => 5]);

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('index', $failureStore->recorded[0]['action']);
        self::assertSame(['docid' => 42, 'priority' => 5], $failureStore->recorded[0]['payload']);
        self::assertStringContainsString('transient failure', $failureStore->recorded[0]['errorMessage']);
    }
}
