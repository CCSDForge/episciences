<?php

declare(strict_types=1);

namespace Episciences\Messenger\Enqueue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

/**
 * Persists dispatch failures into a dedicated table in the main application
 * database — the same connection used for messenger_messages/messenger_failed
 * (see Dbal\DbalConnectionFactory) — so a producer-side failure (the
 * dispatch() call itself throwing, before any Messenger row exists) still
 * leaves a durable, replayable trace instead of only a log line.
 *
 * Deliberately append-only on record(): each exhausted dispatch attempt gets
 * its own row rather than being merged into an existing one, so the audit
 * trail reflects every occurrence.
 *
 * Column order is id, action, <payload columns>, retry_attempts, last_error,
 * created_at, updated_at — subclasses only need to describe the payload
 * columns specific to their domain (docid/priority/solr_query for Solr,
 * rvcode/tag for Next.js revalidation).
 */
abstract class AbstractDbalEnqueueFailureStore implements EnqueueFailureStoreInterface
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    abstract public function tableName(): string;

    /** @param array<string, mixed> $payload */
    public function record(string $action, array $payload, string $errorMessage): void
    {
        $now = date('Y-m-d H:i:s');

        $this->connection->insert($this->tableName(), array_merge(
            ['action' => $action],
            $payload,
            [
                'last_error' => $errorMessage,
                'retry_attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ));
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s ORDER BY id ASC LIMIT ?', $this->tableName()),
            [$limit],
            [ParameterType::INTEGER]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE id = ?', $this->tableName()),
            [$id]
        );

        return $row === false ? null : $row;
    }

    public function delete(int $id): void
    {
        $this->connection->delete($this->tableName(), ['id' => $id]);
    }

    /**
     * `retry_attempts` (not `attempts`): record() always inserts 0 regardless
     * of how many producer-side retries were already burned before the
     * failure was recorded — this column counts only retries of the
     * recorded failure itself, via --retry-dispatch-failure.
     */
    public function markRetryFailed(int $id, string $errorMessage): void
    {
        $this->connection->executeStatement(
            sprintf('UPDATE %s SET retry_attempts = retry_attempts + 1, last_error = ?, updated_at = ? WHERE id = ?', $this->tableName()),
            [$errorMessage, date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Creates the table if it doesn't exist yet — called from
     * `episciences:queue --setup`.
     */
    public function setup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$this->tableName()])) {
            $schemaManager->createTable($this->buildTable());
        }
    }

    /** @return list<string> */
    public function buildCreateTableSql(): array
    {
        return $this->connection->getDatabasePlatform()->getCreateTableSQL($this->buildTable());
    }

    private function buildTable(): Table
    {
        $table = $this->baseTable();
        $this->addPayloadColumns($table);
        $this->finishTable($table);

        return $table;
    }

    private function baseTable(): Table
    {
        $schema = new Schema();
        $table = $schema->createTable($this->tableName());

        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('action', 'string', ['length' => 16]);

        return $table;
    }

    /**
     * Adds the columns specific to this domain's payload, between the base
     * id/action columns and the shared retry_attempts/last_error/created_at/
     * updated_at columns added by finishTable().
     */
    abstract protected function addPayloadColumns(Table $table): void;

    private function finishTable(Table $table): void
    {
        $table->addColumn('retry_attempts', 'integer', ['unsigned' => true, 'default' => 0]);
        $table->addColumn('last_error', 'text');
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
    }
}
