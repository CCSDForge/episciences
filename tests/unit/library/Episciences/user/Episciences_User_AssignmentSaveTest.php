<?php

declare(strict_types=1);

namespace unit\library\Episciences\user;

use Episciences_User_Assignment;
use Episciences_User_AssignmentsManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_User_Assignment::save().
 *
 * The save() method talks to the database through
 * Zend_Db_Table_Abstract::getDefaultAdapter(). These tests install a stub
 * adapter that captures the generated SQL so we can assert the shape of the
 * INSERT/UPDATE without a real database.
 *
 * Regression covered: RT#294893 — during an UPDATE the value of WHEN must be
 * preserved. WHEN is only timestamped (NOW()) on INSERT, never on UPDATE.
 *
 * @covers Episciences_User_Assignment::save
 */
final class Episciences_User_AssignmentSaveTest extends TestCase
{
    private mixed $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        Episciences_User_AssignmentsManager::setCachePool(new ArrayAdapter());
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function installAdapter(int $rowCount = 1, int $lastId = 0): AssignmentSaveTestAdapter
    {
        $adapter = new AssignmentSaveTestAdapter($rowCount, $lastId);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        return $adapter;
    }

    private function makeAssignment(array $opts = []): Episciences_User_Assignment
    {
        return new Episciences_User_Assignment(array_merge([
            'invitation_id' => 1,
            'itemid' => 10,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'rvid' => 1,
            'uid' => 42,
            'tmp_user' => false,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_ACTIVE,
            'deadline' => null,
        ], $opts));
    }

    // -----------------------------------------------------------------------
    // Insert path (no id) — WHEN must be timestamped with NOW()
    // -----------------------------------------------------------------------

    public function testSaveWithoutIdIssuesInsertWithWhenTimestamp(): void
    {
        $adapter = $this->installAdapter(rowCount: 1, lastId: 7);
        $assignment = $this->makeAssignment();

        $result = $assignment->save();

        self::assertTrue($result);
        self::assertSame(7, $assignment->getId());
        self::assertCount(1, $adapter->queryCalls);
        self::assertStringContainsString('INSERT INTO', $adapter->queryCalls[0]);
        self::assertStringContainsString('WHEN', $adapter->queryCalls[0]);
        self::assertStringContainsString('NOW()', $adapter->queryCalls[0]);
    }

    public function testSaveWithoutIdSetsIdFromLastInsertId(): void
    {
        $this->installAdapter(rowCount: 1, lastId: 123);
        $assignment = $this->makeAssignment();

        $assignment->save();

        self::assertSame(123, $assignment->getId());
    }

    public function testSaveReturnsFalseWhenInsertAffectsNoRows(): void
    {
        $this->installAdapter(rowCount: 0, lastId: 9);
        $assignment = $this->makeAssignment();

        $result = $assignment->save();

        self::assertFalse($result);
        self::assertNull($assignment->getId());
    }

    // -----------------------------------------------------------------------
    // Update path (id set) — WHEN must be left untouched (RT#294893)
    // -----------------------------------------------------------------------

    public function testSaveWithIdIssuesUpdateWithoutWhen(): void
    {
        $adapter = $this->installAdapter();
        $assignment = $this->makeAssignment(['id' => 5]);

        $result = $assignment->save();

        self::assertTrue($result);
        self::assertCount(1, $adapter->queryCalls);
        self::assertStringContainsString('UPDATE', $adapter->queryCalls[0]);
        self::assertStringNotContainsString('WHEN', $adapter->queryCalls[0]);
        self::assertStringNotContainsString('NOW()', $adapter->queryCalls[0]);
    }

    public function testSaveWithIdTargetsTheGivenRow(): void
    {
        $adapter = $this->installAdapter();
        $assignment = $this->makeAssignment(['id' => 42]);

        $assignment->save();

        self::assertStringContainsString('ID', $adapter->queryCalls[0]);
        self::assertStringContainsString('42', $adapter->queryCalls[0]);
    }

    public function testSaveWithIdDoesNotResetTheIdFromLastInsertId(): void
    {
        $this->installAdapter(rowCount: 1, lastId: 999);
        $assignment = $this->makeAssignment(['id' => 5]);

        $assignment->save();

        self::assertSame(5, $assignment->getId());
    }
}

/**
 * Minimal Zend_Db adapter stub for Episciences_User_Assignment::save(),
 * which issues a single INSERT or UPDATE via the abstract adapter's
 * query() pipeline. Only the generated SQL and the affected-row count are
 * exercised, so no real DB is required.
 */
final class AssignmentSaveTestAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var array<int, string> */
    public array $queryCalls = [];

    public function __construct(
        private readonly int $rowCount = 1,
        private readonly int $lastId = 0
    ) {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    public function query($sql, $bind = []): Zend_Db_Statement_Interface
    {
        $this->queryCalls[] = (string) $sql;

        return new AssignmentRowCountStatement($this->rowCount);
    }

    /**
     * The abstract insert()/update() rely on named parameters when positional
     * binding is unavailable.
     */
    public function supportsParameters($type): bool
    {
        return $type === 'named';
    }

    public function lastInsertId($tableName = null, $primaryKey = null): string
    {
        return (string) $this->lastId;
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

    public function getServerVersion(): string
    {
        return 'test';
    }
}

/**
 * Minimal Zend_Db_Statement_Interface stub — only rowCount() is exercised.
 */
final class AssignmentRowCountStatement implements Zend_Db_Statement_Interface
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
