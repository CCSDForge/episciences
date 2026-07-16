<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use JsonException;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_Paper::updateNestedJsonDocument().
 *
 * Introduced alongside the "designate main file" feature to persist the
 * newly chosen main file's URL into the PAPERS.DOCUMENT JSON column via
 * JSON_SET, in addition to the relational is_main flag on PAPER_FILES.
 *
 * Regressions specifically guarded against:
 *   - A first version called `$db->prepare(...)` unconditionally, which
 *     fatal-errors when no default adapter is configured. It was corrected
 *     to `$db?->prepare(...) ?? false` (commit 6f7f1415).
 *   - `json_encode()` is called with JSON_UNESCAPED_UNICODE, so non-ASCII
 *     values must not be escaped to \uXXXX sequences in the bound parameter.
 *
 * @covers Episciences_Paper::updateNestedJsonDocument
 */
final class Episciences_Paper_UpdateNestedJsonDocumentTest extends TestCase
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

    private function installAdapter(bool $executeResult = true): JsonDocumentTestAdapter
    {
        $adapter = new JsonDocumentTestAdapter($executeResult);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        return $adapter;
    }

    private function makePaper(int $docId = 42): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setDocid($docId);
        return $paper;
    }

    public function testBuildsAnUpdateStatementOnTheDocumentColumnUsingJsonSet(): void
    {
        $adapter = $this->installAdapter();
        $this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'https://example.org/file.pdf');

        self::assertCount(1, $adapter->prepareCalls);
        $sql = $adapter->prepareCalls[0];

        self::assertStringContainsString('UPDATE `' . T_PAPERS . '`', $sql);
        self::assertStringContainsString('SET `' . Episciences_Paper::JSON_DOCUMENT_COLUMN . '` = JSON_SET(`' . Episciences_Paper::JSON_DOCUMENT_COLUMN . '`, ?, CAST(? AS JSON))', $sql);
        self::assertStringContainsString('WHERE DOCID = ?', $sql);
    }

    public function testExecutesWithThePathEncodedValueAndDocidInOrder(): void
    {
        // json_encode() is not called with JSON_UNESCAPED_SLASHES, so '/' is
        // escaped to '\/' — asserted explicitly so a future flag change is caught.
        $adapter = $this->installAdapter();
        $this->makePaper(1337)->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'https://example.org/file.pdf');

        self::assertCount(1, $adapter->statement->executeCalls);
        self::assertSame(
            ['$.database.current.mainPdfUrl', '"https:\/\/example.org\/file.pdf"', 1337],
            $adapter->statement->executeCalls[0]
        );
    }

    public function testEncodesTheValueWithoutEscapingUnicodeCharacters(): void
    {
        // Regression: JSON_UNESCAPED_UNICODE must be honoured, not é-style escapes.
        $adapter = $this->installAdapter();
        $this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'café.pdf');

        self::assertSame('"café.pdf"', $adapter->statement->executeCalls[0][1]);
    }

    public function testEncodesNonScalarValues(): void
    {
        $adapter = $this->installAdapter();
        $this->makePaper()->updateNestedJsonDocument('$.database.current', ['mainPdfUrl' => 'a.pdf', 'size' => 10]);

        self::assertSame(
            '{"mainPdfUrl":"a.pdf","size":10}',
            $adapter->statement->executeCalls[0][1]
        );
    }

    public function testReturnsTrueWhenTheStatementExecutesSuccessfully(): void
    {
        $this->installAdapter(executeResult: true);

        self::assertTrue($this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'a.pdf'));
    }

    public function testReturnsFalseWhenTheStatementFails(): void
    {
        $this->installAdapter(executeResult: false);

        self::assertFalse($this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'a.pdf'));
    }

    public function testReturnsFalseInsteadOfCrashingWhenNoDefaultAdapterIsConfigured(): void
    {
        // Regression test for the fatal error fixed by the null-safe operator:
        // a missing adapter must degrade to `false`, not a fatal error.
        Zend_Db_Table_Abstract::setDefaultAdapter(null);

        self::assertFalse($this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', 'a.pdf'));
    }

    public function testThrowsWhenTheValueCannotBeJsonEncoded(): void
    {
        // json_encode() is called with JSON_THROW_ON_ERROR: a value it cannot
        // serialize (eg. NAN) must surface as an exception, not a silent false.
        $this->installAdapter();

        $this->expectException(JsonException::class);
        $this->makePaper()->updateNestedJsonDocument('$.database.current.mainPdfUrl', NAN);
    }
}

/**
 * Minimal Zend_Db adapter stub for Episciences_Paper::updateNestedJsonDocument(),
 * which issues a single prepared UPDATE ... JSON_SET(...) statement.
 */
final class JsonDocumentTestAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var array<int, string> */
    public array $prepareCalls = [];

    public JsonDocumentTestStatement $statement;

    public function __construct(bool $executeResult = true)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
        $this->statement = new JsonDocumentTestStatement($executeResult);
    }

    /**
     * @return Zend_Db_Statement_Interface
     */
    public function prepare($sql)
    {
        $this->prepareCalls[] = (string) $sql;
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

    public function query($sql, $bind = []): never
    {
        throw new \RuntimeException('Not used: prepare() is stubbed directly.');
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
 * Minimal Zend_Db_Statement_Interface stub — only execute() is exercised
 * by Episciences_Paper::updateNestedJsonDocument().
 */
final class JsonDocumentTestStatement implements Zend_Db_Statement_Interface
{
    /** @var array<int, array<int, mixed>> */
    public array $executeCalls = [];

    public function __construct(private readonly bool $executeResult) {}

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
        return 0;
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
        $this->executeCalls[] = $params;
        return $this->executeResult;
    }

    public function fetch($style = null, $cursor = null, $offset = null)
    {
        return false;
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