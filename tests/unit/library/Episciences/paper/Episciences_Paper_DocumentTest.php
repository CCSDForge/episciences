<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::setDocument() / getDocument()
 * and setOptions() type-field decoding.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_DocumentTest extends TestCase
{
    // -----------------------------------------------------------------------
    // getDocument default
    // -----------------------------------------------------------------------

    public function testGetDocumentReturnsNullByDefault(): void
    {
        $paper = new Episciences_Paper();
        self::assertNull($paper->getDocument());
    }

    // -----------------------------------------------------------------------
    // setDocument with valid JSON
    // -----------------------------------------------------------------------

    public function testSetDocumentWithValidJsonDecodesToArray(): void
    {
        $paper = new Episciences_Paper();
        $paper->setDocument('{"title":"hello"}');
        self::assertSame(['title' => 'hello'], $paper->getDocument());
    }

    public function testSetDocumentWithNestedJsonKeepsStructure(): void
    {
        $paper = new Episciences_Paper();
        $paper->setDocument('{"meta":{"lang":"fr","year":2024}}');
        $doc = $paper->getDocument();
        self::assertIsArray($doc);
        self::assertSame('fr', $doc['meta']['lang']);
        self::assertSame(2024, $doc['meta']['year']);
    }

    public function testSetDocumentOverwritesPreviousValue(): void
    {
        $paper = new Episciences_Paper();
        $paper->setDocument('{"version":1}');
        $paper->setDocument('{"version":2}');
        self::assertSame(['version' => 2], $paper->getDocument());
    }

    // -----------------------------------------------------------------------
    // setDocument with null
    // -----------------------------------------------------------------------

    public function testSetDocumentWithNullStoresNull(): void
    {
        $paper = new Episciences_Paper();
        $paper->setDocument('{"tmp":true}');
        $paper->setDocument(null);
        self::assertNull($paper->getDocument());
    }

    // -----------------------------------------------------------------------
    // Fix 2 regression: invalid JSON must yield null, not the broken string
    // -----------------------------------------------------------------------

    public function testSetDocumentWithInvalidJsonStoresNull(): void
    {
        $paper = new Episciences_Paper();

        // setDocument() calls trigger_error() on JSON parse failure.
        // PHPUnit converts E_USER_NOTICE to an exception by default, so we
        // suppress it with a custom handler for the duration of the call.
        // The assertion verifies Fix 2: _document must be null, not the raw string.
        set_error_handler(static function (): bool { return true; });
        try {
            $paper->setDocument('{not valid json');
        } finally {
            restore_error_handler();
        }

        self::assertNull($paper->getDocument());
    }

    public function testSetDocumentWithMalformedUnicodeJsonStoresNull(): void
    {
        $paper = new Episciences_Paper();

        set_error_handler(static function (): bool { return true; });
        try {
            $paper->setDocument('[unclosed array');
        } finally {
            restore_error_handler();
        }

        self::assertNull($paper->getDocument());
    }

    // -----------------------------------------------------------------------
    // setOptions type-field decoding
    // -----------------------------------------------------------------------

    public function testSetOptionsWithJsonStringTypeDecodesIt(): void
    {
        $typeJson = json_encode([
            Episciences_Paper::TITLE_TYPE => 'article',
            Episciences_Paper::TYPE_TYPE  => 'journal',
        ]);
        $paper = new Episciences_Paper(['type' => $typeJson]);
        self::assertSame('article', $paper->getType()[Episciences_Paper::TITLE_TYPE]);
    }

    public function testSetOptionsTypeJsonCanContainSubtype(): void
    {
        $typeJson = json_encode([
            Episciences_Paper::TITLE_TYPE   => 'dataset',
            Episciences_Paper::TYPE_SUBTYPE => 'experimental',
        ]);
        $paper = new Episciences_Paper(['type' => $typeJson]);
        self::assertSame('dataset', $paper->getType()[Episciences_Paper::TITLE_TYPE]);
        self::assertSame('experimental', $paper->getType()[Episciences_Paper::TYPE_SUBTYPE]);
    }

    public function testSetOptionsWithNullTypeUsesDefault(): void
    {
        $paper = new Episciences_Paper(['type' => null]);
        $type = $paper->getType();
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $type[Episciences_Paper::TITLE_TYPE]);
    }
}
