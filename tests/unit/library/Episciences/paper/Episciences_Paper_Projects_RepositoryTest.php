<?php

namespace unit\library\Episciences;

use Episciences_Paper_Projects_Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @covers Episciences_Paper_Projects_Repository
 */
class Episciences_Paper_Projects_RepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Episciences_Paper_Projects_Repository::setCachePool(new ArrayAdapter());
    }

    // ---------------------------------------------------------------
    // Cache pool wiring
    // ---------------------------------------------------------------

    public function testGetCachePoolReturnsArrayAdapterByDefault(): void
    {
        $pool = Episciences_Paper_Projects_Repository::getCachePool();
        $this->assertInstanceOf(ArrayAdapter::class, $pool);
    }

    public function testSetCachePoolChangesPoolInstance(): void
    {
        $mock = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        Episciences_Paper_Projects_Repository::setCachePool($mock);
        $this->assertSame($mock, Episciences_Paper_Projects_Repository::getCachePool());
    }

    // ---------------------------------------------------------------
    // getByPaperId() — cache hit path (no DB needed)
    // ---------------------------------------------------------------

    public function testGetByPaperIdReturnsCachedValueOnHit(): void
    {
        $pool = new ArrayAdapter();
        $paperId = 10;
        $cachedRows = [
            1 => ['PAPERID' => 10, 'source_id' => 2, 'source_id_name' => 'HAL'],
        ];

        $item = $pool->getItem('projects_paper_' . $paperId);
        $item->set($cachedRows);
        $pool->save($item);

        Episciences_Paper_Projects_Repository::setCachePool($pool);

        $result = Episciences_Paper_Projects_Repository::getByPaperId($paperId);
        $this->assertSame($cachedRows, $result);
    }

    public function testGetByPaperIdCacheKeyIsIsolatedPerPaper(): void
    {
        $pool = new ArrayAdapter();

        $rows10 = [1 => ['PAPERID' => 10]];
        $rows11 = [2 => ['PAPERID' => 11]];

        $item10 = $pool->getItem('projects_paper_10');
        $item10->set($rows10);
        $pool->save($item10);

        $item11 = $pool->getItem('projects_paper_11');
        $item11->set($rows11);
        $pool->save($item11);

        Episciences_Paper_Projects_Repository::setCachePool($pool);

        $this->assertSame($rows10, Episciences_Paper_Projects_Repository::getByPaperId(10));
        $this->assertSame($rows11, Episciences_Paper_Projects_Repository::getByPaperId(11));
    }

    // ---------------------------------------------------------------
    // getByPaperIdAndSourceId() — cache hit path (no DB needed)
    // ---------------------------------------------------------------

    public function testGetByPaperIdAndSourceIdReturnsCachedValueOnHit(): void
    {
        $pool = new ArrayAdapter();
        $paperId = 20;
        $sourceId = 3;
        $cachedRows = [
            5 => ['PAPERID' => 20, 'source_id' => 3],
        ];

        $key = 'projects_paper_' . $paperId . '_source_' . $sourceId;
        $item = $pool->getItem($key);
        $item->set($cachedRows);
        $pool->save($item);

        Episciences_Paper_Projects_Repository::setCachePool($pool);

        $result = Episciences_Paper_Projects_Repository::getByPaperIdAndSourceId($paperId, $sourceId);
        $this->assertSame($cachedRows, $result);
    }

    public function testGetByPaperIdAndSourceIdCacheKeyIncludesSourceId(): void
    {
        $pool = new ArrayAdapter();
        $paperId = 30;

        $rowsSrc1 = [1 => ['source_id' => 1]];
        $rowsSrc2 = [2 => ['source_id' => 2]];

        $item1 = $pool->getItem('projects_paper_30_source_1');
        $item1->set($rowsSrc1);
        $pool->save($item1);

        $item2 = $pool->getItem('projects_paper_30_source_2');
        $item2->set($rowsSrc2);
        $pool->save($item2);

        Episciences_Paper_Projects_Repository::setCachePool($pool);

        $this->assertSame($rowsSrc1, Episciences_Paper_Projects_Repository::getByPaperIdAndSourceId($paperId, 1));
        $this->assertSame($rowsSrc2, Episciences_Paper_Projects_Repository::getByPaperIdAndSourceId($paperId, 2));
    }

    // ---------------------------------------------------------------
    // invalidateFundingExportCaches()
    // ---------------------------------------------------------------

    public function testInvalidateFundingExportCachesDropsTheCachedFundingExportsOnly(): void
    {
        $paperId = 987654321;
        $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter(\Episciences_Paper::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA);
        $methods = ['getCrossref', 'getOpenaire', 'getDatacite', 'getTei'];

        foreach ($methods as $method) {
            $cache->save($cache->getItem($paperId . '-' . $method)->set('<xml/>'));
        }

        Episciences_Paper_Projects_Repository::invalidateFundingExportCaches($paperId);

        $this->assertFalse($cache->getItem($paperId . '-getCrossref')->isHit());
        $this->assertFalse($cache->getItem($paperId . '-getOpenaire')->isHit());
        $this->assertFalse($cache->getItem($paperId . '-getDatacite')->isHit());
        $this->assertTrue($cache->getItem($paperId . '-getTei')->isHit(), 'exports without fundings are kept');

        $cache->deleteItem($paperId . '-getTei');
    }
}
