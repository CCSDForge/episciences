<?php

declare(strict_types=1);

namespace unit\library\Episciences\support;

use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * In-memory `pages` table behind a partially mocked database adapter.
 *
 * Selects are answered from the rows of the table as MySQL would: only the requested columns are
 * returned, under their alias (e.g. `'visibility' => 'visibility_set'`), filtered by the
 * `column = value` conditions of the query. Inserts, updates and deletes are recorded and applied, so a
 * test can check what the code reads and what it writes without a database.
 */
trait PagesTableDoubleTrait
{
    /** @var list<array<string, mixed>> rows of the `pages` table */
    private array $pagesRows = [];

    /** @var list<array{string, string, mixed, mixed}> [method, table, data, where] of each write */
    private array $pagesWrites = [];

    private ?Zend_Db_Adapter_Abstract $adapterBeforePagesTable = null;

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function installPagesTable(array $rows): void
    {
        $this->pagesRows = $rows;
        $this->pagesWrites = [];
        $this->adapterBeforePagesTable = Zend_Db_Table_Abstract::getDefaultAdapter();

        $adapter = $this->getMockBuilder($this->adapterBeforePagesTable::class)
            ->setConstructorArgs([$this->adapterBeforePagesTable->getConfig()])
            ->onlyMethods(['fetchAll', 'fetchRow', 'insert', 'update', 'delete', 'lastInsertId'])
            ->getMock();

        $adapter->method('fetchAll')->willReturnCallback(fn(Zend_Db_Select $select): array => $this->selectPages($select));
        $adapter->method('fetchRow')->willReturnCallback(fn(Zend_Db_Select $select): array|false => $this->selectPages($select)[0] ?? false);
        $adapter->method('lastInsertId')->willReturnCallback(fn(): int => count($this->pagesRows));
        $adapter->method('insert')->willReturnCallback(function (string $table, array $data): int {
            $this->pagesWrites[] = ['insert', $table, $data, null];
            $this->pagesRows[] = $data;
            return 1;
        });
        $adapter->method('update')->willReturnCallback(function (string $table, array $data, mixed $where = ''): int {
            $this->pagesWrites[] = ['update', $table, $data, $where];
            return $this->applyToMatchingRows((array)$where, fn(array $row): array => array_merge($row, $data));
        });
        $adapter->method('delete')->willReturnCallback(function (string $table, mixed $where = ''): int {
            $this->pagesWrites[] = ['delete', $table, null, $where];
            return $this->applyToMatchingRows((array)$where, fn(array $row): ?array => null);
        });

        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
    }

    private function uninstallPagesTable(): void
    {
        if ($this->adapterBeforePagesTable !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->adapterBeforePagesTable);
            $this->adapterBeforePagesTable = null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectPages(Zend_Db_Select $select): array
    {
        $conditions = [];
        foreach ($select->getPart(Zend_Db_Select::WHERE) as $where) {
            if (preg_match("/(\\w+) = '([^']*)'/", (string)$where, $match)) {
                $conditions[$match[1]] = $match[2];
            }
        }

        $result = [];
        foreach ($this->pagesRows as $row) {
            if (!$this->rowMatches($row, $conditions)) {
                continue;
            }

            $selected = [];
            foreach ($select->getPart(Zend_Db_Select::COLUMNS) as [, $column, $alias]) {
                if ($column === '*') {
                    $selected = array_merge($selected, $row);
                    continue;
                }
                $selected[$alias ?? $column] = $row[$column] ?? null;
            }
            $result[] = $selected;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $conditions
     */
    private function rowMatches(array $row, array $conditions): bool
    {
        foreach ($conditions as $column => $value) {
            if ((string)($row[$column] ?? '') !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $where Zend style conditions: ['code = ?' => 'abc']
     * @param callable(array<string, mixed>): ?array<string, mixed> $change null removes the row
     */
    private function applyToMatchingRows(array $where, callable $change): int
    {
        $conditions = [];
        foreach ($where as $condition => $value) {
            $conditions[trim(str_replace('= ?', '', (string)$condition))] = (string)$value;
        }

        $count = 0;
        $rows = [];
        foreach ($this->pagesRows as $row) {
            if ($this->rowMatches($row, $conditions)) {
                $count++;
                $row = $change($row);
            }
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        $this->pagesRows = $rows;

        return $count;
    }

    /**
     * Writes of one kind, in order
     *
     * @return list<array{string, string, mixed, mixed}>
     */
    private function pagesWritesOf(string $method): array
    {
        return array_values(array_filter($this->pagesWrites, static fn(array $write): bool => $write[0] === $method));
    }

    /**
     * A row of the `pages` table, both visibility columns filled as after the migration
     *
     * @return array<string, mixed>
     */
    private static function pagesRow(int $id, string $code, string $pageCode, string $visibilitySet): array
    {
        return [
            'id' => $id,
            'code' => $code,
            'uid' => 1,
            'date_creation' => '2026-01-01 00:00:00',
            'date_updated' => '2026-01-01 00:00:00',
            'title' => '{"en":"Title"}',
            'content' => '{"en":"Content"}',
            'page_code' => $pageCode,
            'visibility' => json_encode(explode(',', $visibilitySet)),
            'visibility_set' => $visibilitySet,
        ];
    }
}
