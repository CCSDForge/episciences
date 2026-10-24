<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Section\SectionHasPapersException;
use Episciences_PapersManager;
use Episciences_SectionsManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Deletion of a section that still has papers, and moves of papers between sections.
 *
 * The database adapter is a partial mock: queries are built for real and captured, nothing is
 * executed.
 */
final class SectionDeletionTest extends TestCase
{
    private const SID = 7;
    private const CSRF_TOKEN = 'section-deletion-token';

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the paper lookups sent to the adapter */
    private array $paperLookups = [];

    /** @var list<array{0: string, 1: array<string, mixed>, 2: mixed}> table, values and where of each update */
    private array $updates = [];

    /** @var array<string, mixed> */
    private array $previousPost;

    private bool $previousSessionTestMode;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->previousPost = $_POST;
        $this->previousSessionTestMode = Zend_Session::$_unitTestEnabled;

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchPairs', 'fetchCol', 'fetchOne', 'query', 'update', 'delete', 'beginTransaction', 'commit', 'rollBack'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->adapter->method('fetchPairs')->willReturn([]);
        $this->adapter->method('update')->willReturnCallback(function ($table, array $values, $where = '') {
            $this->updates[] = [$table, $values, $where];

            return 1;
        });

        require_once APPLICATION_PATH . '/modules/journal/controllers/SectionController.php';
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);

        if (Zend_Session::$_unitTestEnabled) {
            $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
            unset($session->csrfToken);
        }
        Zend_Session::$_unitTestEnabled = $this->previousSessionTestMode;
        $_POST = $this->previousPost;
    }

    /**
     * @return array<string, int|string>
     */
    private static function sectionRow(): array
    {
        return ['SID' => self::SID, 'RVID' => RVID, 'POSITION' => 1];
    }

    /**
     * @param list<string> $paperIds PAPERIDs returned for the section
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

    /**
     * @param mixed $id posted section id
     */
    private function dispatchDelete(mixed $id = self::SID): string
    {
        Zend_Session::$_unitTestEnabled = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = self::CSRF_TOKEN;

        // The test request keeps what an earlier one posted
        $_POST = [];

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost([
            'ajax' => '1',
            'params' => ['id' => is_int($id) ? (string)$id : $id],
            'csrf_token' => self::CSRF_TOKEN,
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
        $this->adapter->expects(self::never())->method('commit');
        $this->adapter->expects(self::once())->method('rollBack');

        $output = $this->dispatchDelete();

        // The delete button shows the answer as the reason of the failure
        self::assertStringContainsString('#12, #15', $output);
        self::assertNotSame('1', $output);
        self::assertSame([], $this->updates);
    }

    public function testOnlyTheFirstPapersOfACrowdedSectionAreListed(): void
    {
        $this->givenSectionWithPapers(array_map('strval', range(1, 13)));

        $output = $this->dispatchDelete();

        self::assertStringContainsString('#1, #2, #3, #4, #5, #6, #7, #8, #9, #10', $output);
        self::assertStringNotContainsString('#11', $output);
        self::assertMatchesRegularExpression('/#10 (et|and) 3 /', $output);
    }

    public function testSectionWithoutPapersIsDeleted(): void
    {
        $this->givenSectionWithPapers([]);
        $deletedTables = [];
        $this->adapter->method('delete')->willReturnCallback(static function ($table) use (&$deletedTables) {
            $deletedTables[] = $table;

            return 1;
        });
        $this->adapter->expects(self::once())->method('commit');

        $output = $this->dispatchDelete();

        self::assertSame('1', $output);
        self::assertContains(T_SECTIONS, $deletedTables);
    }

    public function testMalformedSectionIdIsRejected(): void
    {
        $this->adapter->expects(self::never())->method('fetchRow');
        $this->adapter->expects(self::never())->method('delete');

        self::assertSame('', $this->dispatchDelete(['foo']));
    }

    public function testManagerKeepsASectionThatStillHasPapers(): void
    {
        $this->givenSectionWithPapers(['12']);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('commit');
        $this->adapter->expects(self::once())->method('rollBack');

        try {
            Episciences_SectionsManager::delete(self::SID);
            self::fail('A section that still has papers must not be deleted');
        } catch (SectionHasPapersException $e) {
            self::assertSame([12], $e->getPaperIds());
        }
    }

    public function testPapersThatCanNoLongerBeMovedLeaveTheSectionWithIt(): void
    {
        $this->givenSectionWithPapers([]);
        $this->adapter->method('delete')->willReturn(1);

        self::assertTrue(Episciences_SectionsManager::delete(self::SID));

        $paperUpdates = array_values(array_filter($this->updates, static fn(array $update) => $update[0] === T_PAPERS));
        $expectedWhere = ['SID = ?' => self::SID, 'RVID = ?' => (int)RVID];
        self::assertSame([[T_PAPERS, ['SID' => 0], $expectedWhere]], $paperUpdates);
    }

    public function testAttachedPapersAreTheMovablePapersOfTheSectionJournal(): void
    {
        $this->givenSectionWithPapers(['12', '15']);

        self::assertSame([12, 15], Episciences_SectionsManager::getAttachedPaperIds(self::SID, 3));

        self::assertCount(1, $this->paperLookups);
        $sql = $this->paperLookups[0];
        self::assertMatchesRegularExpression('/^SELECT DISTINCT\b.*\bPAPERID\b/', $sql);
        self::assertMatchesRegularExpression('/\bSID = ' . self::SID . '\b/', $sql);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $sql);
        self::assertMatchesRegularExpression(
            sprintf(
                '/\bPAPERID NOT IN \(\(SELECT .*\bSTATUS IN \(%d, %d\)\)/',
                \Episciences_Paper::STATUS_DELETED,
                \Episciences_Paper::STATUS_REMOVED
            ),
            $sql
        );
        self::assertStringNotContainsString('FOR UPDATE', $sql);
    }

    public function testDeletionLocksTheAttachedPapers(): void
    {
        $this->givenSectionWithPapers([]);
        $this->adapter->method('delete')->willReturn(1);

        Episciences_SectionsManager::delete(self::SID);

        self::assertCount(1, $this->paperLookups);
        self::assertStringEndsWith('FOR UPDATE', $this->paperLookups[0]);
    }

    public function testSectionLockedForPapersBelongsToTheJournal(): void
    {
        $lookups = [];
        $this->adapter->method('fetchOne')->willReturnCallback(static function (Zend_Db_Select $select) use (&$lookups) {
            $lookups[] = $select->assemble();

            return false;
        });

        self::assertFalse(Episciences_SectionsManager::lockForPapers(self::SID, 3));

        self::assertMatchesRegularExpression('/\bSID = ' . self::SID . '\b.*\bRVID = 3\b.*FOR UPDATE$/', $lookups[0]);
    }

    public function testMovingAPaperMovesAllItsVersions(): void
    {
        Episciences_PapersManager::updateSection(100, 3, 8);

        self::assertSame([[T_PAPERS, ['SID' => 8], ['PAPERID = ?' => 100, 'RVID = ?' => 3]]], $this->updates);
    }
}
