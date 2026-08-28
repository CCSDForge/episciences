<?php

namespace unit\library\Episciences\Paper\Export\Csv;

use Episciences\Paper\Export\Csv\Filters;
use PHPUnit\Framework\TestCase;

class FiltersTest extends TestCase
{
    public function testFromOptionsMapsAllScalarFilters(): void
    {
        $filters = Filters::fromOptions([
            'volume-id' => '10',
            'section-id' => '20',
            'year' => '2024',
            'identifier' => 'hal-04123456',
            'version' => '2',
            'repoid' => '1',
            'uid' => '42',
            'sql-where' => "STATUS = 4",
        ], 7);

        self::assertSame(7, $filters->rvid);
        self::assertSame(10, $filters->volumeId);
        self::assertSame(20, $filters->sectionId);
        self::assertSame(2024, $filters->year);
        self::assertSame('hal-04123456', $filters->identifier);
        self::assertSame('2', $filters->version);
        self::assertSame(1, $filters->repoid);
        self::assertSame(42, $filters->uid);
        self::assertSame('STATUS = 4', $filters->sqlWhere);
        self::assertFalse($filters->versionIgnored);
    }

    public function testFromOptionsDefaultsToEmptyWhenNoOptionsGiven(): void
    {
        $filters = Filters::fromOptions([], 7);

        self::assertSame(7, $filters->rvid);
        self::assertNull($filters->volumeId);
        self::assertNull($filters->sectionId);
        self::assertNull($filters->year);
        self::assertSame([], $filters->docids);
        self::assertNull($filters->identifier);
        self::assertNull($filters->version);
        self::assertSame([], $filters->statuses);
        self::assertNull($filters->repoid);
        self::assertNull($filters->uid);
        self::assertNull($filters->sqlWhere);
        self::assertFalse($filters->versionIgnored);
    }

    public function testFromOptionsParsesRepeatableDocidOption(): void
    {
        $filters = Filters::fromOptions(['docid' => ['12', '34', '12']], 7);

        self::assertSame([12, 34], $filters->docids);
    }

    public function testFromOptionsAcceptsASingleDocidNotWrappedInArray(): void
    {
        $filters = Filters::fromOptions(['docid' => '12'], 7);

        self::assertSame([12], $filters->docids);
    }

    public function testFromOptionsParsesRepeatableStatusOption(): void
    {
        $filters = Filters::fromOptions(['status' => ['4', '16']], 7);

        self::assertSame([4, 16], $filters->statuses);
    }

    public function testFromOptionsIgnoresNonNumericValues(): void
    {
        $filters = Filters::fromOptions([
            'volume-id' => 'abc',
            'docid' => ['abc', '12'],
        ], 7);

        self::assertNull($filters->volumeId);
        self::assertSame([12], $filters->docids);
    }

    public function testFromOptionsDropsVersionWithoutIdentifier(): void
    {
        $filters = Filters::fromOptions(['version' => '2'], 7);

        self::assertNull($filters->version);
        self::assertTrue($filters->versionIgnored);
    }

    public function testFromOptionsKeepsVersionWhenIdentifierIsGiven(): void
    {
        $filters = Filters::fromOptions(['identifier' => 'hal-04123456', 'version' => '2'], 7);

        self::assertSame('2', $filters->version);
        self::assertFalse($filters->versionIgnored);
    }

    public function testFromOptionsTreatsBlankStringsAsAbsent(): void
    {
        $filters = Filters::fromOptions(['identifier' => '   ', 'sql-where' => ''], 7);

        self::assertNull($filters->identifier);
        self::assertNull($filters->sqlWhere);
    }
}
