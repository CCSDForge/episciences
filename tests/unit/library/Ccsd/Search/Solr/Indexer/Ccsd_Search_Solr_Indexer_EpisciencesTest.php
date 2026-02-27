<?php

namespace unit\library\Ccsd\Search\Solr\Indexer;

use Ccsd_Search_Solr_Indexer_Episciences;
use Episciences_Paper;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Solarium\QueryType\Update\Query\Document;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for Ccsd_Search_Solr_Indexer_Episciences.
 *
 * The indexer constructor requires a live Solr connection, so it is bypassed
 * via disableOriginalConstructor(). All tested logic is pure (no I/O).
 *
 * @covers Ccsd_Search_Solr_Indexer_Episciences
 */
class Ccsd_Search_Solr_Indexer_EpisciencesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Call a protected static method without instantiating the class.
     */
    private function callStatic(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(Ccsd_Search_Solr_Indexer_Episciences::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(null, ...$args);
    }

    /**
     * Create an indexer stub that skips the constructor entirely.
     */
    private function makeIndexer(): Ccsd_Search_Solr_Indexer_Episciences
    {
        return $this->getMockBuilder(Ccsd_Search_Solr_Indexer_Episciences::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Call a protected instance method on an existing object.
     */
    private function callMethod(object $obj, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($obj::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($obj, ...$args);
    }

    // -------------------------------------------------------------------------
    // cleanAuthorName
    // -------------------------------------------------------------------------

    public function testCleanAuthorNameTrimsWhitespace(): void
    {
        self::assertSame('Smith, John', $this->callStatic('cleanAuthorName', '  Smith, John  '));
    }

    public function testCleanAuthorNameRemovesTrailingComma(): void
    {
        self::assertSame('Smith', $this->callStatic('cleanAuthorName', 'Smith,'));
    }

    public function testCleanAuthorNameRemovesSpaceBeforeComma(): void
    {
        self::assertSame('Smith', $this->callStatic('cleanAuthorName', 'Smith ,'));
    }

    public function testCleanAuthorNameHandlesEmptyString(): void
    {
        self::assertSame('', $this->callStatic('cleanAuthorName', ''));
    }

    public function testCleanAuthorNamePreservesHyphenatedName(): void
    {
        self::assertSame('Dupont, Jean-Pierre', $this->callStatic('cleanAuthorName', 'Dupont, Jean-Pierre'));
    }

    public function testCleanAuthorNameStripsControlChars(): void
    {
        self::assertSame('Smith, John', $this->callStatic('cleanAuthorName', "Smith\x01, John\x1f"));
    }

    public function testCleanAuthorNameCollapsesDuplicateSpaces(): void
    {
        self::assertSame('Smith, John', $this->callStatic('cleanAuthorName', 'Smith,  John'));
    }

    public function testCleanAuthorNameHandlesUnicodeAccents(): void
    {
        self::assertSame('Étoile, Marie', $this->callStatic('cleanAuthorName', 'Étoile, Marie'));
    }

    // -------------------------------------------------------------------------
    // classifyAuthorFirstLetter
    // -------------------------------------------------------------------------

    public function testClassifyAsciiUppercaseLetter(): void
    {
        self::assertSame('S', $this->callStatic('classifyAuthorFirstLetter', 'Smith, John'));
    }

    public function testClassifyAsciiLowercaseIsUppercased(): void
    {
        // mb_strtoupper() is applied before matching, so lowercase input still classifies
        self::assertSame('S', $this->callStatic('classifyAuthorFirstLetter', 'smith, john'));
    }

    public function testClassifyAllAsciiLetters(): void
    {
        foreach (range('A', 'Z') as $letter) {
            self::assertSame($letter, $this->callStatic('classifyAuthorFirstLetter', $letter . 'uthor'));
        }
    }

    public function testClassifyDigitFirstCharReturnsOthers(): void
    {
        self::assertSame('Others', $this->callStatic('classifyAuthorFirstLetter', '42 Corp'));
    }

    public function testClassifySpecialCharFirstReturnsOthers(): void
    {
        self::assertSame('Others', $this->callStatic('classifyAuthorFirstLetter', '& Author'));
    }

    public function testClassifyEmptyStringReturnsOthers(): void
    {
        self::assertSame('Others', $this->callStatic('classifyAuthorFirstLetter', ''));
    }

    public function testClassifyAccentedFirstLetterReturnsOthers(): void
    {
        // Accented letters like É are not in the ASCII [A-Z] range
        self::assertSame('Others', $this->callStatic('classifyAuthorFirstLetter', 'Étienne, Paul'));
    }

    // -------------------------------------------------------------------------
    // formatAuthorName
    // -------------------------------------------------------------------------

    public function testFormatAuthorNameWithFamilyAndGiven(): void
    {
        self::assertSame(
            'Smith, John',
            $this->callStatic('formatAuthorName', ['family' => 'Smith', 'given' => 'John'])
        );
    }

    public function testFormatAuthorNameWithMissingGiven(): void
    {
        self::assertSame('Smith', $this->callStatic('formatAuthorName', ['family' => 'Smith']));
    }

    public function testFormatAuthorNameWithMissingFamily(): void
    {
        self::assertSame('John', $this->callStatic('formatAuthorName', ['given' => 'John']));
    }

    public function testFormatAuthorNameWithEmptyArray(): void
    {
        self::assertSame('', $this->callStatic('formatAuthorName', []));
    }

    public function testFormatAuthorNameTrimsInternalWhitespace(): void
    {
        self::assertSame(
            'Smith, John',
            $this->callStatic('formatAuthorName', ['family' => ' Smith ', 'given' => ' John '])
        );
    }

    // -------------------------------------------------------------------------
    // buildAuthorSortKey
    // -------------------------------------------------------------------------

    public function testBuildAuthorSortKeyWithSingleAuthor(): void
    {
        // "Smith, John" → strip spaces and commas → "SmithJohn"
        self::assertSame('SmithJohn', $this->callStatic('buildAuthorSortKey', ['Smith, John']));
    }

    public function testBuildAuthorSortKeyWithMultipleAuthors(): void
    {
        // "Smith, John Doe, Jane" → strip → "SmithJohnDoeJane"
        self::assertSame(
            'SmithJohnDoeJane',
            $this->callStatic('buildAuthorSortKey', ['Smith, John', 'Doe, Jane'])
        );
    }

    public function testBuildAuthorSortKeyTruncatesAt30Chars(): void
    {
        // "ABCDEFGHIJ, KLMNOPQRSTUVWXYZ Extra" → joined = "ABCDEFGHIJ, KLMNOPQRSTUVWXYZ Extra Author"
        // truncated at 30 → "ABCDEFGHIJ, KLMNOPQRSTUVWXYZ E"
        // strip spaces and commas → "ABCDEFGHIJKLMNOPQRSTUVWXYZE"
        $result = $this->callStatic(
            'buildAuthorSortKey',
            ['ABCDEFGHIJ, KLMNOPQRSTUVWXYZ', 'Extra Author']
        );
        self::assertSame('ABCDEFGHIJKLMNOPQRSTUVWXYZE', $result);
    }

    public function testBuildAuthorSortKeyWithEmptyArray(): void
    {
        self::assertSame('', $this->callStatic('buildAuthorSortKey', []));
    }

    // -------------------------------------------------------------------------
    // indexOneAuthor
    // -------------------------------------------------------------------------

    public function testIndexOneAuthorSetsAllFourFields(): void
    {
        $indexer = $this->makeIndexer();
        $doc = new Document();

        $this->callMethod($indexer, 'indexOneAuthor', 'Smith, John', $doc);

        $fields = $doc->getFields();
        self::assertSame('Smith, John', $fields['author_fullname_fs']);
        self::assertSame('S', $fields['authorFirstLetters_s']);
        self::assertSame('Smith, John', $fields['authorLastNameFirstNamePrefixed_fs']);
        self::assertSame('Smith, John', $fields['author_fullname_s']);
    }

    public function testIndexOneAuthorWithNonAsciiFirstLetterUsesOthersPrefix(): void
    {
        $indexer = $this->makeIndexer();
        $doc = new Document();

        $this->callMethod($indexer, 'indexOneAuthor', '123Author, Name', $doc);

        $fields = $doc->getFields();
        self::assertSame('Others', $fields['authorFirstLetters_s']);
        self::assertStringStartsWith('Others_FacetSep_', $fields['authorLastNameFirstNamePrefixed_fs']);
    }

    public function testIndexOneAuthorAppliesCleaningBeforeIndexing(): void
    {
        $indexer = $this->makeIndexer();
        $doc = new Document();

        // Trailing comma and control char should be stripped
        $this->callMethod($indexer, 'indexOneAuthor', "Dupont,\x01 Jean,", $doc);

        $fields = $doc->getFields();
        self::assertSame('Dupont, Jean', $fields['author_fullname_fs']);
        self::assertSame('D', $fields['authorFirstLetters_s']);
    }

    public function testIndexOneAuthorWithMultipleCallsCreatesMultivaluedFields(): void
    {
        $indexer = $this->makeIndexer();
        $doc = new Document();

        $this->callMethod($indexer, 'indexOneAuthor', 'Smith, John', $doc);
        $this->callMethod($indexer, 'indexOneAuthor', 'Doe, Jane', $doc);

        $fields = $doc->getFields();
        // Second addField() on same key converts to array
        self::assertIsArray($fields['author_fullname_fs']);
        self::assertContains('Smith, John', $fields['author_fullname_fs']);
        self::assertContains('Doe, Jane', $fields['author_fullname_fs']);
    }

    // -------------------------------------------------------------------------
    // indexAuthors (private — tested via Reflection + Paper mock)
    // -------------------------------------------------------------------------

    public function testIndexAuthorsIndexesAllAuthorsAndSetsSortField(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthors'])
            ->getMock();
        $paper->method('getAuthors')->willReturn([
            ['family' => 'Smith', 'given' => 'John'],
            ['family' => 'Doe',   'given' => 'Jane'],
        ]);

        $this->callMethod($indexer, 'indexAuthors', $paper, $doc);

        $fields = $doc->getFields();
        self::assertIsArray($fields['author_fullname_fs']);
        self::assertContains('Smith, John', $fields['author_fullname_fs']);
        self::assertContains('Doe, Jane', $fields['author_fullname_fs']);
        self::assertArrayHasKey('author_fullname_sort', $fields);
        self::assertIsString($fields['author_fullname_sort']);
    }

    public function testIndexAuthorsWithEmptyAuthorsDoesNothing(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthors'])
            ->getMock();
        $paper->method('getAuthors')->willReturn([]);

        $this->callMethod($indexer, 'indexAuthors', $paper, $doc);

        self::assertEmpty($doc->getFields());
    }

    public function testIndexAuthorsWithGetAuthorsThrowingSkipsGracefully(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthors', 'getDocid'])
            ->getMock();
        $paper->method('getAuthors')->willThrowException(new \InvalidArgumentException('bad data'));
        $paper->method('getDocid')->willReturn(0);

        $this->callMethod($indexer, 'indexAuthors', $paper, $doc);

        // No fields should have been added
        self::assertEmpty($doc->getFields());
    }

    // -------------------------------------------------------------------------
    // getFormattedDate (private — tested via Reflection)
    // -------------------------------------------------------------------------

    public function testGetFormattedDateWithValidDateReturnsIsoString(): void
    {
        $indexer = $this->makeIndexer();
        $result  = $this->callMethod($indexer, 'getFormattedDate', '2024-01-15');
        self::assertSame('2024-01-15T00:00:00Z', $result);
    }

    public function testGetFormattedDateWithInvalidDateReturnsFallback(): void
    {
        $indexer = $this->makeIndexer();
        $result  = $this->callMethod($indexer, 'getFormattedDate', 'not-a-valid-date-xyz');
        self::assertSame('1970-01-01T00:00:00Z', $result);
    }

    public function testGetFormattedDateWithCustomFormat(): void
    {
        $indexer = $this->makeIndexer();
        $result  = $this->callMethod($indexer, 'getFormattedDate', '2024-03-20', 'Y-m-d');
        self::assertSame('2024-03-20', $result);
    }

    // -------------------------------------------------------------------------
    // indexKeywords (private — tested via Reflection + Paper mock)
    // -------------------------------------------------------------------------

    public function testIndexKeywordsWithFlatArrayIndexesAllKeywords(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $paper->method('getMetadata')->willReturn(['physics', 'chemistry']);

        $this->callMethod($indexer, 'indexKeywords', $paper, $doc);

        $fields = $doc->getFields();
        self::assertIsArray($fields['keyword_t']);
        self::assertContains('physics', $fields['keyword_t']);
        self::assertContains('chemistry', $fields['keyword_t']);
    }

    public function testIndexKeywordsWithNestedArrayIndexesAllSubKeywords(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $paper->method('getMetadata')->willReturn([['quantum', 'field theory']]);

        $this->callMethod($indexer, 'indexKeywords', $paper, $doc);

        $fields = $doc->getFields();
        self::assertIsArray($fields['keyword_t']);
        self::assertContains('quantum', $fields['keyword_t']);
        self::assertContains('field theory', $fields['keyword_t']);
    }

    public function testIndexKeywordsWithNullSubjectsAddsNoKeywordField(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $paper->method('getMetadata')->willReturn(null);

        $this->callMethod($indexer, 'indexKeywords', $paper, $doc);

        self::assertArrayNotHasKey('keyword_t', $doc->getFields());
    }

    // -------------------------------------------------------------------------
    // indexTitles (private — tested via Reflection)
    // -------------------------------------------------------------------------

    public function testIndexTitlesWithValidLocaleUsesLocalizedField(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $result = $this->callMethod($indexer, 'indexTitles', ['en' => 'My Title'], $doc);

        self::assertInstanceOf(Document::class, $result);
        self::assertSame('My Title', $result->getFields()['en_paper_title_t']);
    }

    public function testIndexTitlesWithNonLocaleKeyFallsBackToPaperTitle(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $result = $this->callMethod($indexer, 'indexTitles', ['My Title'], $doc);

        self::assertInstanceOf(Document::class, $result);
        self::assertArrayHasKey('paper_title_t', $result->getFields());
    }

    public function testIndexTitlesWithEmptyArrayReturnsDocumentInstance(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $result = $this->callMethod($indexer, 'indexTitles', [], $doc);

        self::assertInstanceOf(Document::class, $result);
    }

    // -------------------------------------------------------------------------
    // indexAbstracts (private — tested via Reflection)
    // -------------------------------------------------------------------------

    public function testIndexAbstractsWithValidLocaleUsesLocalizedField(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $result = $this->callMethod($indexer, 'indexAbstracts', ['en' => 'This is an abstract.'], $doc);

        self::assertInstanceOf(Document::class, $result);
        self::assertSame('This is an abstract.', $result->getFields()['en_abstract_t']);
    }

    public function testIndexAbstractsWithNonLocaleKeyFallsBackToAbstractField(): void
    {
        $indexer = $this->makeIndexer();
        $doc     = new Document();

        $result = $this->callMethod($indexer, 'indexAbstracts', ['A plain abstract.'], $doc);

        self::assertInstanceOf(Document::class, $result);
        self::assertArrayHasKey('abstract_t', $result->getFields());
    }

    // -------------------------------------------------------------------------
    // getVolumeFromDbOrCache (private — tested via Reflection + setCache())
    // -------------------------------------------------------------------------

    public function testGetVolumeFromDbOrCacheReturnsCachedVolumeWithoutDbCall(): void
    {
        $indexer = $this->makeIndexer();
        // Disable serialization so assertSame() can compare object references directly.
        $cache = new ArrayAdapter(0, false);

        $volume = $this->getMockBuilder(Episciences_Volume::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cacheItem = $cache->getItem('volume.42');
        $cache->save($cacheItem->set($volume));

        $indexer->setCache($cache);

        $result = $this->callMethod($indexer, 'getVolumeFromDbOrCache', 42);

        self::assertSame($volume, $result);
    }

    public function testGetVolumeFromDbOrCacheReturnsFalseWhenNotFoundInDb(): void
    {
        $indexer = $this->makeIndexer();
        $indexer->setCache(new ArrayAdapter());

        $result = $this->callMethod($indexer, 'getVolumeFromDbOrCache', 999999);

        self::assertFalse($result);
    }
}