<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Authorization of the linked data actions (add, remove, update).
 *
 * The actions must refuse anything that is not an AJAX POST of a logged-in user, and the
 * paper concerned must be the one stored with the dataset, looked up in the current journal:
 * the docId and paperId sent by the client are never trusted. Controllers are dispatched for
 * real; the database adapter is a partial mock that captures the queries and writes.
 */
final class AdministratelinkeddataControllerTest extends TestCase
{
    private const DENIED = '[false]';

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> */
    private array $queries = [];

    private mixed $previousAuthStorage;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratelinkeddataController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'query', 'insert', 'update', 'delete'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
    }

    private function logIn(): void
    {
        Zend_Auth::getInstance()->getStorage()->write((object)['UID' => 12345, 'ROLES' => []]);
    }

    /**
     * Dataset lookups return $datasetRow; paper lookups (queries) return $paperRow.
     *
     * @param array<string, mixed>|false $datasetRow
     * @param array<string, mixed>|false $paperRow
     */
    private function stubDatabase(array|false $datasetRow, array|false $paperRow): void
    {
        $this->adapter->method('fetchRow')->willReturnCallback(
            function ($select) use ($datasetRow) {
                $this->queries[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

                return $datasetRow;
            }
        );

        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturn($paperRow);
        $this->adapter->method('query')->willReturnCallback(
            function ($sql) use ($statement) {
                $this->queries[] = $sql instanceof Zend_Db_Select ? $sql->assemble() : (string)$sql;

                return $statement;
            }
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatch(string $action, array $post, bool $ajax = true, string $method = 'POST'): string
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method)->setPost($post);
        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }
        $controller = new \AdministratelinkeddataController($request, new Zend_Controller_Response_HttpTestCase());

        // Some actions open their own output buffer: restore the initial level afterwards
        $level = ob_get_level();
        ob_start();
        try {
            $returned = $controller->$action();
        } finally {
            $output = '';
            while (ob_get_level() > $level) {
                $output = (string)ob_get_clean() . $output;
            }
        }

        // setnewinfoldAction() returns its answer instead of printing it
        return $output . (is_string($returned) ? $returned : '');
    }

    private function assertNothingWasWritten(): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function writeActionsProvider(): array
    {
        return [
            'add' => ['addldAction', ['docId' => '5', 'typeld' => 'software', 'valueld' => 'x', 'relationship' => 'isSupplementedBy']],
            'remove' => ['removeldAction', ['docId' => '5', 'id' => '9']],
            'update' => ['setnewinfoldAction', ['ldId' => '9', 'docId' => '5', 'valueLd' => 'x', 'typeld' => 'software', 'relationship' => 'cites']],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @dataProvider writeActionsProvider
     */
    public function testAnonymousRequestIsDenied(string $action, array $post): void
    {
        $this->assertNothingWasWritten();
        $this->stubDatabase(false, false);

        $output = $this->dispatch($action, $post);

        self::assertSame(self::DENIED, $output);
        self::assertSame([], $this->queries, 'an anonymous request must not reach the database');
    }

    /**
     * @param array<string, mixed> $post
     * @dataProvider writeActionsProvider
     */
    public function testLoggedInRequestThatIsNotAnAjaxPostIsDenied(string $action, array $post): void
    {
        $this->logIn();
        $this->assertNothingWasWritten();
        $this->stubDatabase(false, false);

        $this->dispatch($action, $post, false);
        $this->dispatch($action, $post, true, 'GET');

        self::assertSame([], $this->queries, 'a non-AJAX or non-POST request must not reach the database');
    }

    public function testUnknownDatasetIsDeniedOnRemoval(): void
    {
        $this->logIn();
        $this->assertNothingWasWritten();
        $this->stubDatabase(false, false);

        self::assertSame(self::DENIED, $this->dispatch('removeldAction', ['docId' => '5', 'id' => '9']));
    }

    /**
     * The paper is derived from the stored dataset (DOCID 77), whatever docId the client sends
     * (5), and looked up in the current journal: a dataset of another journal is refused.
     */
    public function testRemovalUsesTheDocIdOfTheStoredDatasetAndTheCurrentJournal(): void
    {
        $this->logIn();
        $this->assertNothingWasWritten();
        $this->stubDatabase(['id' => 9, 'doc_id' => 77, 'code' => 'x', 'value' => 'v', 'name' => 'n'], false);

        $output = $this->dispatch('removeldAction', ['docId' => '5', 'paperId' => '5', 'id' => '9']);

        self::assertSame(self::DENIED, $output);

        $paperQueries = array_values(array_filter(
            $this->queries,
            static fn(string $sql): bool => str_contains($sql, 'DOCID')
        ));
        self::assertNotEmpty($paperQueries, 'the paper must be looked up');
        foreach ($paperQueries as $sql) {
            self::assertMatchesRegularExpression('/DOCID = 77\b/', $sql);
            self::assertDoesNotMatchRegularExpression('/DOCID = 5\b/', $sql);
            self::assertMatchesRegularExpression('/RVID = ' . RVID . '\b/', $sql);
        }
    }
}
