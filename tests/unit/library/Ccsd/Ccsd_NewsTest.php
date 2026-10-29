<?php

use PHPUnit\Framework\TestCase;

/**
 * Site scoping of Ccsd_News write operations.
 *
 * @covers Ccsd_News
 */
class Ccsd_NewsTest extends TestCase
{
    private const SID = 7;

    private function buildNews(Zend_Db_Adapter_Abstract $db): Ccsd_News
    {
        $news = (new ReflectionClass(Ccsd_News::class))->newInstanceWithoutConstructor();
        foreach (['_db' => $db, '_sid' => self::SID] as $property => $value) {
            $reflection = new ReflectionProperty(Ccsd_News::class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue($news, $value);
        }

        return $news;
    }

    /**
     * Concrete adapter so that select() and identifier quoting stay real;
     * only the methods reaching the database (connection included) are stubbed.
     */
    private function buildAdapter(): Zend_Db_Adapter_Pdo_Mysql
    {
        return $this->getMockBuilder(Zend_Db_Adapter_Pdo_Mysql::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_connect', 'fetchOne', 'delete', 'update', 'insert'])
            ->getMock();
    }

    public function testBelongsToSiteRejectsNonPositiveId(): void
    {
        $db = $this->buildAdapter();
        $db->expects(self::never())->method('fetchOne');

        self::assertFalse($this->buildNews($db)->belongsToSite(0));
    }

    public function testBelongsToSiteQueriesCurrentSite(): void
    {
        $db = $this->buildAdapter();
        $db->expects(self::once())->method('fetchOne')
            ->with(self::callback(static fn(Zend_Db_Select $select): bool => $select->getPart(Zend_Db_Select::WHERE) === [
                '(NEWSID = 12)',
                'AND (SID = ' . self::SID . ')',
            ]))
            ->willReturn('12');

        self::assertTrue($this->buildNews($db)->belongsToSite(12));
    }

    public function testBelongsToSiteIsFalseForForeignNews(): void
    {
        $db = $this->buildAdapter();
        $db->method('fetchOne')->willReturn(false);

        self::assertFalse($this->buildNews($db)->belongsToSite(12));
    }

    public function testDeleteIsScopedToCurrentSite(): void
    {
        $db = $this->buildAdapter();
        $db->expects(self::once())->method('delete')
            ->with('NEWS', [
                'NEWSID = ?' => 12,
                'SID = ?' => self::SID,
            ])
            ->willReturn(0);

        self::assertFalse($this->buildNews($db)->delete(12));
    }

    public function testDeleteRejectsNonPositiveId(): void
    {
        $db = $this->buildAdapter();
        $db->expects(self::never())->method('delete');

        self::assertFalse($this->buildNews($db)->delete(0));
    }

    public function testSaveRejectsForeignNews(): void
    {
        $db = $this->buildAdapter();
        $db->method('fetchOne')->willReturn(false);
        $db->expects(self::never())->method('update');
        $db->expects(self::never())->method('insert');

        $this->expectException(InvalidArgumentException::class);

        $this->buildNews($db)->save(['newsid' => 12, 'online' => 1, 'title' => ['fr' => 't'], 'uid' => 1]);
    }
}
