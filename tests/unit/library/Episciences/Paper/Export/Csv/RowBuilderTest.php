<?php

namespace unit\library\Episciences\Paper\Export\Csv;

use Episciences\Paper\Export\Csv\RowBuilder;
use Episciences_Paper;
use Episciences_Section;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for RowBuilder::build().
 *
 * Episciences_Paper/_Volume/_Section are hydrated directly from an options array (no DB query
 * triggered by their constructors); Paper::getEditors() would otherwise hit the DB, so its
 * private _editors cache is seeded via reflection instead — same workaround already used
 * elsewhere in this suite for DB-backed lazy getters.
 */
class RowBuilderTest extends TestCase
{
    private function makePaper(array $options, array $editors = []): Episciences_Paper
    {
        $paper = new Episciences_Paper($options);

        $property = new ReflectionProperty(Episciences_Paper::class, '_editors');
        $property->setAccessible(true);
        $property->setValue($paper, $editors);

        return $paper;
    }

    private function fullOptions(): array
    {
        return [
            'DOCID' => 999,
            'RVID' => 7,
            'VID' => 10,
            'SID' => 20,
            'UID' => 42,
            'STATUS' => 16,
            'IDENTIFIER' => 'HAL-001',
            'REPOID' => 3,
            'VERSION' => 2.0,
            'DOI' => '10.1234/abc',
            'PUBLICATION_DATE' => '2024-01-01 00:00:00',
            'SUBMISSION_DATE' => '2023-12-01 00:00:00',
        ];
    }

    public function testBuildMapsAllScalarFieldsWithVolumeAndSection(): void
    {
        $paper = $this->makePaper($this->fullOptions(), [12 => [], 34 => []]);
        $volume = new Episciences_Volume([
            'VID' => 10,
            'TITLES' => ['fr' => 'Titre FR', 'en' => 'Title EN'],
            'VOL_NUM' => '5',
            'VOL_YEAR' => '2024',
        ]);
        $section = new Episciences_Section([
            'SID' => 20,
            'TITLES' => ['fr' => 'Section FR', 'en' => 'Section EN'],
        ]);

        $row = RowBuilder::build($paper, $volume, $section);

        self::assertSame('HAL-001', $row->identifier);
        self::assertSame(3, $row->repoid);
        self::assertSame('2', $row->version);
        self::assertSame(16, $row->status);
        self::assertSame('16', $row->rawStatus);
        self::assertSame(10, $row->volumeId);
        self::assertSame('Titre FR', $row->volumeTitleFr);
        self::assertSame('Title EN', $row->volumeTitleEn);
        self::assertSame('5', $row->volumeNum);
        self::assertSame('2024', $row->volumeYear);
        self::assertSame(20, $row->sectionId);
        self::assertSame('Section FR', $row->sectionTitleFr);
        self::assertSame('Section EN', $row->sectionTitleEn);
        self::assertSame(42, $row->uid);
        self::assertSame('2024-01-01 00:00:00', $row->publicationDate);
        self::assertSame('12-34', $row->editors);
        self::assertSame('10.1234/abc', $row->doi);
        self::assertSame(999, $row->docid);
        self::assertSame('7', $row->rvidOrCode);
        self::assertSame('2023-12-01 00:00:00', $row->submissionDate);
    }

    public function testBuildWithoutVolumeOrSectionLeavesTitlesNull(): void
    {
        $options = $this->fullOptions();
        $options['VID'] = 0;
        $options['SID'] = 0;

        $row = RowBuilder::build($this->makePaper($options), null, null);

        self::assertNull($row->volumeId);
        self::assertNull($row->volumeTitleFr);
        self::assertNull($row->volumeTitleEn);
        self::assertNull($row->volumeNum);
        self::assertNull($row->volumeYear);
        self::assertNull($row->sectionId);
        self::assertNull($row->sectionTitleFr);
        self::assertNull($row->sectionTitleEn);
    }

    // No "empty editors" case here: Episciences_Paper::getEditors() treats an empty _editors
    // cache as "not loaded yet" and refetches from the DB regardless — that branch needs a real
    // DB, like the DB-dependent resolvers in Episciences\Paper\Import are left untested here too.

    /**
     * @dataProvider versionProvider
     */
    public function testBuildFormatsVersionWithoutTrailingZeros(float $version, string $expected): void
    {
        $options = $this->fullOptions();
        $options['VERSION'] = $version;

        $row = RowBuilder::build($this->makePaper($options), null, null);

        self::assertSame($expected, $row->version);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function versionProvider(): array
    {
        return [
            'whole number' => [1.0, '1'],
            'decimal' => [2.5, '2.5'],
            'zero' => [0.0, '0'],
            'trailing zero decimal' => [1.10, '1.1'],
        ];
    }

    public function testBuildTreatsEmptyDoiAsNull(): void
    {
        $options = $this->fullOptions();
        $options['DOI'] = '';

        $row = RowBuilder::build($this->makePaper($options), null, null);

        self::assertNull($row->doi);
    }
}
