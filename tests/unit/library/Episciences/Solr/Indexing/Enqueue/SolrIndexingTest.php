<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SpyMessageBus.php';
require_once __DIR__ . '/SpyEnqueueFailureStore.php';
require_once __DIR__ . '/RestoresSolrIndexingPort.php';

/**
 * Unit tests for the SolrIndexing facade — the seam every legacy trigger call
 * site (Trait\Tools::index(), PapersManager::delete(), etc.) uses to enqueue
 * work without any constructor-injection point of its own.
 */
class SolrIndexingTest extends TestCase
{
    use RestoresSolrIndexingPort;

    protected function setUp(): void
    {
        $this->captureSolrIndexingPort();
    }

    protected function tearDown(): void
    {
        $this->restoreSolrIndexingPort();
    }

    public function testEnqueueIndexDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort($bus, new SpyEnqueueFailureStore()));

        SolrIndexing::enqueueIndex(42, 3);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $bus->dispatched[0]);
        self::assertSame(42, $bus->dispatched[0]->docId);
        self::assertSame(3, $bus->dispatched[0]->priority);
    }

    public function testEnqueueDeleteDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort($bus, new SpyEnqueueFailureStore()));

        SolrIndexing::enqueueDelete(docId: 7);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(DeletePaperMessage::class, $bus->dispatched[0]);
        self::assertSame(7, $bus->dispatched[0]->docId);
    }

    public function testGetPortReturnsTheInjectedInstance(): void
    {
        $port = new SolrIndexQueuePort(new SpyMessageBus(), new SpyEnqueueFailureStore());
        SolrIndexing::setPort($port);

        self::assertSame($port, SolrIndexing::getPort());
    }
}
