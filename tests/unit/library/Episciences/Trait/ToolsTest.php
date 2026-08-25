<?php

namespace unit\library\Episciences\Trait;

use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Episciences\Trait\Tools;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Solr/Indexing/Enqueue/SpyMessageBus.php';
require_once __DIR__ . '/../Solr/Indexing/Enqueue/SpyEnqueueFailureStore.php';
require_once __DIR__ . '/../Solr/Indexing/Enqueue/RestoresSolrIndexingPort.php';

use unit\library\Episciences\Solr\Indexing\Enqueue\RestoresSolrIndexingPort;
use unit\library\Episciences\Solr\Indexing\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Solr\Indexing\Enqueue\SpyMessageBus;

/**
 * Unit tests for Episciences\Trait\Tools::index() — the migrated trigger that
 * enqueues Solr indexing for a published paper via the SolrIndexing facade.
 * No test previously existed for this trait.
 */
class ToolsTest extends TestCase
{
    use RestoresSolrIndexingPort;

    private SpyMessageBus $bus;

    protected function setUp(): void
    {
        $this->captureSolrIndexingPort();
        $this->bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort($this->bus, new SpyEnqueueFailureStore()));
    }

    protected function tearDown(): void
    {
        $this->restoreSolrIndexingPort();
    }

    public function testPublishedPaperEnqueuesAnIndexMessage(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getDocid')->willReturn(123);

        $subject = new class {
            use Tools;
        };

        $subject->index($paper);

        self::assertCount(1, $this->bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $this->bus->dispatched[0]);
        self::assertSame(123, $this->bus->dispatched[0]->docId);
    }

    public function testUnpublishedPaperDispatchesNothing(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isPublished')->willReturn(false);

        $subject = new class {
            use Tools;
        };

        $subject->index($paper);

        self::assertCount(0, $this->bus->dispatched);
    }
}
