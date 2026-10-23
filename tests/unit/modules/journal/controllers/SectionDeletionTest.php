<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_SectionsManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Deletion of a section that still has papers.
 *
 * The database adapter is a partial mock: queries are built for real and captured, nothing is
 * executed.
 */
final class SectionDeletionTest extends TestCase
{
    private const SID = 7;

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the paper lookups sent to the adapter */
    private array $paperLookups = [];

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchPairs', 'fetchCol', 'query', 'update', 'delete', 'beginTransaction', 'commit', 'rollBack'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->adapter->method('fetchPairs')->willReturn([]);

        require_once APPLICATION_PATH . '/modules/journal/controllers/SectionController.php';
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @return array<string, int|string>
     */
    private static function sectionRow(): array
    {
        return ['SID' => self::SID, 'RVID' => RVID, 'POSITION' => 1];
    }

    /**
     * @param list<string> $paperIds DOCIDs returned for the section
     */
    private function givenSectionWithPapers(array $paperIds): void
    {
        $this->adapter->method('fetchRow')->willReturn(self::sectionRow());

        $statement = $this->createStub(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturn(self::sectionRow());
        $this->adapter->method('query')->willReturn($statement);

        $this->adapter->method('fetchCol')->willReturnCallback(function ($select) use ($paperIds) {
            $this->paperLookups[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return $paperIds;
        });
    }

    private function dispatchDelete(): string
    {
        \Zend_Session::$_unitTestEnabled = true;
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = 'section-deletion-token';

        // The test request keeps what an earlier one posted
        $_POST = [];

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost([
            'ajax' => '1',
            'params' => ['id' => (string)self::SID],
            'csrf_token' => 'section-deletion-token',
        ]);

        $controller = new \SectionController($request, new Zend_Controller_Response_HttpTestCase());

        ob_start();
        try {
            $controller->deleteAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    public function testSectionWithPapersIsNotDeletedAndTheButtonListsThem(): void
    {
        $this->givenSectionWithPapers(['12', '15']);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('update');

        $output = $this->dispatchDelete();

        // The delete button shows the answer as the reason of the failure
        self::assertStringContainsString('#12, #15', $output);
        self::assertNotSame('1', $output);
    }

    public function testSectionWithoutPapersIsDeleted(): void
    {
        $this->givenSectionWithPapers([]);
        $deletedTables = [];
        $this->adapter->method('delete')->willReturnCallback(static function ($table) use (&$deletedTables) {
            $deletedTables[] = $table;

            return 1;
        });

        $output = $this->dispatchDelete();

        self::assertSame('1', $output);
        self::assertContains(T_SECTIONS, $deletedTables);
    }

    public function testManagerKeepsASectionThatStillHasPapers(): void
    {
        $this->givenSectionWithPapers(['12']);
        $this->adapter->expects(self::never())->method('beginTransaction');
        $this->adapter->expects(self::never())->method('delete');

        self::assertFalse(Episciences_SectionsManager::delete(self::SID));
    }

    public function testAttachedPapersAreLookedUpInTheSectionJournalOnly(): void
    {
        $this->givenSectionWithPapers(['15', '12']);

        self::assertSame([15, 12], Episciences_SectionsManager::getAttachedPaperIds(self::SID, 3));

        self::assertCount(1, $this->paperLookups);
        self::assertMatchesRegularExpression('/\bSID = ' . self::SID . '\b/', $this->paperLookups[0]);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->paperLookups[0]);
    }
}
