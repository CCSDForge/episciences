<?php

declare(strict_types=1);

namespace Episciences\Next\Enqueue;

use Doctrine\DBAL\Schema\Table;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;

/**
 * Next.js revalidation-specific dispatch-failure store: adds the rvcode/tag
 * payload columns on top of the generic id/action/retry_attempts/last_error/
 * created_at/updated_at shape.
 */
final class DbalNextRevalidationFailureStore extends AbstractDbalEnqueueFailureStore
{
    public const TABLE_NAME = 'next_revalidation_enqueue_failures';

    public function tableName(): string
    {
        return self::TABLE_NAME;
    }

    protected function addPayloadColumns(Table $table): void
    {
        $table->addColumn('rvcode', 'string', ['length' => 50]);
        $table->addColumn('tag', 'string', ['length' => 190]);
    }
}
