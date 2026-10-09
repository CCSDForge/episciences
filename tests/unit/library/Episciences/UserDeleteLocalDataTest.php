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

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testJournalScopedDeletionBindsAnIntegerIdentifier(): void
    {
        $deletes = [];
        $db = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete', 'query'])
            ->getMock();
        $db->method('delete')->willReturnCallback(static function ($table, $where) use (&$deletes) {
            $deletes[] = [$table, $where];
            return 1;
        });
        $db->method('query')->willReturn(null);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        Episciences_User::deleteLocalData('5 OR 1=1');

        self::assertCount(1, $deletes);
        self::assertSame('USER_ROLES', $deletes[0][0]);
        self::assertSame(['RVID = ?', 'UID = ?'], array_keys($deletes[0][1]));
        self::assertSame(5, $deletes[0][1]['UID = ?']);
    }

    public function testPlatformWideDeletionBindsAnIntegerIdentifier(): void
    {
        $deletes = [];
        $db = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['delete', 'query'])
            ->getMock();
        $db->method('delete')->willReturnCallback(static function ($table, $where) use (&$deletes) {
            $deletes[] = $where;
            return 1;
        });
        $db->method('query')->willReturn(null);
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        Episciences_User::deleteLocalData('7) OR (1=1', true);

        self::assertSame([['UID = ?' => 7], ['UID = ?' => 7]], $deletes);
    }
}
