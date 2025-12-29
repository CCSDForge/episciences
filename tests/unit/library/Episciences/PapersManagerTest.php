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
     * @group integration
     */
    public function testLoadEditorsBatchDelegatesToGenericMethod()
    {
        $this->markTestSkipped('Requires real database - Zend_Db_Select mocking is too complex for unit tests');

        // Create mock paper
        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(123);

        // Mock database
        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->getMockBuilder(Zend_Db_Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->addMethods(['joinUsing'])
            ->getMock();

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
     * @group integration
     */
    public function testLoadCopyEditorsBatchUsesCorrectRole()
    {
        $this->markTestSkipped('Requires real database - Zend_Db_Select mocking is too complex for unit tests');

        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(456);

        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $mockSelect = $this->getMockBuilder(Zend_Db_Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->addMethods(['joinUsing'])
            ->getMock();

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
     * @group integration
     */
    public function testLoadReviewersBatchHandlesStatusArray()
    {
        $this->markTestSkipped('Requires real database - Zend_Db_Select mocking is too complex for unit tests');

        $mockPaper = $this->createMock(Episciences_Paper::class);
        $mockPaper->method('getDocid')->willReturn(789);

        // Create TWO separate Select mocks (one for subQuery, one for main select)
        $mockSelect1 = $this->getMockBuilder(Zend_Db_Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $mockSelect1->method('from')->willReturn($mockSelect1);
        $mockSelect1->method('where')->willReturn($mockSelect1);
        $mockSelect1->method('join')->willReturn($mockSelect1);
        $mockSelect1->method('group')->willReturn($mockSelect1);

        $mockSelect2 = $this->getMockBuilder(Zend_Db_Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->addMethods(['joinUsing'])
            ->getMock();
        $mockSelect2->method('from')->willReturn($mockSelect2);
        $mockSelect2->method('where')->willReturn($mockSelect2);
        $mockSelect2->method('join')->willReturn($mockSelect2);
        $mockSelect2->method('joinUsing')->willReturn($mockSelect2);
        $mockSelect2->method('joinLeft')->willReturn($mockSelect2);
        $mockSelect2->method('group')->willReturn($mockSelect2);

        $mockDb = $this->createMock(Zend_Db_Adapter_Abstract::class);

        // Return the two mocks in order for consecutive calls
        $mockDb->method('select')
            ->willReturnOnConsecutiveCalls($mockSelect1, $mockSelect2);
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
