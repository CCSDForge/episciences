<?php

namespace unit\library\Episciences\Messenger\Enqueue;

use ReflectionProperty;

/**
 * Captures a static facade's private static $port (e.g.
 * Episciences\Solr\Indexing\Enqueue\SolrIndexing,
 * Episciences\Next\RevalidationService) before a test replaces it with a
 * spy, and restores that exact prior value (possibly null, if nothing had
 * configured it yet) afterwards — instead of overwriting it with a
 * hardcoded fallback spy, which would leak fake queue state into whichever
 * test runs next in the same process.
 *
 * Generalized from the Solr-only RestoresSolrIndexingPort so
 * Episciences\Next\RevalidationService's tests can reuse it: every facade
 * following this convention stores its port in a property literally named
 * "port", so the facade class name is the only thing that varies.
 */
trait RestoresStaticQueuePort
{
    private mixed $originalStaticQueuePort = null;

    private function captureStaticQueuePort(string $facadeClass): void
    {
        $this->originalStaticQueuePort = self::readStaticQueuePort($facadeClass);
    }

    private function restoreStaticQueuePort(string $facadeClass): void
    {
        self::writeStaticQueuePort($facadeClass, $this->originalStaticQueuePort);
    }

    private static function readStaticQueuePort(string $facadeClass): mixed
    {
        $property = new ReflectionProperty($facadeClass, 'port');
        $property->setAccessible(true);

        return $property->getValue();
    }

    private static function writeStaticQueuePort(string $facadeClass, mixed $port): void
    {
        $property = new ReflectionProperty($facadeClass, 'port');
        $property->setAccessible(true);
        $property->setValue(null, $port);
    }
}
