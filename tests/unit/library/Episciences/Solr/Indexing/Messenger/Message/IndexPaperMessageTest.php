<?php

namespace unit\library\Episciences\Solr\Indexing\Messenger\Message;

use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use PHPUnit\Framework\TestCase;

class IndexPaperMessageTest extends TestCase
{
    public function testDefaultsPriorityToZero(): void
    {
        $message = new IndexPaperMessage(42);

        self::assertSame(42, $message->docId);
        self::assertSame(0, $message->priority);
    }

    public function testCarriesExplicitPriority(): void
    {
        $message = new IndexPaperMessage(42, 10);

        self::assertSame(10, $message->priority);
    }
}
