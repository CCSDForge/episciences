<?php

namespace unit\library\Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Build\VolumeSectionResolver;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Episciences_Review;
use Episciences_Section;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for VolumeSectionResolver. DB-touching static Manager calls are
 * bypassed by pre-seeding the constructor-injected cache directly, so no
 * database or reflection is needed to reach the cached-lookup path.
 */
class VolumeSectionResolverTest extends TestCase
{
    /** @param array<string, string>|null $titles */
    private function makeVolume(int $status, ?array $titles): Episciences_Volume
    {
        $volume = $this->getMockBuilder(Episciences_Volume::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStatus', 'getTitles'])
            ->getMock();
        $volume->method('getStatus')->willReturn($status);
        $volume->method('getTitles')->willReturn($titles);

        return $volume;
    }

    /** @param array<string, string>|null $titles */
    private function makeSection(?array $titles): Episciences_Section
    {
        $section = $this->getMockBuilder(Episciences_Section::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTitles'])
            ->getMock();
        $section->method('getTitles')->willReturn($titles);

        return $section;
    }

    public function testWithVolumeReturnsUnchangedDocumentForVidZero(): void
    {
        $resolver = new VolumeSectionResolver(new ArrayAdapter(0, false));

        self::assertSame([], $resolver->withVolume(SolrDocument::empty(), 0)->toArray());
    }

    public function testWithVolumeUsesCachedVolumeAndPrefersEnglishTitle(): void
    {
        $cache = new ArrayAdapter(0, false);
        $item = $cache->getItem('volume.5');
        $cache->save($item->set($this->makeVolume(1, ['fr' => 'Titre', 'en' => 'Title'])));

        $document = (new VolumeSectionResolver($cache))->withVolume(SolrDocument::empty(), 5);
        $fields = $document->toArray();

        self::assertSame([5], $fields['volume_id_i']);
        self::assertSame([1], $fields['volume_status_i']);
        self::assertSame(['5_FacetSep_Title'], $fields['volume_fs']);
        self::assertSame(['Titre'], $fields['fr_volume_title_t']);
        self::assertSame(['Title'], $fields['en_volume_title_t']);
    }

    public function testWithVolumeReturnsUnchangedDocumentWhenVolumeNotFound(): void
    {
        // Pre-seed the cache with the resolver's own missing-value result
        // (false) instead of relying on a real cache miss, which would call
        // the DB-backed Episciences_VolumesManager::find() — the resolver
        // must leave the document unchanged rather than write partial volume
        // fields, without this test depending on database availability.
        $cache = new ArrayAdapter(0, false);
        $item = $cache->getItem('volume.999999');
        $cache->save($item->set(false));

        $resolver = new VolumeSectionResolver($cache);

        $result = $resolver->withVolume(SolrDocument::empty(), 999999);

        self::assertArrayNotHasKey('volume_id_i', $result->toArray());
    }

    public function testWithSectionReturnsUnchangedDocumentForSectionIdZero(): void
    {
        $resolver = new VolumeSectionResolver(new ArrayAdapter(0, false));

        self::assertSame([], $resolver->withSection(SolrDocument::empty(), 0)->toArray());
    }

    public function testWithSectionUsesCachedSectionAndPrefersEnglishTitle(): void
    {
        $cache = new ArrayAdapter(0, false);
        $item = $cache->getItem('section.7');
        $cache->save($item->set($this->makeSection(['fr' => 'Section FR', 'en' => 'Section EN'])));

        $document = (new VolumeSectionResolver($cache))->withSection(SolrDocument::empty(), 7);
        $fields = $document->toArray();

        self::assertSame([7], $fields['section_id_i']);
        self::assertSame(['7_FacetSep_Section EN'], $fields['section_fs']);
        self::assertSame(['Section FR'], $fields['fr_section_title_t']);
        self::assertSame(['Section EN'], $fields['en_section_title_t']);
    }

    public function testResolveJournalReturnsCachedJournalWithoutTouchingDb(): void
    {
        $journal = $this->getMockBuilder(Episciences_Review::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cache = new ArrayAdapter(0, false);
        $item = $cache->getItem('rvid.42');
        $cache->save($item->set($journal));

        $resolver = new VolumeSectionResolver($cache);

        self::assertSame($journal, $resolver->resolveJournal(42));
    }

    public function testResolveJournalThrowsWhenJournalNotFound(): void
    {
        // Cache miss on a RVID that does not exist in DB: getData()/findByRvid()
        // returns false — resolveJournal() must fail predictably instead of
        // crashing with a TypeError when constructing Episciences_Review.
        $resolver = new VolumeSectionResolver(new ArrayAdapter(0, false));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Journal config for RVID 999999 not found.');

        $resolver->resolveJournal(999999);
    }
}
