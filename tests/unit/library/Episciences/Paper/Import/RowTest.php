<?php

namespace unit\library\Episciences\Paper\Import;

use Episciences\Paper\Import\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function fullRow(): array
    {
        return [
            'HAL-001',     // identifier
            '3',           // repoid
            '2',           // version
            '16',          // status
            '10',          // volume_id
            'Titre FR',    // volume_title_fr
            'Title EN',    // volume_title_en
            '5',           // volume_num
            '2024',        // volume_year
            '20',          // section_id
            'Section FR',  // section_title_fr
            'Section EN',  // section_title_en
            '42',          // uid
            '2024-01-01',  // publication_date
            '1-2',         // editors
            '10.1234/abc', // doi
            '999',         // docid
            '7',           // rvid
            '2023-12-01',  // submission_date
        ];
    }

    public function testFromCsvRowParsesAllColumns(): void
    {
        $row = Row::fromCsvRow($this->fullRow());

        $this->assertSame('HAL-001', $row->identifier);
        $this->assertSame(3, $row->repoid);
        $this->assertSame('2', $row->version);
        $this->assertSame(16, $row->status);
        $this->assertSame(10, $row->volumeId);
        $this->assertSame('Titre FR', $row->volumeTitleFr);
        $this->assertSame('Title EN', $row->volumeTitleEn);
        $this->assertSame('5', $row->volumeNum);
        $this->assertSame('2024', $row->volumeYear);
        $this->assertSame(20, $row->sectionId);
        $this->assertSame('Section FR', $row->sectionTitleFr);
        $this->assertSame('Section EN', $row->sectionTitleEn);
        $this->assertSame(42, $row->uid);
        $this->assertSame('2024-01-01', $row->publicationDate);
        $this->assertSame('1-2', $row->editors);
        $this->assertSame('10.1234/abc', $row->doi);
        $this->assertSame(999, $row->docid);
        $this->assertSame('7', $row->rvidOrCode);
        $this->assertSame('2023-12-01', $row->submissionDate);
    }

    public function testMissingTrailingColumnsBecomeNull(): void
    {
        $row = Row::fromCsvRow(['HAL-001', '3']);

        $this->assertSame('HAL-001', $row->identifier);
        $this->assertSame(3, $row->repoid);
        $this->assertNull($row->version);
        $this->assertNull($row->volumeId);
        $this->assertNull($row->rvidOrCode);
    }

    public function testBlankAndWhitespaceColumnsBecomeNull(): void
    {
        $data = array_fill(0, 19, '');
        $data[0] = '   ';

        $row = Row::fromCsvRow($data);

        $this->assertNull($row->identifier);
        $this->assertNull($row->repoid);
        $this->assertNull($row->rvidOrCode);
    }

    public function testVolumeTitlesFiltersEmptyLanguages(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_VOLUME_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame(['fr' => 'Titre FR'], $row->volumeTitles());
    }

    public function testVolumeTitlesEmptyWhenBothLanguagesBlank(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_VOLUME_TITLE_FR] = '';
        $data[Row::COL_VOLUME_TITLE_EN] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame([], $row->volumeTitles());
    }

    public function testSectionTitlesFiltersEmptyLanguages(): void
    {
        $data = $this->fullRow();
        $data[Row::COL_SECTION_TITLE_FR] = '';

        $row = Row::fromCsvRow($data);

        $this->assertSame(['en' => 'Section EN'], $row->sectionTitles());
    }
}
