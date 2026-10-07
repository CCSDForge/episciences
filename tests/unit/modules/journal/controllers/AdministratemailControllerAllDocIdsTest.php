<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Review;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * The mail history needs the document ids of the whole journal, not the papers themselves:
 * hydrating every paper (record XML included) exhausted the memory on journals with many papers.
 */
final class AdministratemailControllerAllDocIdsTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testOnlyTheDocIdsOfTheJournalAreSelected(): void
    {
        $sql = null;
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchCol', 'fetchAssoc', 'fetchAll'])
            ->getMock();
        $adapter->expects(self::never())->method('fetchAssoc');
        $adapter->expects(self::never())->method('fetchAll');
        $adapter->method('fetchCol')->willReturnCallback(static function ($select) use (&$sql) {
            $sql = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return ['12', '7', '30'];
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getRvid')->willReturn(3);

        $controller = new \AdministratemailController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'allDocIds');
        $method->setAccessible(true);

        self::assertSame([12, 7, 30], $method->invoke($controller, $journal));
        self::assertNotNull($sql);
        self::assertMatchesRegularExpression('/^SELECT\s+`?papers`?\.?`?DOCID`?\s+FROM|^SELECT\s+`?DOCID`?\s+FROM/i', $sql, 'only the DOCID column');
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $sql);
    }
}
