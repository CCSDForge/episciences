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

    private function buildAdapter(): Zend_Db_Adapter_Abstract
    {
        $db = $this->getMockBuilder(Zend_Db_Adapter_Abstract::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchOne', 'delete', 'select'])
            ->getMockForAbstractClass();
        $db->method('select')->willReturnCallback(static fn() => new Zend_Db_Select($db));

        return $db;
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
            ->with(self::callback(static fn($select) => str_contains((string)$select, 'SID')))
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

        self::assertFalse($this->buildNews($db)->delete('12'));
    }
}
