<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Doctrine\DBAL\Schema\Table;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;

/**
 * Solr-specific dispatch-failure store: adds the docid/priority/solr_query
 * payload columns on top of the generic id/action/retry_attempts/last_error/
 * created_at/updated_at shape.
 */
final class DbalEnqueueFailureStore extends AbstractDbalEnqueueFailureStore
{
    public const TABLE_NAME = 'solr_enqueue_failures';

    public function tableName(): string
    {
        return self::TABLE_NAME;
    }

    protected function addPayloadColumns(Table $table): void
    {
        $table->addColumn('docid', 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('priority', 'integer', ['default' => 0]);
        $table->addColumn('solr_query', 'text', ['notnull' => false]);
    }
}
