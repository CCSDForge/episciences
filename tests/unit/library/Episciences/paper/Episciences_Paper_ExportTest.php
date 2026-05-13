<?php

namespace unit\library\Episciences\paper;

require_once __DIR__ . '/../../../../../library/Episciences/Paper/Export.php';

use Episciences\Paper\Export;
use Episciences_Paper;
use Episciences_Paper_XmlExportManager as XmlExportManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class Episciences_Paper_ExportTest extends TestCase
{
    /**
     * Helper method to call private/protected methods for testing
     *
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws ReflectionException
     */
    private function callPrivateMethod(string $methodName, array $parameters)
    {
        $reflection = new ReflectionClass(Export::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $parameters);
    }

    /**
     * Test parseVolumeString with "Volume X, Issue Y" format
     */
    public function testParseVolumeStringWithVolumeAndIssue(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Volume X" format (no issue)
     */
    public function testParseVolumeStringWithVolumeOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13']);
        $this->assertSame('13', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "Vol. X, Issue Y" format
     */
    public function testParseVolumeStringWithVolAbbreviation(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Vol. 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Vol. X" format (no issue)
     */
    public function testParseVolumeStringWithVolAbbreviationOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Vol. 15']);
        $this->assertSame('15', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "Tome X, Issue Y" format (French)
     */
    public function testParseVolumeStringWithTome(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Tome 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Tome X" format (no issue)
     */
    public function testParseVolumeStringWithTomeOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Tome 20']);
        $this->assertSame('20', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "X, Issue Y" format (numeric only)
     */
    public function testParseVolumeStringNumericWithIssue(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "X" format (numeric only, no issue)
     */
    public function testParseVolumeStringNumericOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['13']);
        $this->assertSame('13', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with whitespace variations
     */
    public function testParseVolumeStringWithExtraWhitespace(): void
    {
        // Extra spaces
        $result = $this->callPrivateMethod('parseVolumeString', ['  Volume  13  ,  Issue  2  ']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);

        // Tabs
        $result = $this->callPrivateMethod('parseVolumeString', ["Volume\t13,\tIssue\t2"]);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with case variations
     */
    public function testParseVolumeStringCaseInsensitive(): void
    {
        // Uppercase
        $result = $this->callPrivateMethod('parseVolumeString', ['VOLUME 13, ISSUE 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);

        // Mixed case
        $result = $this->callPrivateMethod('parseVolumeString', ['vOlUmE 13, iSsUe 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with large numbers
     */
    public function testParseVolumeStringWithLargeNumbers(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 999, Issue 888']);
        $this->assertSame('999', $result['volume']);
        $this->assertSame('888', $result['issue']);
    }

    /**
     * Test parseVolumeString with single digit numbers
     */
    public function testParseVolumeStringWithSingleDigit(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 1, Issue 2']);
        $this->assertSame('1', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString returns original value for unrecognized formats
     */
    public function testParseVolumeStringFallbackToOriginal(): void
    {
        // Invalid format - should return original
        $result = $this->callPrivateMethod('parseVolumeString', ['Invalid Format']);
        $this->assertSame('Invalid Format', $result['volume']);
        $this->assertNull($result['issue']);

        // Text without numbers
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume ABC']);
        $this->assertSame('Volume ABC', $result['volume']);
        $this->assertNull($result['issue']);

        // Empty string
        $result = $this->callPrivateMethod('parseVolumeString', ['']);
        $this->assertSame('', $result['volume']);
        $this->assertNull($result['issue']);

        // Complex string
        $result = $this->callPrivateMethod('parseVolumeString', ['Special Edition 2024']);
        $this->assertSame('Special Edition 2024', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with edge cases
     */
    public function testParseVolumeStringEdgeCases(): void
    {
        // Just "Volume" without number
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume']);
        $this->assertSame('Volume', $result['volume']);
        $this->assertNull($result['issue']);

        // Number with "Issue" but no volume
        $result = $this->callPrivateMethod('parseVolumeString', ['Issue 5']);
        $this->assertSame('Issue 5', $result['volume']);
        $this->assertNull($result['issue']);

        // Volume with comma but no issue
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 10,']);
        $this->assertSame('Volume 10,', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with zero values
     */
    public function testParseVolumeStringWithZero(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 0, Issue 0']);
        $this->assertSame('0', $result['volume']);
        $this->assertSame('0', $result['issue']);
    }

    /**
     * Test parseVolumeString return structure
     */
    public function testParseVolumeStringReturnStructure(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13, Issue 2']);

        // Should be an array
        $this->assertIsArray($result);

        // Should have exactly 2 keys
        $this->assertCount(2, $result);

        // Should have 'volume' and 'issue' keys
        $this->assertArrayHasKey('volume', $result);
        $this->assertArrayHasKey('issue', $result);
    }

    // -----------------------------------------------------------------------
    // Fixtures for buildCslFromDocumentArray / selectTitle
    // -----------------------------------------------------------------------

    private function journalDoc(): array
    {
        return [
            XmlExportManager::JOURNAL_KEY => [
                XmlExportManager::JOURNAL_METADATA_KEY => ['full_title' => 'Journal of Example Science'],
                XmlExportManager::JOURNAL_ARTICLE_KEY  => [
                    'doi_data'         => ['doi' => '10.1234/example.2024.001'],
                    'publication_date' => ['year' => '2024'],
                    'contributors'     => [
                        'person_name' => [
                            ['given_name' => 'Alice', 'surname' => 'Smith'],
                            ['given_name' => 'Bob',   'surname' => 'Jones'],
                        ],
                    ],
                    'titles' => ['title' => 'An Example Article'],
                ],
            ],
            XmlExportManager::DATABASE_KEY => [
                'current' => [
                    'type'    => ['title' => 'article'],
                    'version' => '1',
                    'volume'  => [
                        'id'       => 42,
                        'position' => 3,
                        'titles'   => ['en' => 'Volume One', 'fr' => 'Tome Un'],
                    ],
                    'section' => [
                        'id'     => 7,
                        'titles' => ['en' => 'Research Articles', 'fr' => 'Articles de recherche'],
                    ],
                ],
            ],
        ];
    }

    private function conferenceDoc(): array
    {
        return [
            XmlExportManager::CONFERENCE_KEY => [
                'event_metadata' => [
                    'conference_name'     => 'Example Conference',
                    'conference_location' => 'Paris, France',
                    'conference_date'     => ['@start_year' => '2024'],
                ],
                XmlExportManager::CONFERENCE_PAPER_KEY => [
                    'doi_data'                              => ['doi' => '10.1234/conf.2024.001'],
                    XmlExportManager::CONFERENCE_PAPER_KEY => ['year' => '2024'],
                    'contributors'                         => [
                        'person_name' => [
                            ['given_name' => 'Charlie', 'surname' => 'Brown'],
                        ],
                    ],
                ],
            ],
            XmlExportManager::DATABASE_KEY => [
                'current' => [
                    'type'    => ['title' => 'conferencepaper'],
                    'version' => '2',
                    'volume'  => null,
                    'section' => null,
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — type mapping
    // -----------------------------------------------------------------------

    public function testBuildCslArticleTypeMappedToArticleJournal(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('article-journal', $csl['type']);
    }

    public function testBuildCslDatasetTypeUnchanged(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['type']['title'] = Episciences_Paper::DATASET_TYPE_TITLE;
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame(Episciences_Paper::DATASET_TYPE_TITLE, $csl['type']);
    }

    public function testBuildCslPreprintTypeUnchanged(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['type']['title'] = Episciences_Paper::DEFAULT_TYPE_TITLE;
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $csl['type']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — journal fields
    // -----------------------------------------------------------------------

    public function testBuildCslIdIsDoiUrl(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('https://doi.org/10.1234/example.2024.001', $csl['id']);
    }

    public function testBuildCslContainerTitleFromJournalFullTitle(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('Journal of Example Science', $csl['container-title']);
    }

    public function testBuildCslNoPublisherKey(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertArrayNotHasKey('publisher', $csl);
    }

    public function testBuildCslTitleFromArticle(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('An Example Article', $csl['title']);
    }

    public function testBuildCslDoiField(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('10.1234/example.2024.001', $csl['DOI']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — authors
    // -----------------------------------------------------------------------

    public function testBuildCslMultipleAuthors(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertCount(2, $csl['author']);
        self::assertSame('Smith', $csl['author'][0]['family']);
        self::assertSame('Alice', $csl['author'][0]['given']);
        self::assertSame('Jones', $csl['author'][1]['family']);
        self::assertSame('Bob',   $csl['author'][1]['given']);
    }

    public function testBuildCslSingleAuthorWrappedCorrectly(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::JOURNAL_KEY][XmlExportManager::JOURNAL_ARTICLE_KEY]['contributors']['person_name'] = [
            'given_name' => 'Eve', 'surname' => 'Martin',
        ];
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertCount(1, $csl['author']);
        self::assertSame('Martin', $csl['author'][0]['family']);
        self::assertSame('Eve',    $csl['author'][0]['given']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — issued date
    // -----------------------------------------------------------------------

    public function testBuildCslIssuedDatePartsStructure(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame([['2024']], $csl['issued']['date-parts']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — volume title
    // -----------------------------------------------------------------------

    public function testBuildCslVolumeUsesEnglishTitle(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('Volume One', $csl['volume']);
    }

    public function testBuildCslVolumeFallbackToFirstTitleWhenNoEnglish(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['volume']['titles'] = ['fr' => 'Tome Un'];
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame('Tome Un', $csl['volume']);
    }

    public function testBuildCslVolumeNullWhenNoVolume(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['volume'] = null;
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertNull($csl['volume']);
    }

    public function testBuildCslVolumeNullWhenEmptyTitles(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['volume']['titles'] = [];
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertNull($csl['volume']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — number (from volume position)
    // -----------------------------------------------------------------------

    public function testBuildCslNumberFromVolumePosition(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame(3, $csl['number']);
    }

    public function testBuildCslNumberAbsentWhenVolumeHasNoPosition(): void
    {
        $doc = $this->journalDoc();
        unset($doc[XmlExportManager::DATABASE_KEY]['current']['volume']['position']);
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertArrayNotHasKey('number', $csl);
    }

    public function testBuildCslNumberAbsentWhenNoVolume(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['volume'] = null;
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertArrayNotHasKey('number', $csl);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — issue (section title)
    // -----------------------------------------------------------------------

    public function testBuildCslIssueUsesEnglishSectionTitle(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('Research Articles', $csl['issue']);
    }

    public function testBuildCslIssueFallbackToFirstTitleWhenNoEnglish(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['section']['titles'] = ['fr' => 'Articles de recherche'];
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame('Articles de recherche', $csl['issue']);
    }

    public function testBuildCslIssueNullWhenNoSection(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::DATABASE_KEY]['current']['section'] = null;
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertNull($csl['issue']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — ISSN
    // -----------------------------------------------------------------------

    public function testBuildCslIssnSetWhenPresent(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::JOURNAL_KEY][XmlExportManager::JOURNAL_METADATA_KEY]['issn'] = ['value' => '1234-5678'];
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame('1234-5678', $csl['ISSN']);
    }

    public function testBuildCslIssnAbsentWhenMissing(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertArrayNotHasKey('ISSN', $csl);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — language
    // -----------------------------------------------------------------------

    public function testBuildCslLanguageSetWhenPresent(): void
    {
        $doc = $this->journalDoc();
        $doc[XmlExportManager::JOURNAL_KEY][XmlExportManager::JOURNAL_ARTICLE_KEY]['@language'] = 'en';
        $csl = Export::buildCslFromDocumentArray($doc);
        self::assertSame('en', $csl['language']);
    }

    public function testBuildCslLanguageAbsentWhenMissing(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertArrayNotHasKey('language', $csl);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — version
    // -----------------------------------------------------------------------

    public function testBuildCslVersionPassedThrough(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->journalDoc());
        self::assertSame('1', $csl['version']);
    }

    // -----------------------------------------------------------------------
    // buildCslFromDocumentArray — conference paper
    // -----------------------------------------------------------------------

    public function testBuildCslConferencePaperTypeUnchanged(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->conferenceDoc());
        self::assertSame('conferencepaper', $csl['type']);
    }

    public function testBuildCslConferencePaperEventFields(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->conferenceDoc());
        self::assertSame('Example Conference', $csl['event-title']);
        self::assertSame('Paris, France',      $csl['event-place']);
        self::assertSame('2024',               $csl['event-date']);
    }

    public function testBuildCslConferencePaperHasNoContainerTitle(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->conferenceDoc());
        self::assertArrayNotHasKey('container-title', $csl);
    }

    public function testBuildCslConferencePaperIdIsDoiUrl(): void
    {
        $csl = Export::buildCslFromDocumentArray($this->conferenceDoc());
        self::assertSame('https://doi.org/10.1234/conf.2024.001', $csl['id']);
    }

    // -----------------------------------------------------------------------
    // selectTitle (private) — via reflection
    // -----------------------------------------------------------------------

    public function testSelectTitleReturnsEnglishWhenAvailable(): void
    {
        $result = $this->callPrivateMethod('selectTitle', [['en' => 'English Title', 'fr' => 'Titre français']]);
        self::assertSame('English Title', $result);
    }

    public function testSelectTitleFallbacksToFirstEntryWhenNoEnglish(): void
    {
        $result = $this->callPrivateMethod('selectTitle', [['fr' => 'Titre français', 'de' => 'Deutscher Titel']]);
        self::assertSame('Titre français', $result);
    }

    public function testSelectTitleReturnsNullForEmptyArray(): void
    {
        $result = $this->callPrivateMethod('selectTitle', [[]]);
        self::assertNull($result);
    }

    public function testSelectTitleReturnsSoleEntry(): void
    {
        $result = $this->callPrivateMethod('selectTitle', [['ja' => 'タイトル']]);
        self::assertSame('タイトル', $result);
    }
}
