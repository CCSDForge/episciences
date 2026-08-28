<?php

namespace unit\library\Episciences\Section\Import;

use Episciences\Section\Import\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function fullRow(): array
    {
        return [
            '10',           // rvid
            '2',            // position
            'Section titre', // title_fr
            'Section title', // title_en
            'Description fr', // description_fr
            'Description en', // description_en
            '1',            // status
        ];
    }

    public function testFromCsvRowParsesAllColumns(): void
    {
        $row = Row::fromCsvRow($this->fullRow());

        $this->assertSame(10, $row->rvid);
        $this->assertSame(2, $row->position);
        $this->assertSame('Section titre', $row->titleFr);
        $this->assertSame('Section title', $row->titleEn);
        $this->assertSame('Description fr', $row->descriptionFr);
        $this->assertSame('Description en', $row->descriptionEn);
        $this->assertSame('1', $row->status);
    }

    public function testMissingTrailingColumnsBecomeNull(): void
    {
        $row = Row::fromCsvRow(['10']);

        $this->assertSame(10, $row->rvid);
        $this->assertNull($row->position);
        $this->assertNull($row->titleFr);
        $this->assertNull($row->status);
    }

    public function testBlankAndWhitespaceColumnsBecomeNull(): void
    {
        $data = array_fill(0, 7, '');
        $data[0] = '   ';

        $row = Row::fromCsvRow($data);

        $this->assertNull($row->rvid);
        $this->assertNull($row->titleFr);
        $this->assertNull($row->titleEn);
    }

    public function testTitlesFiltersEmptyLanguages(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame(['fr' => 'Section titre'], $row->titles());
    }

    public function testDescriptionsForTitlesExcludesLanguageWithoutTitle(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);
        $titles = $row->titles();

        $this->assertSame(['fr' => 'Description fr'], $row->descriptionsForTitles($titles));
    }

    public function testOrphanedDescriptionLanguagesDetectsMismatch(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);
        $titles = $row->titles();

        $this->assertSame(['en'], $row->orphanedDescriptionLanguages($titles));
    }

    public function testOrphanedDescriptionLanguagesEmptyWhenAllMatch(): void
    {
        $row = Row::fromCsvRow($this->fullRow());
        $titles = $row->titles();

        $this->assertSame([], $row->orphanedDescriptionLanguages($titles));
    }

    /**
     * @return array<string, array{?string, int}>
     */
    public static function statusProvider(): array
    {
        return [
            'null defaults to open' => [null, \Episciences_Section::SECTION_OPEN_STATUS],
            'empty defaults to open' => ['', \Episciences_Section::SECTION_OPEN_STATUS],
            'whitespace defaults to open' => ['  ', \Episciences_Section::SECTION_OPEN_STATUS],
            'open status "1"' => ['1', \Episciences_Section::SECTION_OPEN_STATUS],
            'closed status "0"' => ['0', \Episciences_Section::SECTION_CLOSED_STATUS],
            'invalid falls back to open' => ['99', \Episciences_Section::SECTION_OPEN_STATUS],
            'invalid text falls back' => ['abc', \Episciences_Section::SECTION_OPEN_STATUS],
        ];
    }

    /** @dataProvider statusProvider */
    public function testParseStatus(?string $raw, int $expected): void
    {
        $this->assertSame($expected, Row::parseStatus($raw));
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function statusInvalidProvider(): array
    {
        return [
            'null is not invalid' => [null, false],
            'empty is not invalid' => ['', false],
            'whitespace is not invalid' => ['  ', false],
            'open status "1" is valid' => ['1', false],
            'closed status "0" is valid' => ['0', false],
            'out of range is invalid' => ['99', true],
            'non numeric is invalid' => ['abc', true],
        ];
    }

    /** @dataProvider statusInvalidProvider */
    public function testIsStatusInvalid(?string $raw, bool $expected): void
    {
        $this->assertSame($expected, Row::isStatusInvalid($raw));
    }

    /**
     * A malformed rvid must become null (row rejected/skipped upstream), never 0 — casting
     * blindly to (int) would silently target journal 0 instead of failing loudly.
     */
    public function testMalformedRvidBecomesNullInsteadOfZero(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_RVID] = 'abc';

        $row = Row::fromCsvRow($data);

        $this->assertNull($row->rvid);
    }

    public function testMalformedPositionBecomesNullInsteadOfZero(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_POSITION] = '2.5';

        $row = Row::fromCsvRow($data);

        $this->assertNull($row->position);
    }
}
