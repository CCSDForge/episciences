<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper_File;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_Paper_File::isMain() / setIsMain() / save().
 *
 * save() upserts the file's full row (see Episciences_Paper_FilesManager's
 * unique_docid_self_link key) purely to toggle `is_main`. A prior version of
 * this method quoted the raw PHP boolean instead of the string '0'/'1',
 * which produced an empty string ('') for `false` and could trip MySQL's
 * strict mode ("Incorrect integer value: '' for column 'is_main'").
 *
 * @covers Episciences_Paper_File::isMain
 * @covers Episciences_Paper_File::setIsMain
 * @covers Episciences_Paper_File::save
 */
final class Episciences_Paper_File_MainFlagTest extends TestCase
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

    private function installAdapter(int $rowCount = 1): FileSaveTestAdapter
    {
        $adapter = new FileSaveTestAdapter($rowCount);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        return $adapter;
    }

    private function makeFile(bool $isMain = false): Episciences_Paper_File
    {
        return new Episciences_Paper_File([
            'id' => 1,
            'doc_id' => 42,
            'file_name' => 'article.pdf',
            'checksum' => 'abc123',
            'checksum_type' => 'MD5',
            'self_link' => '#link',
            'file_size' => 1000,
            'file_type' => 'pdf',
            'source' => 1,
            'is_main' => $isMain ? 1 : 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // isMain() / setIsMain()
    // -----------------------------------------------------------------------

    public function testIsMainDefaultsToFalse(): void
    {
        $file = new Episciences_Paper_File();
        self::assertFalse($file->isMain());
    }

    public function testSetIsMainDefaultsToFalseWhenCalledWithoutArgument(): void
    {
        $file = $this->makeFile(true);
        self::assertTrue($file->isMain());

        $file->setIsMain();

        self::assertFalse($file->isMain());
    }

    public function testSetIsMainIsFluent(): void
    {
        $file = new Episciences_Paper_File();
        self::assertSame($file, $file->setIsMain(true));
    }

    public function testHydratingFromARowSetsIsMainFromTheIsMainColumn(): void
    {
        self::assertTrue($this->makeFile(true)->isMain());
        self::assertFalse($this->makeFile(false)->isMain());
    }

    // -----------------------------------------------------------------------
    // save() — SQL shape
    // -----------------------------------------------------------------------

    public function testSaveQuotesIsMainTrueAsStringOne(): void
    {
        $adapter = $this->installAdapter();
        $this->makeFile(true)->save();

        self::assertCount(1, $adapter->queryCalls);
        self::assertStringContainsString("'1')", $adapter->queryCalls[0]);
    }

    public function testSaveQuotesIsMainFalseAsStringZeroNotEmptyString(): void
    {
        // Regression test: quote(false) used to produce '' (empty string),
        // not '0', which MySQL's strict mode rejects for a boolean column.
        $adapter = $this->installAdapter();
        $this->makeFile(false)->save();

        self::assertStringContainsString("'0')", $adapter->queryCalls[0]);
        self::assertStringNotContainsString("'')", $adapter->queryCalls[0]);
    }

    public function testSaveBuildsAnUpsertOnTheUniqueDocIdSelfLinkKey(): void
    {
        $adapter = $this->installAdapter();
        $this->makeFile(true)->save();

        $sql = $adapter->queryCalls[0];
        self::assertStringContainsString('INSERT INTO', $sql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        self::assertStringContainsString('is_main = new_file.is_main', $sql);
    }

    public function testSaveReturnsAffectedRowsFromTheAdapter(): void
    {
        $this->installAdapter(rowCount: 1);

        self::assertSame(1, $this->makeFile(true)->save());
    }

    public function testSaveReturnsZeroWhenNoRowsWereAffected(): void
    {
        $this->installAdapter(rowCount: 0);

        self::assertSame(0, $this->makeFile(true)->save());
    }
}

/**
 * Minimal Zend_Db adapter stub for Episciences_Paper_File::save(), which
 * issues a single raw INSERT ... ON DUPLICATE KEY UPDATE via query().
 */
final class FileSaveTestAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var array<int, string> */
    public array $queryCalls = [];

    public function __construct(private readonly int $rowCount = 1)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    public function query($sql, $bind = []): Zend_Db_Statement_Interface
    {
        $this->queryCalls[] = (string) $sql;

        return new FakeRowCountStatement($this->rowCount);
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

    public function prepare($sql): never
    {
        throw new \RuntimeException('Not used: query() is stubbed directly.');
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
 * Minimal Zend_Db_Statement_Interface stub — only rowCount() is exercised
 * by Episciences_Paper_File::save().
 */
final class FakeRowCountStatement implements Zend_Db_Statement_Interface
{
    public function __construct(private readonly int $rowCount) {}

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
        return true;
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
        return $this->rowCount;
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