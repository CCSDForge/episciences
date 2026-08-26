<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;

/**
 * The single entry point used to enqueue Solr indexing/deletion work — by the
 * solr:* CLI commands directly, and by every trigger call site (paper
 * publication, deletion, import) indirectly via
 * Episciences\Solr\Indexing\Enqueue\SolrIndexing.
 *
 * Delegates the actual bounded-retry-then-record-failure behaviour to the
 * generic BoundedRetryDispatcher; this class only knows how to turn a
 * (docId, priority) or (docId, solrQuery) pair into the right message and
 * failure-store payload.
 */
final class SolrIndexQueuePort
{
    public function __construct(private readonly BoundedRetryDispatcher $dispatcher)
    {
    }

    public function enqueueIndex(int $docId, int $priority = 0): void
    {
        $this->dispatcher->dispatch(
            new IndexPaperMessage($docId, $priority),
            'index',
            ['docid' => $docId, 'priority' => $priority, 'solr_query' => null]
        );
    }

    public function enqueueDelete(?int $docId = null, ?string $solrQuery = null): void
    {
        // Built outside dispatch() on purpose: DeletePaperMessage's
        // constructor validates its arguments and throws on a caller bug (no
        // usable docId or solrQuery) — that's not a transient send-bus
        // failure, so it must not be retried and recorded as a dispatch
        // failure. Let it propagate immediately instead.
        $message = new DeletePaperMessage($docId, $solrQuery);

        $this->dispatcher->dispatch(
            $message,
            'delete',
            ['docid' => $docId, 'priority' => 0, 'solr_query' => $solrQuery]
        );
    }
}
