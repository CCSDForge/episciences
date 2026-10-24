<?php

declare(strict_types=1);

namespace unit\modules\support;

use LogicException;
use stdClass;
use Zend_Db;
use Zend_Db_Statement_Interface;

/**
 * Result of a query answered by RecordingDbAdapter: the programmed rows, read with the fetch modes
 * Zend_Db relies on (associative, numeric, both, column, object).
 */
final class ArrayStatement implements Zend_Db_Statement_Interface
{
    private int $cursor = 0;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(private readonly array $rows, private int $fetchMode = Zend_Db::FETCH_ASSOC)
    {
    }

    /**
     * @param string|int $column
     * @param mixed $param
     * @param mixed $type
     */
    public function bindColumn($column, &$param, $type = null): bool
    {
        throw new LogicException('Column binding is not supported by the recording adapter');
    }

    /**
     * @param string|int $parameter
     * @param mixed $variable
     * @param mixed $type
     * @param mixed $length
     * @param mixed $options
     */
    public function bindParam($parameter, &$variable, $type = null, $length = null, $options = null): bool
    {
        return true;
    }

    /**
     * @param string|int $parameter
     * @param mixed $value
     * @param mixed $type
     */
    public function bindValue($parameter, $value, $type = null): bool
    {
        return true;
    }

    public function closeCursor(): bool
    {
        $this->cursor = count($this->rows);

        return true;
    }

    public function columnCount(): int
    {
        return count($this->rows[0] ?? []);
    }

    public function errorCode(): string
    {
        return '00000';
    }

    /**
     * @return array{string, null, null}
     */
    public function errorInfo(): array
    {
        return ['00000', null, null];
    }

    /**
     * @param array<mixed> $params
     */
    public function execute(array $params = []): bool
    {
        return true;
    }

    /**
     * @param int|null $style
     * @param mixed $cursor
     * @param mixed $offset
     * @return array<int|string, mixed>|stdClass|false
     */
    public function fetch($style = null, $cursor = null, $offset = null): array|stdClass|false
    {
        $row = $this->rows[$this->cursor] ?? null;
        if ($row === null) {
            return false;
        }
        $this->cursor++;

        return $this->shape($row, $style ?? $this->fetchMode);
    }

    /**
     * @param int|null $style
     * @param int|null $col
     * @return list<mixed>
     */
    public function fetchAll($style = null, $col = null): array
    {
        $style ??= $this->fetchMode;
        $remaining = array_slice($this->rows, $this->cursor);
        $this->cursor = count($this->rows);

        if ($style === Zend_Db::FETCH_COLUMN) {
            return array_map(static fn(array $row): mixed => array_values($row)[(int)$col] ?? null, $remaining);
        }

        return array_map(fn(array $row): array|stdClass => $this->shape($row, $style), $remaining);
    }

    /**
     * @param int $col
     */
    public function fetchColumn($col = 0): mixed
    {
        $row = $this->fetch(Zend_Db::FETCH_NUM);

        return is_array($row) && array_key_exists($col, $row) ? $row[$col] : false;
    }

    /**
     * @param string $class
     * @param array<mixed> $config
     */
    public function fetchObject($class = 'stdClass', array $config = []): object|false
    {
        $row = $this->fetch(Zend_Db::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }

        $object = new $class(...array_values($config));
        foreach ($row as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }

    /**
     * @param string $key
     */
    public function getAttribute($key): mixed
    {
        return null;
    }

    public function nextRowset(): bool
    {
        return false;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * @param string $key
     * @param mixed $val
     */
    public function setAttribute($key, $val): bool
    {
        return true;
    }

    /**
     * @param int $mode
     */
    public function setFetchMode($mode): bool
    {
        $this->fetchMode = (int)$mode;

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int|string, mixed>|stdClass
     */
    private function shape(array $row, int $style): array|stdClass
    {
        return match ($style) {
            Zend_Db::FETCH_ASSOC => $row,
            Zend_Db::FETCH_NUM => array_values($row),
            Zend_Db::FETCH_BOTH => array_merge($row, array_values($row)),
            Zend_Db::FETCH_OBJ => (object)$row,
            default => throw new LogicException(sprintf('Fetch mode %d is not supported by the recording adapter', $style)),
        };
    }
}
