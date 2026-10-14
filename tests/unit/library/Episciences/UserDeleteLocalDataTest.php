<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The identifier given to the local account deletion must only ever reach the database as an integer.
 */
final class UserDeleteLocalDataTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var list<array{string, mixed}> table and conditions of each deletion */
    private array $deletes = [];

    /** @var list<mixed> bound values of each query */
    private array $queryBinds = [];

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete', 'query'])
            ->getMock();
        $db->method('delete')->willReturnCallback(function ($table, $where) {
            $this->deletes[] = [$table, $where];
            return 1;
        });
        $db->method('query')->willReturnCallback(function ($sql, $bind = []) {
            $this->queryBinds[] = $bind;
            return null;
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($db);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testJournalScopedDeletionBindsAnIntegerIdentifier(): void
    {
        Episciences_User::deleteLocalData('5 OR 1=1');
        $rvid = (int)RVID;

        self::assertCount(1, $this->deletes);
        self::assertSame('USER_ROLES', $this->deletes[0][0]);
        self::assertSame(['RVID = ?' => $rvid, 'UID = ?' => 5], $this->deletes[0][1]);
        self::assertSame([[5, $rvid]], $this->queryBinds);
    }

    public function testPlatformWideDeletionBindsAnIntegerIdentifier(): void
    {
        Episciences_User::deleteLocalData('7) OR (1=1', true);

        self::assertSame([['UID = ?' => 7], ['UID = ?' => 7]], array_column($this->deletes, 1));
        self::assertSame([7], $this->queryBinds);
    }
}
