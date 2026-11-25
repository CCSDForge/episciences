<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Section;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Regression tests: volume and section editor lookups must be scoped to the
 * journal of the volume/section itself, not to the global RVID constant, which
 * is not defined in CLI contexts such as solr:index.
 *
 * The bootstrap defines RVID = 1, so the objects below use another journal id
 * to tell the two sources apart.
 *
 * @covers Episciences_Volume::loadEditors
 * @covers Episciences_Section::loadEditors
 */
final class Episciences_VolumeSectionEditorsRvidTest extends TestCase
{
    private const OWN_RVID = 42;

    private mixed $previousAdapter;
    private EditorsRvidTestAdapter $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = new EditorsRvidTestAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testVolumeEditorsAreScopedToTheVolumeJournal(): void
    {
        $volume = new Episciences_Volume();
        $volume->setVid(7);
        $volume->setRvid(self::OWN_RVID);

        $volume->loadEditors();

        self::assertCount(1, $this->adapter->fetchAssocCalls);
        self::assertStringContainsString('ur.RVID = ' . self::OWN_RVID, $this->adapter->fetchAssocCalls[0]);
        self::assertStringNotContainsString('ur.RVID = ' . RVID, $this->adapter->fetchAssocCalls[0]);
        self::assertSame([], $volume->getEditors());
    }

    public function testVolumeEditorsFallBackToTheCurrentJournalWhenRvidIsUnset(): void
    {
        $volume = new Episciences_Volume();
        $volume->setVid(7);

        $volume->loadEditors();

        self::assertCount(1, $this->adapter->fetchAssocCalls);
        self::assertStringContainsString('ur.RVID = ' . RVID, $this->adapter->fetchAssocCalls[0]);
    }

    public function testSectionEditorsAreScopedToTheSectionJournal(): void
    {
        $section = new Episciences_Section();
        $section->setSid(9);
        $section->setRvid(self::OWN_RVID);

        $section->loadEditors();

        self::assertCount(1, $this->adapter->fetchAssocCalls);
        self::assertStringContainsString('ur.RVID = ' . self::OWN_RVID, $this->adapter->fetchAssocCalls[0]);
        self::assertStringNotContainsString('ur.RVID = ' . RVID, $this->adapter->fetchAssocCalls[0]);
        self::assertSame([], $section->getEditors());
    }

    public function testSectionEditorsFallBackToTheCurrentJournalWhenRvidIsUnset(): void
    {
        $section = new Episciences_Section();
        $section->setSid(9);

        $section->loadEditors();

        self::assertCount(1, $this->adapter->fetchAssocCalls);
        self::assertStringContainsString('ur.RVID = ' . RVID, $this->adapter->fetchAssocCalls[0]);
    }
}

/**
 * Minimal Zend_Db adapter stub recording the assembled SQL of every
 * fetchAssoc() call and returning no rows, so no editor is looked up in CAS.
 */
final class EditorsRvidTestAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var string[] */
    public array $fetchAssocCalls = [];

    public function __construct()
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchAssoc($sql, $bind = [])
    {
        $this->fetchAssocCalls[] = $sql instanceof Zend_Db_Select ? $sql->assemble() : (string)$sql;
        return [];
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
        throw new \RuntimeException('Not used: fetchAssoc() is stubbed directly.');
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
