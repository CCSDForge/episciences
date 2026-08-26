<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use Zend_Db_Table_Abstract;

require_once __DIR__ . '/../../../Messenger/Enqueue/SpyMessageBus.php';
require_once __DIR__ . '/../../../Messenger/Enqueue/SpyEnqueueFailureStore.php';
require_once __DIR__ . '/../../../Messenger/Enqueue/RestoresStaticQueuePort.php';

/**
 * Unit tests for the SolrIndexing facade — the seam every legacy trigger call
 * site (Trait\Tools::index(), PapersManager::delete(), etc.) uses to enqueue
 * work without any constructor-injection point of its own.
 */
class SolrIndexingTest extends TestCase
{
    use RestoresStaticQueuePort;

    protected function setUp(): void
    {
        $this->captureStaticQueuePort(SolrIndexing::class);
    }

    protected function tearDown(): void
    {
        $this->restoreStaticQueuePort(SolrIndexing::class);
    }

    public function testEnqueueIndexDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        SolrIndexing::enqueueIndex(42, 3);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(IndexPaperMessage::class, $bus->dispatched[0]);
        self::assertSame(42, $bus->dispatched[0]->docId);
        self::assertSame(3, $bus->dispatched[0]->priority);
    }

    public function testEnqueueDeleteDelegatesToInjectedPort(): void
    {
        $bus = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher($bus, new SpyEnqueueFailureStore())));

        SolrIndexing::enqueueDelete(docId: 7);

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(DeletePaperMessage::class, $bus->dispatched[0]);
        self::assertSame(7, $bus->dispatched[0]->docId);
    }

    public function testGetPortReturnsTheInjectedInstance(): void
    {
        $port = new SolrIndexQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore()));
        SolrIndexing::setPort($port);

        self::assertSame($port, SolrIndexing::getPort());
    }

    public function testEnqueueDeleteWithNoUsableInputStillThrowsThroughTheFacade(): void
    {
        // A caller bug (no docId, no solrQuery) must still propagate through
        // SolrIndexing — only a failure to *build* the port is swallowed,
        // not a legitimate exception from the port's own call.
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

        $this->expectException(InvalidArgumentException::class);

        SolrIndexing::enqueueDelete(null, null);
    }

    // =========================================================================
    // Best-effort — an infrastructure failure building the port (not just a
    // send failure, already covered by BoundedRetryDispatcherTest) must not
    // propagate to the caller, which has already committed its own change.
    // =========================================================================

    #[RunInSeparateProcess]
    public function testEnqueueIndexDoesNotThrowWhenThePortCannotBeBuilt(): void
    {
        // No default adapter registered in this fresh process:
        // DbalConnectionFactory::fromZendAdapter(null) throws a TypeError,
        // exercising the exact failure tryGetPort() is meant to swallow.
        Zend_Db_Table_Abstract::setDefaultAdapter(null);

        SolrIndexing::enqueueIndex(42);

        $this->addToAssertionCount(1);
    }

    #[RunInSeparateProcess]
    public function testEnqueueDeleteDoesNotThrowWhenThePortCannotBeBuilt(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter(null);

        SolrIndexing::enqueueDelete(7);

        $this->addToAssertionCount(1);
    }
}
