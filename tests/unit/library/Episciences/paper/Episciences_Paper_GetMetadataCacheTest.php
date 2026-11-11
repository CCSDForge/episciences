<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Episciences_Paper::get() must not cache a failed export.
 */
final class Episciences_Paper_GetMetadataCacheTest extends TestCase
{
    private const PAPER_ID = 999999901;
    private const CACHE_KEY = self::PAPER_ID . '-getJsonV2';

    private ?FilesystemAdapter $cache = null;

    protected function setUp(): void
    {
        $this->cache = new FilesystemAdapter(Episciences_Paper::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA);
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        $this->cache?->deleteItem(self::CACHE_KEY);
    }

    public function testFailedJsonExportIsNotCached(): void
    {
        self::assertNull($this->paperReturning(null)->get('json', 2));
        self::assertFalse($this->cache->hasItem(self::CACHE_KEY));

        // Once the export is repaired, the next call generates and caches the document
        $json = '{"paper":"ok"}';
        self::assertSame($json, $this->paperReturning($json)->get('json', 2));
        self::assertTrue($this->cache->hasItem(self::CACHE_KEY));
    }

    public function testSuccessfulJsonExportIsServedFromCache(): void
    {
        $json = '{"paper":"cached"}';
        $this->paperReturning($json)->get('json', 2);

        $paper = $this->paperReturning('{"paper":"fresh"}');
        $paper->expects(self::never())->method('toJson');

        self::assertSame($json, $paper->get('json', 2));
    }

    private function paperReturning(?string $json): Episciences_Paper&MockObject
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['toJson', 'getPaperid', 'isPublished'])
            ->getMock();
        $paper->method('getPaperid')->willReturn(self::PAPER_ID);
        $paper->method('isPublished')->willReturn(false);
        $paper->method('toJson')->willReturn($json);

        return $paper;
    }
}
