<?php

namespace unit\library\Episciences\Volume\Import;

use Episciences\Volume\Import\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function fullRow(): array
    {
        return [
            '3',            // position
            '1',            // status
            '0',            // current_issue
            '0',            // special_issue
            'Ref. 2024-01', // bib_reference
            'Special issue 2024', // title_en
            'Numéro spécial 2024', // title_fr
            'An english description', // description_en
            'Une description en français', // description_fr
            '5',            // num
            '2024',         // year
        ];
    }

    public function testFromCsvRowParsesAllColumns(): void
    {
        $row = Row::fromCsvRow($this->fullRow());

        $this->assertSame('3', $row->position);
        $this->assertSame('1', $row->status);
        $this->assertSame('0', $row->currentIssue);
        $this->assertSame('0', $row->specialIssue);
        $this->assertSame('Ref. 2024-01', $row->bibReference);
        $this->assertSame('Special issue 2024', $row->titleEn);
        $this->assertSame('Numéro spécial 2024', $row->titleFr);
        $this->assertSame('An english description', $row->descriptionEn);
        $this->assertSame('Une description en français', $row->descriptionFr);
        $this->assertSame('5', $row->num);
        $this->assertSame('2024', $row->year);
    }

    public function testMissingTrailingColumnsBecomeNull(): void
    {
        $row = Row::fromCsvRow(['1', '1']);

        $this->assertSame('1', $row->position);
        $this->assertNull($row->titleEn);
        $this->assertNull($row->num);
        $this->assertNull($row->year);
    }

    public function testBlankAndWhitespaceColumnsBecomeNull(): void
    {
        $data = array_fill(0, 11, '');
        $data[0] = '   ';

        $row = Row::fromCsvRow($data);

        $this->assertNull($row->position);
        $this->assertNull($row->titleEn);
        $this->assertNull($row->titleFr);
        $this->assertNull($row->num);
        $this->assertNull($row->year);
    }

    public function testTitlesFiltersEmptyLanguages(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame(['fr' => 'Numéro spécial 2024'], $row->titles());
    }

    public function testTitlesEmptyWhenBothLanguagesBlank(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_TITLE_EN] = '';
        $data[Row::COL_TITLE_FR] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame([], $row->titles());
    }

    public function testDescriptionsFiltersEmptyLanguages(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_DESC_FR] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame(['en' => 'An english description'], $row->descriptions());
    }
}
