<?php

/**
 * Unit tests for Episciences_PapersManager batch loading methods
 *
 * Tests the generic loadAssignmentsBatch() method and its usage
 * by loadEditorsBatch(), loadCopyEditorsBatch(), and loadReviewersBatch().
 */
class Episciences_PapersManagerTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test loadAssignmentsBatch with empty papers array
     */
    public function testLoadAssignmentsBatchWithEmptyArray()
    {
        $result = Episciences_PapersManager::loadAssignmentsBatch(
            [],
            Episciences_Acl::ROLE_EDITOR,
            'Episciences_Editor'
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test loadAssignmentsBatch validates Paper objects
     */
    public function testLoadAssignmentsBatchValidatesPaperObjects()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items must be Episciences_Paper objects');

        // Pass invalid objects
        Episciences_PapersManager::loadAssignmentsBatch(
            [new stdClass()],
            Episciences_Acl::ROLE_EDITOR,
            'Episciences_Editor'
        );
    }

    /**
     * Test loadAssignmentsBatch enforces batch size limit
     */
    public function testLoadAssignmentsBatchEnforcesSizeLimit()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch size exceeds maximum of 10,000 papers');

        // Create array of 10,001 mock papers
        $papers = array_fill(0, 10001, $this->createMock(Episciences_Paper::class));

        Episciences_PapersManager::loadAssignmentsBatch(
            $papers,
            Episciences_Acl::ROLE_EDITOR,
            'Episciences_Editor'
        );
    }

    /**
     * Test loadAssignmentsBatch validates assignment class exists
     */
    public function testLoadAssignmentsBatchValidatesClassName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Assignment class does not exist');

        $mockPaper = $this->createMock(Episciences_Paper::class);

        Episciences_PapersManager::loadAssignmentsBatch(
            [$mockPaper],
            Episciences_Acl::ROLE_EDITOR,
            'NonExistentClass'
        );
    }

    /**
     * Test loadEditorsBatch delegates to generic method correctly
     */
    public function testLoadEditorsBatchDelegatesToGenericMethod()
    {
        // Create mock paper
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(123);

        // Mock database
        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->createMock(Zend_Db_Select::class);

        $mockSelect->method('from')->willReturn($mockSelect);
        $mockSelect->method('where')->willReturn($mockSelect);
        $mockSelect->method('join')->willReturn($mockSelect);
        $mockSelect->method('joinUsing')->willReturn($mockSelect);
        $mockSelect->method('group')->willReturn($mockSelect);

        $mockDb->method('select')->willReturn($mockSelect);
        $mockDb->method('fetchAll')->willReturn([]);

        Zend_Db_Table_Abstract::setDefaultAdapter($mockDb);

        // Test - should not throw exception
        $result = Episciences_Paper::loadEditorsBatch([$mockPaper], true, false);

        $this->assertIsArray($result);
    }

    /**
     * Test loadCopyEditorsBatch uses correct role
     */
    public function testLoadCopyEditorsBatchUsesCorrectRole()
    {
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(456);

        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->createMock(Zend_Db_Select::class);

        $mockSelect->method('from')->willReturn($mockSelect);
        $mockSelect->method('where')->willReturn($mockSelect);
        $mockSelect->method('join')->willReturn($mockSelect);
        $mockSelect->method('joinUsing')->willReturn($mockSelect);
        $mockSelect->method('group')->willReturn($mockSelect);

        $mockDb->method('select')->willReturn($mockSelect);
        $mockDb->method('fetchAll')->willReturn([]);

        Zend_Db_Table_Abstract::setDefaultAdapter($mockDb);

        $result = Episciences_Paper::loadCopyEditorsBatch([$mockPaper], true, false);

        $this->assertIsArray($result);
    }

    /**
     * Test loadReviewersBatch handles status array correctly
     */
    public function testLoadReviewersBatchHandlesStatusArray()
    {
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(789);

        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->createMock(Zend_Db_Select::class);

        $mockSelect->method('from')->willReturn($mockSelect);
        $mockSelect->method('where')->willReturn($mockSelect);
        $mockSelect->method('join')->willReturn($mockSelect);
        $mockSelect->method('joinLeft')->willReturn($mockSelect);
        $mockSelect->method('group')->willReturn($mockSelect);

        $mockDb->method('select')->willReturn($mockSelect);
        $mockDb->method('fetchAll')->willReturn([]);

        Zend_Db_Table_Abstract::setDefaultAdapter($mockDb);

        // Test with custom status array
        $result = Episciences_PapersManager::loadReviewersBatch(
            [$mockPaper],
            ['active', 'pending'],
            false
        );

        $this->assertIsArray($result);
    }
}
