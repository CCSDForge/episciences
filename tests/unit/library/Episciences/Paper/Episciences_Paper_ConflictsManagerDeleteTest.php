<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Paper_ConflictsManager::deleteByIdAndPaperId
 */
class Episciences_Paper_ConflictsManagerDeleteTest extends TestCase
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

    public function testDeleteIsScopedToTheOwningPaper(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects($this->once())
            ->method('delete')
            ->with(Episciences_Paper_ConflictsManager::TABLE, ['cid = ?' => 5, 'paper_id = ?' => 42])
            ->willReturn(1);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        $this->assertTrue(Episciences_Paper_ConflictsManager::deleteByIdAndPaperId(5, 42));
    }

    public function testNothingDeletedWhenConflictBelongsToAnotherPaper(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->method('delete')->willReturn(0);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        $this->assertFalse(Episciences_Paper_ConflictsManager::deleteByIdAndPaperId(5, 43));
    }

    public function testInvalidIdsNeverReachTheDatabase(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->expects($this->never())->method('delete');
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        $this->assertFalse(Episciences_Paper_ConflictsManager::deleteByIdAndPaperId(0, 42));
        $this->assertFalse(Episciences_Paper_ConflictsManager::deleteByIdAndPaperId(5, 0));
    }
}
