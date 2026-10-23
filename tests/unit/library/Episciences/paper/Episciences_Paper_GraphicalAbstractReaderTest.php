<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_Paper::getGraphical_abstract().
 *
 * Regression: when the JSON v2 export started storing an explicit JSON
 * `null` for an absent graphical_abstract_file (instead of an empty
 * string), `JSON_UNQUOTE(JSON_EXTRACT(...))` started returning the PHP
 * string "null" rather than a SQL NULL. The `is_null($val)` guard let that
 * string through, and paper_graphical_abstract.phtml rendered
 * `src="/public/documents/{docId}/null"` for papers with no abstract.
 *
 * @covers Episciences_Paper::getGraphical_abstract
 */
final class Episciences_Paper_GraphicalAbstractReaderTest extends TestCase
{
    private mixed $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function installAdapter(mixed $fetchValue): void
    {
        $adapter = new GraphicalAbstractTestAdapter($fetchValue);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
    }

    public function testReturnsNullWhenTheJsonValueIsTheLiteralStringNull(): void
    {
        // MySQL's JSON_UNQUOTE(JSON_EXTRACT()) returns the string "null",
        // not a SQL NULL, when the stored JSON value is `null`.
        $this->installAdapter('null');

        self::assertNull((new Episciences_Paper())->getGraphical_abstract(18935));
    }

    public function testReturnsNullWhenTheColumnIsAbsent(): void
    {
        $this->installAdapter(null);

        self::assertNull((new Episciences_Paper())->getGraphical_abstract(18935));
    }

    public function testReturnsTheTrimmedFilenameWhenPresent(): void
    {
        $this->installAdapter(' graphical_abstract.png ');

        self::assertSame('graphical_abstract.png', (new Episciences_Paper())->getGraphical_abstract(18935));
    }
}

/**
 * Minimal Zend_Db adapter stub for Episciences_Paper::getGraphical_abstract(),
 * which issues a single `SELECT JSON_UNQUOTE(JSON_EXTRACT(...))` query and
 * reads the single-column row back via fetch().
 */
final class GraphicalAbstractTestAdapter extends Zend_Db_Adapter_Abstract
{
    private readonly GraphicalAbstractTestStatement $statement;

    public function __construct(private readonly mixed $fetchValue)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
        $this->statement = new GraphicalAbstractTestStatement($this->fetchValue);
    }

    /**
     * @param string $sql
     * @param array<int, mixed> $bind
     * @return Zend_Db_Statement_Interface
     */
    public function query($sql, $bind = []): Zend_Db_Statement_Interface
    {
        return $this->statement;
    }

    /**
     * @return array<int, string>
     */
    public function listTables(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function describeTable($tableName, $schemaName = null): array
    {
        return [];
    }

    protected function _connect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function closeConnection(): void {}

    /**
     * @return Zend_Db_Statement_Interface
     */
    public function prepare($sql)
    {
        return $this->statement;
    }

    public function lastInsertId($tableName = null, $primaryKey = null): string
    {
        return '0';
    }

    protected function _beginTransaction(): void {}

    protected function _commit(): void {}

    protected function _rollBack(): void {}

    public function setFetchMode($mode): void
    {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0): string
    {
        return $sql;
    }

    public function supportsParameters($type): bool
    {
        return false;
    }

    public function getServerVersion(): string
    {
        return 'test';
    }
}

/**
 * Minimal Zend_Db_Statement_Interface stub — only fetch() is exercised
 * by Episciences_Paper::getGraphical_abstract().
 */
final class GraphicalAbstractTestStatement implements Zend_Db_Statement_Interface
{
    public function __construct(private readonly mixed $fetchValue) {}

    public function bindColumn($column, &$param, $type = null)
    {
        return true;
    }

    public function bindParam($parameter, &$variable, $type = null, $length = null, $options = null)
    {
        return true;
    }

    public function bindValue($parameter, $value, $type = null)
    {
        return true;
    }

    public function closeCursor()
    {
        return true;
    }

    public function columnCount()
    {
        return 1;
    }

    public function errorCode()
    {
        return '';
    }

    /**
     * @return array<int, mixed>
     */
    public function errorInfo()
    {
        return [];
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(array $params = [])
    {
        return true;
    }

    /**
     * @return array<int, mixed>
     */
    public function fetch($style = null, $cursor = null, $offset = null)
    {
        return [$this->fetchValue];
    }

    /**
     * @return array<int, mixed>
     */
    public function fetchAll($style = null, $col = null)
    {
        return [];
    }

    public function fetchColumn($col = 0)
    {
        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fetchObject($class = 'stdClass', array $config = [])
    {
        return false;
    }

    public function getAttribute($key)
    {
        return null;
    }

    public function nextRowset()
    {
        return true;
    }

    public function rowCount()
    {
        return 1;
    }

    public function setAttribute($key, $val)
    {
        return true;
    }

    public function setFetchMode($mode)
    {
        return true;
    }
}
