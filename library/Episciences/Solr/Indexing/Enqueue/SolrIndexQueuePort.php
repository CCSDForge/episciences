<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The single entry point used to enqueue Solr indexing/deletion work — by the
 * solr:* CLI commands directly, and by every trigger call site (paper
 * publication, deletion, import) indirectly via
 * Episciences\Solr\Indexing\Enqueue\SolrIndexing.
 */
final class SolrIndexQueuePort
{
    public function __construct(private readonly MessageBusInterface $sendBus)
    {
    }

    public function enqueueIndex(int $docId, int $priority = 0): void
    {
        $this->sendBus->dispatch(new IndexPaperMessage($docId, $priority));
    }

    public function enqueueDelete(?int $docId = null, ?string $solrQuery = null): void
    {
        $this->sendBus->dispatch(new DeletePaperMessage($docId, $solrQuery));
    }
}
