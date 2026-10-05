<?php

namespace unit\scripts\Messenger;

use Episciences\Next\Messenger\NextRevalidationTransport;
use Episciences\Solr\Indexing\Messenger\SolrIndexTransport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TransportProfileInterface;
use TransportProfileRegistry;

require_once __DIR__ . '/../../../../scripts/Messenger/TransportProfileRegistry.php';

class TransportProfileRegistryTest extends TestCase
{
    public function testNamesIncludesBothKnownTransports(): void
    {
        self::assertSame(['solr_index', 'next_revalidation'], TransportProfileRegistry::names());
    }

    public function testRegistryKeysStayInSyncWithTheTransportDescriptorConstants(): void
    {
        // The registry uses literal string keys (not SolrIndexTransport::NAME
        // / NextRevalidationTransport::NAME) so names()/get() work before any
        // profile's bootstrap() has registered the Zend fallback autoloader —
        // this guards against the two ever drifting apart.
        self::assertSame(SolrIndexTransport::NAME, TransportProfileRegistry::get('solr_index')->config()->name);
        self::assertSame(NextRevalidationTransport::NAME, TransportProfileRegistry::get('next_revalidation')->config()->name);
    }

    public function testGetReturnsAProfileForEachKnownTransport(): void
    {
        foreach (TransportProfileRegistry::names() as $name) {
            self::assertInstanceOf(TransportProfileInterface::class, TransportProfileRegistry::get($name));
        }
    }

    public function testGetThrowsOnUnknownTransport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown transport "not_a_real_transport"/');

        TransportProfileRegistry::get('not_a_real_transport');
    }

    public function testGetReturnsANewInstanceEachTime(): void
    {
        // The registry deliberately does no bootstrapping and holds no
        // shared state, so --transport validation can run before any
        // bootstrap() call — a fresh profile instance per get() call keeps
        // that true.
        self::assertNotSame(
            TransportProfileRegistry::get('solr_index'),
            TransportProfileRegistry::get('solr_index')
        );
    }
}
