<?php

namespace unit\library\Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Build\LocaleFieldsBuilder;
use Episciences\Solr\Indexing\Model\SolrDocument;
use PHPUnit\Framework\TestCase;

class LocaleFieldsBuilderTest extends TestCase
{
    public function testWithTitlesFansOutByLocale(): void
    {
        $document = (new LocaleFieldsBuilder())->withTitles(SolrDocument::empty(), ['en' => 'My Title']);

        self::assertSame(['My Title'], $document->toArray()['en_paper_title_t']);
    }

    public function testWithTitlesFallsBackToNonLocalizedFieldWhenNoValidLocale(): void
    {
        $document = (new LocaleFieldsBuilder())->withTitles(SolrDocument::empty(), ['My Title']);

        self::assertArrayNotHasKey('0_paper_title_t', $document->toArray());
        self::assertSame(['My Title'], $document->toArray()['paper_title_t']);
    }

    public function testWithTitlesReturnsUnchangedDocumentForEmptyArray(): void
    {
        $document = (new LocaleFieldsBuilder())->withTitles(SolrDocument::empty(), []);

        self::assertSame([], $document->toArray());
    }

    public function testWithAbstractsFansOutByLocale(): void
    {
        $document = (new LocaleFieldsBuilder())->withAbstracts(SolrDocument::empty(), ['fr' => 'Un résumé']);

        self::assertSame(['Un résumé'], $document->toArray()['fr_abstract_t']);
    }
}
