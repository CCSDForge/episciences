<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;

/**
 * Persists dispatch failures into a dedicated table in the main application
 * database — the same connection used for messenger_messages/messenger_failed
 * (see Messenger\Dbal\DbalConnectionFactory) — so a producer-side failure
 * (the dispatch() call itself throwing, before any Messenger row exists)
 * still leaves a durable, replayable trace instead of only a log line.
 *
 * Deliberately append-only on record(): each exhausted dispatch attempt gets
 * its own row rather than being merged into an existing one for the same
 * docid, so the audit trail reflects every occurrence.
 */
final class DbalEnqueueFailureStore implements EnqueueFailureStoreInterface
{
    public const TABLE_NAME = 'solr_enqueue_failures';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(string $action, ?int $docId, int $priority, ?string $solrQuery, string $errorMessage): void
    {
        $this->connection->insert(self::TABLE_NAME, [
            'action' => $action,
            'docid' => $docId,
            'priority' => $priority,
            'solr_query' => $solrQuery,
            'last_error' => $errorMessage,
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s ORDER BY id ASC LIMIT ?', self::TABLE_NAME),
            [$limit],
            [ParameterType::INTEGER]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE id = ?', self::TABLE_NAME),
            [$id]
        );

        return $row === false ? null : $row;
    }

    public function delete(int $id): void
    {
        $this->connection->delete(self::TABLE_NAME, ['id' => $id]);
    }

    public function markRetryFailed(int $id, string $errorMessage): void
    {
        $this->connection->executeStatement(
            sprintf('UPDATE %s SET attempts = attempts + 1, last_error = ?, updated_at = ? WHERE id = ?', self::TABLE_NAME),
            [$errorMessage, date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Creates the table if it doesn't exist yet — called from
     * `solr:queue --setup`, alongside the messenger_messages/messenger_failed
     * setup already done there.
     */
    public function setup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE_NAME])) {
            $schemaManager->createTable($this->buildTable());
        }
    }

    /**
     * @return list<string>
     */
    public function buildCreateTableSql(): array
    {
        return $this->connection->getDatabasePlatform()->getCreateTableSQL($this->buildTable());
    }

    private function buildTable(): \Doctrine\DBAL\Schema\Table
    {
        $schema = new Schema();
        $table = $schema->createTable(self::TABLE_NAME);

        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('action', 'string', ['length' => 16]);
        $table->addColumn('docid', 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('priority', 'integer', ['default' => 0]);
        $table->addColumn('solr_query', 'text', ['notnull' => false]);
        $table->addColumn('last_error', 'text');
        $table->addColumn('attempts', 'integer', ['unsigned' => true, 'default' => 0]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
