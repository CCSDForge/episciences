<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger\Message;

/**
 * Asks the queue worker to (re)index one paper. Replaces the legacy
 * INDEX_QUEUE row (DOCID, ORIGIN='UPDATE', PRIORITY) — the priority is carried
 * on the message itself rather than a mutable queue-table column.
 */
final class IndexPaperMessage
{
    public function __construct(
        public readonly int $docId,
        public readonly int $priority = 0,
    ) {
    }
}
