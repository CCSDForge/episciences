<?php

namespace unit\library\Episciences\Solr\Indexing\Messenger\Message;

use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeletePaperMessageTest extends TestCase
{
    public function testThrowsWhenNeitherDocIdNorQueryIsProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeletePaperMessage();
    }

    public function testToSolrDeleteQueryBuildsDocidQueryFromDocId(): void
    {
        $message = new DeletePaperMessage(docId: 42);

        self::assertSame('docid:42', $message->toSolrDeleteQuery());
    }

    public function testToSolrDeleteQueryPrefersExplicitQuery(): void
    {
        $message = new DeletePaperMessage(solrQuery: 'revue_id_i:7');

        self::assertSame('revue_id_i:7', $message->toSolrDeleteQuery());
    }

    public function testThrowsWhenQueryIsBlankAndNoDocIdIsProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeletePaperMessage(solrQuery: '   ');
    }

    public function testBlankQueryFallsBackToDocIdInsteadOfWinningSilently(): void
    {
        $message = new DeletePaperMessage(docId: 42, solrQuery: '   ');

        self::assertNull($message->solrQuery);
        self::assertSame('docid:42', $message->toSolrDeleteQuery());
    }
}
