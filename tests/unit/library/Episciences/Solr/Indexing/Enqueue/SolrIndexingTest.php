<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SpyMessageBus.php';

/**
 * Unit tests for the SolrIndexing facade — the seam every legacy trigger call
 * site (Trait\Tools::index(), PapersManager::delete(), etc.) uses to enqueue
 * work without any constructor-injection point of its own.
 */
class SolrIndexingTest extends TestCase
{
    protected function tearDown(): void
    {
        // Avoid leaking an injected spy into unrelated tests run afterwards.
        SolrIndexing::setPort(new SolrIndexQueuePort(new SpyMessageBus()));
    }

    public function testEnqueueIndexDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort($bus));

        SolrIndexing::enqueueIndex(42, 3);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $bus->dispatched[0]);
        self::assertSame(42, $bus->dispatched[0]->docId);
        self::assertSame(3, $bus->dispatched[0]->priority);
    }

    public function testEnqueueDeleteDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort($bus));

        SolrIndexing::enqueueDelete(docId: 7);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(DeletePaperMessage::class, $bus->dispatched[0]);
        self::assertSame(7, $bus->dispatched[0]->docId);
    }

    public function testGetPortReturnsTheInjectedInstance(): void
    {
        $port = new SolrIndexQueuePort(new SpyMessageBus());
        SolrIndexing::setPort($port);

        self::assertSame($port, SolrIndexing::getPort());
    }
}
