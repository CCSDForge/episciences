<?php

namespace unit\library\Episciences\Solr\Indexing\Model;

use Episciences\Solr\Indexing\Model\SolrDocument;
use PHPUnit\Framework\TestCase;
use Solarium\QueryType\Update\Query\Document;

/**
 * Unit tests for SolrDocument's field-filtering semantics: empty/null/empty-
 * array values are skipped, non-empty values are kept, per the field-suffix
 * rules documented on FieldSuffix.
 */
class SolrDocumentTest extends TestCase
{
    public function testFilteredFieldSkipsEmptyString(): void
    {
        $doc = SolrDocument::empty()->withFilteredField('doi_s', '');

        self::assertSame([], $doc->toArray());
    }

    public function testFilteredFieldSkipsNull(): void
    {
        $doc = SolrDocument::empty()->withFilteredField('doi_s', null);

        self::assertSame([], $doc->toArray());
    }

    public function testFilteredFieldSkipsZeroDate(): void
    {
        $doc = SolrDocument::empty()->withFilteredField('publication_date_tdate', '0000-00-00');

        self::assertSame([], $doc->toArray());
    }

    public function testFilteredFieldSkipsFacetSeparator(): void
    {
        $doc = SolrDocument::empty()->withFilteredField('revue_title_s', '_FacetSep_');

        self::assertSame([], $doc->toArray());
    }

    public function testFilteredFieldTrimsAndStripsControlChars(): void
    {
        $doc = SolrDocument::empty()->withFilteredField('revue_title_s', "  Some Title\x01  ");

        self::assertSame(['revue_title_s' => ['Some Title']], $doc->toArray());
    }

    public function testFilteredFieldAcceptsBoolFromXpathHelper(): void
    {
        // Ccsd_Tools::xpath() returns false when no match is found — legacy
        // would pass this straight through to addField(), not skip it.
        $doc = SolrDocument::empty()->withFilteredField('language_s', false);

        self::assertSame(['language_s' => [false]], $doc->toArray());
    }

    public function testFilteredFieldKeepsIntAndFloat(): void
    {
        $doc = SolrDocument::empty()
            ->withFilteredField('docid', 42)
            ->withFilteredField('version_td', 1.5);

        self::assertSame(['docid' => [42], 'version_td' => [1.5]], $doc->toArray());
    }

    public function testRawFieldAppendsWithoutSanitizationOrDedup(): void
    {
        $doc = SolrDocument::empty()
            ->withRawField('author_fullname_s', 'Smith, John')
            ->withRawField('author_fullname_s', 'Smith, John');

        self::assertSame(['author_fullname_s' => ['Smith, John', 'Smith, John']], $doc->toArray());
    }

    public function testFilteredMapDeduplicatesArrayValues(): void
    {
        $doc = SolrDocument::empty()->withFilteredMap([
            'keyword_t' => ['physics', 'physics', 'chemistry'],
        ]);

        self::assertSame(['keyword_t' => ['physics', 'chemistry']], $doc->toArray());
    }

    public function testFilteredMapSkipsEmptyEntriesWithinArrayValue(): void
    {
        $doc = SolrDocument::empty()->withFilteredMap([
            'keyword_t' => ['physics', '', null],
        ]);

        self::assertSame(['keyword_t' => ['physics']], $doc->toArray());
    }

    public function testToSolariumDocumentFansOutMultivaluedFields(): void
    {
        $doc = SolrDocument::empty()
            ->withRawField('author_fullname_s', 'Smith, John')
            ->withRawField('author_fullname_s', 'Doe, Jane')
            ->withFilteredField('docid', 42);

        $solariumDocument = $doc->toSolariumDocument(new Document());

        self::assertSame(['Smith, John', 'Doe, Jane'], $solariumDocument->getFields()['author_fullname_s']);
        self::assertSame(42, $solariumDocument->getFields()['docid']);
    }

    public function testEmptyIsTrulyEmpty(): void
    {
        self::assertSame([], SolrDocument::empty()->toArray());
    }
}
