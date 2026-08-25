<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Enqueue\EnqueueFailureStoreInterface;

/**
 * Minimal EnqueueFailureStoreInterface test double that records calls
 * in-memory instead of touching a database — used to assert what
 * SolrIndexQueuePort persists once its retry budget is exhausted.
 */
final class SpyEnqueueFailureStore implements EnqueueFailureStoreInterface
{
    /** @var list<array{action: string, docId: ?int, priority: int, solrQuery: ?string, errorMessage: string}> */
    public array $recorded = [];

    public function record(string $action, ?int $docId, int $priority, ?string $solrQuery, string $errorMessage): void
    {
        $this->recorded[] = [
            'action' => $action,
            'docId' => $docId,
            'priority' => $priority,
            'solrQuery' => $solrQuery,
            'errorMessage' => $errorMessage,
        ];
    }
}
