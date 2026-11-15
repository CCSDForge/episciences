<?php

declare(strict_types=1);

namespace unit\library\Episciences\Volume;

use Episciences\Volume\ProceedingsDoiLock;
use PHPUnit\Framework\TestCase;
use unit\modules\support\RecordingDbAdapter;
use Zend_Db_Adapter_Abstract;

/**
 * @covers \Episciences\Volume\ProceedingsDoiLock
 */
final class ProceedingsDoiLockTest extends TestCase
{
    public function testAcquiresAndReleasesTheJournalLockOnce(): void
    {
        $db = (new RecordingDbAdapter())->respondTo('/GET_LOCK/', [['lock' => '1']]);

        $lock = ProceedingsDoiLock::acquire(42, $db);
        self::assertInstanceOf(ProceedingsDoiLock::class, $lock);
        $lock->release();
        $lock->release();

        $queries = $db->queries();
        self::assertCount(2, $queries);
        self::assertSame(['episciences_volume_doi_42', 10], $queries[0]['bind']);
        self::assertSame('SELECT RELEASE_LOCK(?)', $queries[1]['sql']);
        self::assertSame(['episciences_volume_doi_42'], $queries[1]['bind']);
    }

    public function testReturnsNullWhenTheLockIsHeldElsewhere(): void
    {
        $db = (new RecordingDbAdapter())->respondTo('/GET_LOCK/', [['lock' => '0']]);

        self::assertNull(ProceedingsDoiLock::acquire(42, $db));
    }

    public function testReturnsNullOnDatabaseError(): void
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->method('fetchOne')->willThrowException(new \Zend_Db_Statement_Exception('gone away'));

        self::assertNull(ProceedingsDoiLock::acquire(42, $db));
    }
}
