<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger\Message;

use InvalidArgumentException;

/**
 * Asks the queue worker to delete one paper from Solr, either by docid (parity
 * with the legacy INDEX_QUEUE ORIGIN='DELETE' row) or by a raw Solr delete
 * query (parity with solrJob.php's --delete "<query>").
 */
final class DeletePaperMessage
{
    public readonly ?string $solrQuery;

    public function __construct(
        public readonly ?int $docId = null,
        ?string $solrQuery = null,
    ) {
        // A blank/whitespace-only query is not a valid Solr query and would
        // otherwise silently take priority over a valid docId in
        // toSolrDeleteQuery() below — normalise it to null instead.
        $this->solrQuery = $solrQuery !== null && trim($solrQuery) !== '' ? $solrQuery : null;

        if ($this->docId === null && $this->solrQuery === null) {
            throw new InvalidArgumentException('DeletePaperMessage requires either a docId or a solrQuery.');
        }
    }

    public function toSolrDeleteQuery(): string
    {
        return $this->solrQuery ?? sprintf('docid:%d', $this->docId);
    }
}
