<?php

namespace unit\library\Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Build\KeywordFieldsBuilder;
use Episciences\Solr\Indexing\Model\SolrDocument;
use PHPUnit\Framework\TestCase;

class KeywordFieldsBuilderTest extends TestCase
{
    public function testBuildIgnoresNonArraySubjects(): void
    {
        $document = SolrDocument::empty();

        self::assertSame([], (new KeywordFieldsBuilder())->build($document, null)->toArray());
    }

    public function testBuildFansOutFlatKeywordList(): void
    {
        $document = (new KeywordFieldsBuilder())->build(SolrDocument::empty(), ['physics', 'chemistry']);

        self::assertSame(['physics', 'chemistry'], $document->toArray()['keyword_t']);
    }

    public function testBuildFansOutGroupedKeywordLists(): void
    {
        $document = (new KeywordFieldsBuilder())->build(SolrDocument::empty(), [
            ['physics', 'quantum'],
            'chemistry',
        ]);

        self::assertSame(['physics', 'quantum', 'chemistry'], $document->toArray()['keyword_t']);
    }
}
