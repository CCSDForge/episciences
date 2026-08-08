<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper_File;
use Episciences_Paper_FilesManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_Paper_FilesManager::getMainFile() / findByType() / findById().
 *
 * @covers Episciences_Paper_FilesManager::getMainFile
 * @covers Episciences_Paper_FilesManager::findByType
 * @covers Episciences_Paper_FilesManager::findById
 */
final class Episciences_Paper_FilesManager_MainFileTest extends TestCase
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

    /**
     * @param array<int, array<string, mixed>> $fetchRowResults
     * @param array<int, array<int|string, array<string, mixed>>> $fetchAssocResults
     */
    private function installAdapter(array $fetchRowResults = [], array $fetchAssocResults = []): MainFileTestAdapter
    {
        $adapter = new MainFileTestAdapter($fetchRowResults, $fetchAssocResults);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        return $adapter;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRow(int $id, int $docId, bool $isMain, string $fileType = 'pdf', int $fileSize = 100): array
    {
        return [
            'id' => $id,
            'doc_id' => $docId,
            'file_name' => "file-$id.pdf",
            'checksum' => 'abc',
            'checksum_type' => 'MD5',
            'self_link' => "#link-$id",
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'source' => 1,
            'is_main' => $isMain ? 1 : 0,
            'time_modified' => '2026-01-01 00:00:00',
        ];
    }

    // -----------------------------------------------------------------------
    // getMainFile() — strict mode
    // -----------------------------------------------------------------------

    public function testStrictReturnsNullWhenNoFileIsFlaggedMain(): void
    {
        $adapter = $this->installAdapter([[]]);

        $result = Episciences_Paper_FilesManager::getMainFile(1, true);

        self::assertNull($result);
        self::assertCount(1, $adapter->fetchRowCalls, 'Strict mode must not run the fallback query.');
    }

    public function testStrictReturnsTheFlaggedFile(): void
    {
        $row = $this->makeRow(10, 1, true);
        $adapter = $this->installAdapter([$row]);

        $result = Episciences_Paper_FilesManager::getMainFile(1, true);

        self::assertInstanceOf(Episciences_Paper_File::class, $result);
        self::assertSame(10, $result->getId());
        self::assertCount(1, $adapter->fetchRowCalls);
    }

    public function testStrictQueryFiltersOnIsMainAndOrdersByMostRecentlyModified(): void
    {
        // Regression guard: if several files were ever left flagged is_main=1
        // (see PaperController::savemasterfileAction bug fix), the most
        // recently modified one must win rather than an arbitrary row.
        $adapter = $this->installAdapter([[]]);

        Episciences_Paper_FilesManager::getMainFile(1, true);

        self::assertStringContainsString('is_main = 1', $adapter->fetchRowCalls[0]);
        self::assertStringContainsString('ORDER BY "time_modified" DESC', $adapter->fetchRowCalls[0]);
    }

    // -----------------------------------------------------------------------
    // getMainFile() — non-strict mode (fallback to largest PDF)
    // -----------------------------------------------------------------------

    public function testNonStrictReturnsFlaggedFileWithoutRunningFallback(): void
    {
        $row = $this->makeRow(11, 1, true);
        $adapter = $this->installAdapter([$row]);

        $result = Episciences_Paper_FilesManager::getMainFile(1);

        self::assertSame(11, $result->getId());
        self::assertCount(1, $adapter->fetchRowCalls, 'The fallback query must not run once a flagged file was found.');
    }

    public function testNonStrictFallsBackToLargestPdfWhenNoneFlagged(): void
    {
        $fallbackRow = $this->makeRow(12, 1, false, 'pdf', 500);
        $adapter = $this->installAdapter([[], $fallbackRow]);

        $result = Episciences_Paper_FilesManager::getMainFile(1);

        self::assertSame(12, $result->getId());
        self::assertCount(2, $adapter->fetchRowCalls);
        self::assertStringContainsString("file_type = 'pdf'", $adapter->fetchRowCalls[1]);
        self::assertStringContainsString('ORDER BY "file_size" DESC', $adapter->fetchRowCalls[1]);
    }

    public function testNonStrictReturnsNullWhenNothingFlaggedAndNoPdfExists(): void
    {
        $adapter = $this->installAdapter([[], []]);

        $result = Episciences_Paper_FilesManager::getMainFile(1);

        self::assertNull($result);
        self::assertCount(2, $adapter->fetchRowCalls);
    }

    // -----------------------------------------------------------------------
    // findByType()
    // -----------------------------------------------------------------------

    public function testFindByTypeReturnsFilesKeyedById(): void
    {
        $row1 = $this->makeRow(1, 1, false);
        $row2 = $this->makeRow(2, 1, false);
        $adapter = $this->installAdapter([], [[1 => $row1, 2 => $row2]]);

        $result = Episciences_Paper_FilesManager::findByType(1);

        self::assertCount(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(2, $result);
        self::assertInstanceOf(Episciences_Paper_File::class, $result[1]);
    }

    public function testFindByTypeDefaultsToPdf(): void
    {
        $adapter = $this->installAdapter([], [[]]);

        Episciences_Paper_FilesManager::findByType(1);

        self::assertStringContainsString("file_type = 'pdf'", $adapter->fetchAssocCalls[0]);
    }

    public function testFindByTypeHonoursExplicitType(): void
    {
        $adapter = $this->installAdapter([], [[]]);

        Episciences_Paper_FilesManager::findByType(1, 'zip');

        self::assertStringContainsString("file_type = 'zip'", $adapter->fetchAssocCalls[0]);
    }

    public function testFindByTypeReturnsEmptyArrayWhenNoFilesMatch(): void
    {
        $adapter = $this->installAdapter([], [[]]);

        $result = Episciences_Paper_FilesManager::findByType(1);

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // findById()
    // -----------------------------------------------------------------------

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->installAdapter([[]]);

        self::assertNull(Episciences_Paper_FilesManager::findById(999));
    }

    public function testFindByIdReturnsMatchingFile(): void
    {
        $row = $this->makeRow(5, 1, false);
        $adapter = $this->installAdapter([$row]);

        $result = Episciences_Paper_FilesManager::findById(5);

        self::assertInstanceOf(Episciences_Paper_File::class, $result);
        self::assertSame(5, $result->getId());
        self::assertStringContainsString('id = 5', $adapter->fetchRowCalls[0]);
    }
}

/**
 * Minimal Zend_Db adapter stub returning pre-configured rows per call order,
 * while recording the assembled SQL of every fetchRow()/fetchAssoc() call so
 * tests can assert on WHERE/ORDER BY clauses without a real database.
 */
final class MainFileTestAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var string[] */
    public array $fetchRowCalls = [];
    /** @var string[] */
    public array $fetchAssocCalls = [];

    /**
     * @param array<int, array<string, mixed>> $fetchRowResults
     * @param array<int, array<int|string, array<string, mixed>>> $fetchAssocResults
     */
    public function __construct(
        private array $fetchRowResults = [],
        private array $fetchAssocResults = []
    ) {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    private function toSqlString(Zend_Db_Select|string $sql): string
    {
        return $sql instanceof Zend_Db_Select ? $sql->assemble() : $sql;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchRow($sql, $bind = [], $fetchMode = null)
    {
        $this->fetchRowCalls[] = $this->toSqlString($sql);
        return array_shift($this->fetchRowResults) ?? [];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchAssoc($sql, $bind = [])
    {
        $this->fetchAssocCalls[] = $this->toSqlString($sql);
        return array_shift($this->fetchAssocResults) ?? [];
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
        throw new \RuntimeException('Not used: fetchRow()/fetchAssoc() are stubbed directly.');
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