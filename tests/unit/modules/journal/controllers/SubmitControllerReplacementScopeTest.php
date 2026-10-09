<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_PapersManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * The paper replaced by a new version is read from the database, never described by the client.
 * The database adapter is a partial mock: queries are built for real, nothing is executed.
 */
final class SubmitControllerReplacementScopeTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['query', 'update'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        require_once APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php';
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testRenameIdentifierCanBeRestrictedToAJournal(): void
    {
        $this->adapter->expects(self::once())
            ->method('update')
            ->with(
                T_PAPERS,
                ['IDENTIFIER' => 'abc-REFUSED'],
                ['IDENTIFIER = ?' => 'abc', 'RVID = ?' => 3]
            )
            ->willReturn(1);

        self::assertSame(1, Episciences_PapersManager::renameIdentifier('abc', 'abc-REFUSED', 3));
    }

    public function testRenameIdentifierWithoutJournalKeepsItsPreviousBehaviour(): void
    {
        $this->adapter->expects(self::once())
            ->method('update')
            ->with(T_PAPERS, ['IDENTIFIER' => 'abc-REFUSED'], ['IDENTIFIER = ?' => 'abc'])
            ->willReturn(2);

        self::assertSame(2, Episciences_PapersManager::renameIdentifier('abc', 'abc-REFUSED'));
    }

    public function testReplacementOfAnUnknownPaperIsRefusedWithoutAnyWrite(): void
    {
        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturn(false);

        $selects = [];
        $this->adapter->method('query')->willReturnCallback(
            function ($sql) use ($statement, &$selects) {
                $selects[] = (string)$sql;

                return $statement;
            }
        );
        $this->adapter->expects(self::never())->method('update');

        $controller = new \SubmitController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'handlePaperReplacement');
        $method->setAccessible(true);

        // Everything below is attacker-controlled
        $formValues = [
            'old_docid' => '12',
            'old_identifier' => 'victim-identifier',
            'old_paper_status' => '5',
            'old_paperid' => '99',
            'old_version' => '0',
            'old_repoid' => '1',
            'search_doc' => ['docId' => 'victim-identifier', 'version' => '1', 'repoId' => '1'],
        ];

        [$result] = $method->invokeArgs($controller, [&$formValues]);

        self::assertSame(0, $result['code']);
        self::assertNotEmpty($selects);
        self::assertStringContainsString('12', $selects[0]);
    }

    public function testReplacementOfAPaperOfAnotherJournalIsRefusedWithoutAnyWrite(): void
    {
        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $rows = [[
            'DOCID' => 12,
            'PAPERID' => 12,
            'RVID' => RVID + 1,
            'UID' => 5,
            'STATUS' => 5,
            'IDENTIFIER' => 'victim-identifier',
            'VERSION' => 1,
            'REPOID' => 1,
        ]];
        $statement->method('fetch')->willReturnCallback(static function () use (&$rows) {
            return array_shift($rows) ?? false;
        });
        $statement->method('fetchAll')->willReturn([]);
        $statement->method('fetchColumn')->willReturn(false);
        $this->adapter->method('query')->willReturn($statement);
        $this->adapter->expects(self::never())->method('update');

        $controller = new \SubmitController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'handlePaperReplacement');
        $method->setAccessible(true);

        $formValues = [
            'old_docid' => '12',
            'old_paper_status' => '5',
            'search_doc' => ['docId' => 'victim-identifier', 'version' => '1', 'repoId' => '1'],
        ];

        [$result] = $method->invokeArgs($controller, [&$formValues]);

        self::assertSame(0, $result['code']);
    }
}
