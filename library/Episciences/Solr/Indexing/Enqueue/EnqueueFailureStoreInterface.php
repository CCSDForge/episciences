<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

/**
 * Durable record of a dispatch that SolrIndexQueuePort could not enqueue even
 * after its bounded retry — the one case Messenger's own retry/failure
 * transport cannot cover, since no message row was ever created to retry.
 */
interface EnqueueFailureStoreInterface
{
    public function record(string $action, ?int $docId, int $priority, ?string $solrQuery, string $errorMessage): void;
}
