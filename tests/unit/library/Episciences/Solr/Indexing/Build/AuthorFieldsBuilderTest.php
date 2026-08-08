<?php

namespace unit\library\Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Build\AuthorFieldsBuilder;
use Episciences\Solr\Indexing\Model\SolrDocument;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthorFieldsBuilder's author-name formatting/sorting/
 * classification logic, exercised here via constructor injection.
 */
class AuthorFieldsBuilderTest extends TestCase
{
    public function testFormatAuthorNameJoinsFamilyAndGiven(): void
    {
        self::assertSame('Smith, John', AuthorFieldsBuilder::formatAuthorName(['family' => 'Smith', 'given' => 'John']));
    }

    public function testFormatAuthorNameFallsBackToFamilyOnly(): void
    {
        self::assertSame('Smith', AuthorFieldsBuilder::formatAuthorName(['family' => 'Smith']));
    }

    public function testFormatAuthorNameFallsBackToGivenOnly(): void
    {
        self::assertSame('John', AuthorFieldsBuilder::formatAuthorName(['given' => 'John']));
    }

    public function testClassifyAuthorFirstLetterUppercasesAsciiLetter(): void
    {
        self::assertSame('S', AuthorFieldsBuilder::classifyAuthorFirstLetter('smith, john'));
    }

    public function testClassifyAuthorFirstLetterFallsBackToOthersForNonAscii(): void
    {
        self::assertSame('Others', AuthorFieldsBuilder::classifyAuthorFirstLetter('123Author, Name'));
    }

    public function testCleanAuthorNameStripsControlCharsAndTrailingComma(): void
    {
        self::assertSame('Dupont, Jean', AuthorFieldsBuilder::cleanAuthorName("Dupont,\x01 Jean,"));
    }

    public function testBuildAuthorSortKeyTruncatesAndStripsSpacesAndCommas(): void
    {
        $key = AuthorFieldsBuilder::buildAuthorSortKey(['Smith, John', 'Doe, Jane']);

        self::assertLessThanOrEqual(30, mb_strlen($key));
        self::assertStringNotContainsString(' ', $key);
        self::assertStringNotContainsString(',', $key);
    }

    public function testBuildWritesFourFieldsPerAuthorPlusSortKey(): void
    {
        $document = (new AuthorFieldsBuilder())->build(SolrDocument::empty(), [
            ['family' => 'Smith', 'given' => 'John'],
        ]);

        $fields = $document->toArray();

        self::assertSame(['Smith, John'], $fields['author_fullname_fs']);
        self::assertSame(['S'], $fields['authorFirstLetters_s']);
        self::assertSame(['Smith, John'], $fields['authorLastNameFirstNamePrefixed_fs']);
        self::assertSame(['Smith, John'], $fields['author_fullname_s']);
        self::assertArrayHasKey('author_fullname_sort', $fields);
    }

    public function testBuildPrefixesOthersBucketWithFacetSeparator(): void
    {
        $document = (new AuthorFieldsBuilder())->build(SolrDocument::empty(), [
            ['family' => '123Author', 'given' => 'Name'],
        ]);

        $fields = $document->toArray();

        self::assertSame(['Others'], $fields['authorFirstLetters_s']);
        self::assertSame(['Others_FacetSep_123Author, Name'], $fields['authorLastNameFirstNamePrefixed_fs']);
    }

    public function testBuildReturnsDocumentUnchangedForNoAuthors(): void
    {
        $document = SolrDocument::empty();

        self::assertSame($document->toArray(), (new AuthorFieldsBuilder())->build($document, [])->toArray());
    }

    public function testBuildHandlesMultipleAuthorsInOrder(): void
    {
        $document = (new AuthorFieldsBuilder())->build(SolrDocument::empty(), [
            ['family' => 'Smith', 'given' => 'John'],
            ['family' => 'Doe', 'given' => 'Jane'],
        ]);

        self::assertSame(
            ['Smith, John', 'Doe, Jane'],
            $document->toArray()['author_fullname_s']
        );
    }
}
