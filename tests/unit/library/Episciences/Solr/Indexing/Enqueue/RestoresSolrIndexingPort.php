<?php

namespace unit\library\Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use ReflectionProperty;

/**
 * Captures SolrIndexing's private static $port before a test replaces it with
 * a spy, and restores that exact prior value (possibly null, if nothing had
 * configured it yet) afterwards — instead of overwriting it with a hardcoded
 * fallback spy, which leaks fake queue state into whichever test runs next in
 * the same process.
 */
trait RestoresSolrIndexingPort
{
    private ?SolrIndexQueuePort $originalSolrIndexingPort = null;

    private function captureSolrIndexingPort(): void
    {
        $this->originalSolrIndexingPort = self::readSolrIndexingPort();
    }

    private function restoreSolrIndexingPort(): void
    {
        self::writeSolrIndexingPort($this->originalSolrIndexingPort);
    }

    private static function readSolrIndexingPort(): ?SolrIndexQueuePort
    {
        $property = new ReflectionProperty(SolrIndexing::class, 'port');
        $property->setAccessible(true);

        /** @var SolrIndexQueuePort|null */
        return $property->getValue();
    }

    private static function writeSolrIndexingPort(?SolrIndexQueuePort $port): void
    {
        $property = new ReflectionProperty(SolrIndexing::class, 'port');
        $property->setAccessible(true);
        $property->setValue(null, $port);
    }
}
