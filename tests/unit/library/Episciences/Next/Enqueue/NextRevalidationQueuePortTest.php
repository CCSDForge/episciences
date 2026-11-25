<?php

namespace unit\library\Episciences\Next\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Messenger\Enqueue\FlakyMessageBus;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;

require_once __DIR__ . '/../../Messenger/Enqueue/SpyMessageBus.php';
require_once __DIR__ . '/../../Messenger/Enqueue/FlakyMessageBus.php';
require_once __DIR__ . '/../../Messenger/Enqueue/SpyEnqueueFailureStore.php';

/**
 * Unit tests for NextRevalidationQueuePort: it must turn a (rvcode, tag) pair
 * into a RevalidateTagMessage and the right failure-store payload, silently
 * skip blank input instead of letting the message constructor throw, and
 * deduplicate repeat (rvcode, tag) pairs for its own lifetime (R6). The
 * bounded-retry/record mechanics themselves are covered generically by
 * BoundedRetryDispatcherTest.
 */
class NextRevalidationQueuePortTest extends TestCase
{
    public function testEnqueueTagDispatchesTheMessage(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTag('epijinfo', 'article-42');

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(RevalidateTagMessage::class, $bus->dispatched[0]);
        self::assertSame('epijinfo', $bus->dispatched[0]->rvcode);
        self::assertSame('article-42', $bus->dispatched[0]->tag);
    }

    public function testEnqueueTagsDispatchesOneMessagePerTag(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTags('epijinfo', ['article-42', 'volumes-epijinfo']);

        self::assertCount(2, $bus->dispatched);
    }

    public function testEnqueueTagIgnoresBlankTagWithoutDispatching(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTag('epijinfo', '   ');

        self::assertSame([], $bus->dispatched);
    }

    public function testEnqueueTagIgnoresBlankRvcodeWithoutDispatching(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTag('', 'article-42');

        self::assertSame([], $bus->dispatched);
    }

    public function testEnqueueTagDeduplicatesTheSamePairWithinTheSamePortInstance(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTag('epijinfo', 'article-42');
        $port->enqueueTag('epijinfo', 'article-42');

        self::assertCount(1, $bus->dispatched);
    }

    public function testEnqueueTagStillDispatchesTheSameTagForADifferentJournal(): void
    {
        $bus = new SpyMessageBus();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore()));

        $port->enqueueTag('epijinfo', 'article-42');
        $port->enqueueTag('epirevo', 'article-42');

        self::assertCount(2, $bus->dispatched);
    }

    public function testEnqueueTagRecordsFailureOnceRetryIsExhausted(): void
    {
        $bus = new FlakyMessageBus(failuresBeforeSuccess: 99);
        $failureStore = new SpyEnqueueFailureStore();
        $port = new NextRevalidationQueuePort(new BoundedRetryDispatcher($bus, $failureStore));

        $port->enqueueTag('epijinfo', 'article-42');

        self::assertCount(0, $bus->dispatched);
        self::assertCount(1, $failureStore->recorded);
        self::assertSame('revalidate', $failureStore->recorded[0]['action']);
        self::assertSame('epijinfo', $failureStore->recorded[0]['payload']['rvcode']);
        self::assertSame('article-42', $failureStore->recorded[0]['payload']['tag']);
    }
}
