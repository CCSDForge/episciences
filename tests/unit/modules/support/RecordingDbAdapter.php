<?php

declare(strict_types=1);

namespace unit\modules\support;

use Closure;
use LogicException;
use Zend_Db_Adapter_Pdo_Mysql;
use Zend_Db_Select;

/**
 * Database adapter that never connects: queries are built for real, recorded, and answered with
 * the rows programmed by the test.
 *
 * Reads all go through query(), so a programmed answer is a list of rows whatever the fetch
 * method: fetchRow() returns the first one, fetchCol() their first column, fetchOne() the first
 * column of the first row, and so on. A query no rule matches gets no row.
 * Inserts, updates and deletes are recorded as they are called, with their table, values and
 * where clause, and are not turned into SQL.
 */
final class RecordingDbAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    /** @var list<array{pattern: string, rows: list<array<string, mixed>>|Closure(string, array<mixed>): list<array<string, mixed>>}> */
    private array $rules = [];

    /** @var list<array{sql: string, bind: array<mixed>}> */
    private array $queries = [];

    /** @var list<array{table: mixed, values: array<string, mixed>}> */
    private array $inserts = [];

    /** @var list<array{table: mixed, values: array<string, mixed>, where: mixed}> */
    private array $updates = [];

    /** @var list<array{table: mixed, where: mixed}> */
    private array $deletes = [];

    /** @var list<'begin'|'commit'|'rollback'> */
    private array $transactions = [];

    private int $affectedRows = 1;

    public function __construct()
    {
        // Only required by the parent constructor: nothing ever connects
        parent::__construct(['dbname' => 'unit_tests', 'username' => 'unit_tests', 'password' => '']);
    }

    /**
     * Answer the queries whose SQL matches $pattern (a regular expression) with $rows.
     *
     * Rules are tried in the order they were added: the first match answers.
     *
     * @param list<array<string, mixed>>|Closure(string, array<mixed>): list<array<string, mixed>> $rows
     *        the rows, or a callback computing them from the SQL and its bound values
     */
    public function respondTo(string $pattern, array|Closure $rows): self
    {
        $this->rules[] = ['pattern' => $pattern, 'rows' => $rows];

        return $this;
    }

    /**
     * Number of rows that inserts, updates and deletes report as affected (1 by default).
     */
    public function setAffectedRows(int $affectedRows): self
    {
        $this->affectedRows = $affectedRows;

        return $this;
    }

    /**
     * @return list<array{sql: string, bind: array<mixed>}> every query sent, in order
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<string> SQL of the SELECT statements sent, in order
     */
    public function selects(): array
    {
        $selects = array_filter(
            array_column($this->queries, 'sql'),
            static fn(string $sql): bool => (bool)preg_match('/^\s*SELECT\b/i', $sql)
        );

        return array_values($selects);
    }

    /**
     * @return list<array{table: mixed, values: array<string, mixed>}>
     */
    public function inserts(): array
    {
        return $this->inserts;
    }

    /**
     * @return list<array{table: mixed, values: array<string, mixed>, where: mixed}>
     */
    public function updates(): array
    {
        return $this->updates;
    }

    /**
     * @return list<array{table: mixed, where: mixed}>
     */
    public function deletes(): array
    {
        return $this->deletes;
    }

    /**
     * @return list<'begin'|'commit'|'rollback'> transaction calls, in order
     */
    public function transactions(): array
    {
        return $this->transactions;
    }

    /**
     * @param string|Zend_Db_Select $sql
     * @param mixed $bind
     */
    public function query($sql, $bind = []): ArrayStatement
    {
        if ($sql instanceof Zend_Db_Select) {
            if (empty($bind)) {
                $bind = $sql->getBind();
            }
            $sql = $sql->assemble();
        }

        $sql = (string)$sql;
        $bind = is_array($bind) ? $bind : [$bind];
        $this->queries[] = ['sql' => $sql, 'bind' => $bind];

        return new ArrayStatement($this->rowsFor($sql, $bind), (int)$this->_fetchMode);
    }

    /**
     * @param mixed $table
     * @param array<string, mixed> $bind
     */
    public function insert($table, array $bind): int
    {
        $this->inserts[] = ['table' => $table, 'values' => $bind];

        return $this->affectedRows;
    }

    /**
     * @param mixed $table
     * @param array<string, mixed> $bind
     * @param mixed $where
     */
    public function update($table, array $bind, $where = ''): int
    {
        $this->updates[] = ['table' => $table, 'values' => $bind, 'where' => $where];

        return $this->affectedRows;
    }

    /**
     * @param mixed $table
     * @param mixed $where
     */
    public function delete($table, $where = ''): int
    {
        $this->deletes[] = ['table' => $table, 'where' => $where];

        return $this->affectedRows;
    }

    /**
     * @param string $sql
     */
    public function exec($sql): int
    {
        $this->queries[] = ['sql' => (string)$sql, 'bind' => []];

        return $this->affectedRows;
    }

    /**
     * Number of inserts recorded so far, as the id of the last inserted row.
     *
     * @param string|null $tableName
     * @param string|null $primaryKey
     */
    public function lastInsertId($tableName = null, $primaryKey = null): string
    {
        return (string)count($this->inserts);
    }

    /**
     * @param string|Zend_Db_Select $sql
     */
    public function prepare($sql): never
    {
        throw new LogicException('Prepared statements are not supported by the recording adapter: program the code under test through query()');
    }

    public function getServerVersion(): string
    {
        return '8.4.0';
    }

    protected function _connect(): void
    {
        // Nothing to connect to: quote() calls this before quoting
    }

    protected function _beginTransaction(): void
    {
        $this->transactions[] = 'begin';
    }

    protected function _commit(): void
    {
        $this->transactions[] = 'commit';
    }

    protected function _rollBack(): void
    {
        $this->transactions[] = 'rollback';
    }

    /**
     * Same escaping as Zend_Db_Adapter_Abstract: the PDO one needs a connection.
     *
     * @param mixed $value
     */
    protected function _quote($value): int|string
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return sprintf('%F', $value);
        }

        return "'" . addcslashes((string)$value, "\000\n\r\\'\"\032") . "'";
    }

    /**
     * @param array<mixed> $bind
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $sql, array $bind): array
    {
        foreach ($this->rules as $rule) {
            if (preg_match($rule['pattern'], $sql)) {
                return $rule['rows'] instanceof Closure ? ($rule['rows'])($sql, $bind) : $rule['rows'];
            }
        }

        return [];
    }
}
