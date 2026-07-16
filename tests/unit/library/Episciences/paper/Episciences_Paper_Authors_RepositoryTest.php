<?php

namespace unit\library\Episciences;

use Episciences_Paper_Authors_Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @covers Episciences_Paper_Authors_Repository
 */
class Episciences_Paper_Authors_RepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Episciences_Paper_Authors_Repository::setCachePool(new ArrayAdapter());
    }

    // ---------------------------------------------------------------
    // Cache pool wiring
    // ---------------------------------------------------------------

    public function testGetCachePoolReturnsArrayAdapterByDefault(): void
    {
        $pool = Episciences_Paper_Authors_Repository::getCachePool();
        $this->assertInstanceOf(ArrayAdapter::class, $pool);
    }

    public function testSetCachePoolChangesPoolInstance(): void
    {
        $mock = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        Episciences_Paper_Authors_Repository::setCachePool($mock);
        $this->assertSame($mock, Episciences_Paper_Authors_Repository::getCachePool());
    }

    // ---------------------------------------------------------------
    // getAuthorByPaperId() — cache hit path (no DB needed)
    // ---------------------------------------------------------------

    public function testGetAuthorByPaperIdReturnsCachedValueOnHit(): void
    {
        $pool = new ArrayAdapter();
        $paperId = 123;
        $cachedRows = [
            1 => ['PAPERID' => 123, 'authors' => '{"name":"Alice"}'],
        ];

        $item = $pool->getItem('authors_paper_' . $paperId);
        $item->set($cachedRows);
        $pool->save($item);

        Episciences_Paper_Authors_Repository::setCachePool($pool);

        $result = Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId);
        $this->assertSame($cachedRows, $result);
    }

    public function testGetAuthorByPaperIdStoresResultInCacheOnMiss(): void
    {
        $pool = new ArrayAdapter();
        Episciences_Paper_Authors_Repository::setCachePool($pool);

        $paperId = 456;
        $emptyRows = [];

        $item = $pool->getItem('authors_paper_' . $paperId);
        $item->set($emptyRows);
        $pool->save($item);

        $result = Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId);
        $this->assertSame($emptyRows, $result);

        $this->assertTrue($pool->getItem('authors_paper_' . $paperId)->isHit());
    }

    public function testGetAuthorByPaperIdCacheKeyIsIsolatedPerPaper(): void
    {
        $pool = new ArrayAdapter();

        $rows7 = [1 => ['PAPERID' => 7, 'authors' => '{}']];
        $rows8 = [2 => ['PAPERID' => 8, 'authors' => '{}']];

        $item7 = $pool->getItem('authors_paper_7');
        $item7->set($rows7);
        $pool->save($item7);

        $item8 = $pool->getItem('authors_paper_8');
        $item8->set($rows8);
        $pool->save($item8);

        Episciences_Paper_Authors_Repository::setCachePool($pool);

        $this->assertSame($rows7, Episciences_Paper_Authors_Repository::getAuthorByPaperId(7));
        $this->assertSame($rows8, Episciences_Paper_Authors_Repository::getAuthorByPaperId(8));
    }
}