<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Template;
use Episciences_Mail_TemplatesManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Exception;

/**
 * @covers Episciences_Mail_TemplatesManager::findManyByKeys
 */
final class Episciences_Mail_TemplatesManager_FindManyByKeysTest extends TestCase
{
    private mixed $previousAdapter;

    protected function setUp(): void
    {
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function installAdapter(array $resultsByCall): FindManyByKeysTestAdapter
    {
        $adapter = new FindManyByKeysTestAdapter($resultsByCall);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        return $adapter;
    }

    /**
     * Build a minimal DB row suitable for Template::fromRow().
     * $parentId > 0 means custom template (child of a default); 0 means default.
     */
    private function makeRow(string $key, string $rvcode = '', int $parentId = 0): array
    {
        return [
            'ID'       => random_int(1, 9999),
            'PARENTID' => $parentId,
            'RVID'     => $parentId > 0 ? 5 : 0,
            'RVCODE'   => $rvcode,
            'KEY'      => $key,
            'TYPE'     => 'manual',
        ];
    }

    // =========================================================================
    // Early-return guards
    // =========================================================================

    public function testEmptyKeysReturnsEmptyArrayWithoutDbQuery(): void
    {
        $adapter = $this->installAdapter([]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys([], 'myjournal');

        self::assertSame([], $result);
        self::assertSame(0, $adapter->fetchAllCallCount, 'No DB query must be issued for an empty key list.');
    }

    public function testNullRvcodeThrowsZendException(): void
    {
        $this->installAdapter([]);

        $this->expectException(Zend_Exception::class);
        $this->expectExceptionMessage('Template could not be found because rvcode is missing');

        Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], null);
    }

    public function testEmptyStringRvcodeThrowsZendException(): void
    {
        $this->installAdapter([]);

        $this->expectException(Zend_Exception::class);
        $this->expectExceptionMessage('Template could not be found because rvcode is missing');

        Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], '');
    }

    // =========================================================================
    // Custom takes precedence over default
    // =========================================================================

    public function testCustomTemplateTakesPrecedenceOverDefault(): void
    {
        $customRow  = $this->makeRow('custom_paper_refused', 'myjournal', 1);
        $defaultRow = $this->makeRow('paper_refused');

        // Query 1 (custom) → returns the custom row.
        // Query 2 (default) → not issued because all keys have a custom match.
        $adapter = $this->installAdapter([[$customRow], [$defaultRow]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertArrayHasKey('paper_refused', $result);
        self::assertSame('custom_paper_refused', $result['paper_refused']->getKey());
    }

    public function testOnlyOneDbQueryWhenAllKeysHaveCustomTemplates(): void
    {
        $customRow1 = $this->makeRow('custom_paper_refused', 'myjournal', 1);
        $customRow2 = $this->makeRow('custom_paper_accepted', 'myjournal', 2);

        $adapter = $this->installAdapter([[$customRow1, $customRow2], []]);

        Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused', 'paper_accepted'], 'myjournal');

        self::assertSame(1, $adapter->fetchAllCallCount, 'Second query must be skipped when all keys are covered by custom templates.');
    }

    // =========================================================================
    // First-row-wins on duplicate DB rows
    // =========================================================================

    public function testFirstRowWinsWhenDuplicateCustomRowsExist(): void
    {
        $firstRow  = $this->makeRow('custom_paper_refused', 'myjournal', 1);
        $firstRow['ID']  = 10;
        $secondRow = $this->makeRow('custom_paper_refused', 'myjournal', 2);
        $secondRow['ID'] = 20;

        $this->installAdapter([[$firstRow, $secondRow], []]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertSame(10, $result['paper_refused']->getId(), 'First returned row must win when duplicates exist.');
    }

    public function testFirstRowWinsWhenDuplicateDefaultRowsExist(): void
    {
        $firstRow  = $this->makeRow('paper_refused');
        $firstRow['ID']  = 10;
        $secondRow = $this->makeRow('paper_refused');
        $secondRow['ID'] = 20;

        $this->installAdapter([[], [$firstRow, $secondRow]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertSame(10, $result['paper_refused']->getId(), 'First returned row must win when duplicates exist.');
    }

    // =========================================================================
    // Default fallback
    // =========================================================================

    public function testDefaultTemplateUsedWhenNoCustomExists(): void
    {
        $defaultRow = $this->makeRow('paper_refused');

        // Query 1 (custom) → nothing. Query 2 (default) → default row.
        $adapter = $this->installAdapter([[], [$defaultRow]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertArrayHasKey('paper_refused', $result);
        self::assertSame('paper_refused', $result['paper_refused']->getKey());
        self::assertSame(2, $adapter->fetchAllCallCount, 'Both queries must fire when no custom template is found.');
    }

    // =========================================================================
    // Mixed keys — some custom, some default
    // =========================================================================

    public function testMixedCustomAndDefaultKeys(): void
    {
        $customRow  = $this->makeRow('custom_paper_accepted', 'myjournal', 1);
        $defaultRow = $this->makeRow('paper_refused');

        // Query 1 returns the custom row for 'paper_accepted'.
        // Query 2 fetches default for 'paper_refused' (not covered by query 1).
        $adapter = $this->installAdapter([[$customRow], [$defaultRow]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(
            ['paper_accepted', 'paper_refused'],
            'myjournal'
        );

        self::assertCount(2, $result);
        self::assertSame('custom_paper_accepted', $result['paper_accepted']->getKey());
        self::assertSame('paper_refused', $result['paper_refused']->getKey());
        self::assertSame(2, $adapter->fetchAllCallCount);
    }

    // =========================================================================
    // Missing keys
    // =========================================================================

    public function testKeyAbsentFromDatabaseIsNotPresentInResult(): void
    {
        // Both queries return nothing.
        $adapter = $this->installAdapter([[], []]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_unknown'], 'myjournal');

        self::assertSame([], $result);
        self::assertArrayNotHasKey('paper_unknown', $result);
    }

    public function testPartiallyMissingKeysReturnOnlyFoundOnes(): void
    {
        $foundRow = $this->makeRow('paper_accepted');

        $adapter = $this->installAdapter([[], [$foundRow]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(
            ['paper_accepted', 'paper_totally_unknown'],
            'myjournal'
        );

        self::assertCount(1, $result);
        self::assertArrayHasKey('paper_accepted', $result);
        self::assertArrayNotHasKey('paper_totally_unknown', $result);
    }

    // =========================================================================
    // Result shape
    // =========================================================================

    public function testResultIsIndexedByBaseKeyNotByCustomPrefixedKey(): void
    {
        $customRow = $this->makeRow('custom_paper_refused', 'myjournal', 1);

        $this->installAdapter([[$customRow], []]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertArrayHasKey('paper_refused', $result, 'Result must be keyed by the base key, not the custom_ prefixed key.');
        self::assertArrayNotHasKey('custom_paper_refused', $result);
    }

    public function testReturnedObjectsAreMailTemplateInstances(): void
    {
        $row = $this->makeRow('paper_refused');

        $this->installAdapter([[], [$row]]);

        $result = Episciences_Mail_TemplatesManager::findManyByKeys(['paper_refused'], 'myjournal');

        self::assertInstanceOf(Episciences_Mail_Template::class, $result['paper_refused']);
    }
}

/**
 * Minimal Zend_Db adapter stub for findManyByKeys tests.
 * Returns pre-configured rows per fetchAll() call order.
 */
final class FindManyByKeysTestAdapter extends Zend_Db_Adapter_Abstract
{
    public int $fetchAllCallCount = 0;

    public function __construct(private readonly array $resultsByCall)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    public function fetchAll($sql, $bind = [], $fetchMode = null): array
    {
        return $this->resultsByCall[$this->fetchAllCallCount++] ?? [];
    }

    public function listTables(): array
    {
        return [];
    }

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

    public function prepare($sql)
    {
        return null;
    }

    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        return null;
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