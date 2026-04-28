<?php

namespace unit\library\Episciences;

use DateTime;
use Episciences_Paper_Citations;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_CitationsTest extends TestCase
{
    // ────────────────────────────────────────────
    // toArray()
    // ────────────────────────────────────────────

    public function testToArrayHasCorrectCitationKey(): void
    {
        $entity = new Episciences_Paper_Citations();
        $entity->setId(1)
               ->setDocId(10)
               ->setSourceId(2)
               ->setCitation('{"test":1}');

        $array = $entity->toArray();

        self::assertArrayHasKey('citation', $array, 'toArray() must use key "citation", not "licence"');
        self::assertArrayNotHasKey('licence', $array);
        self::assertSame('{"test":1}', $array['citation']);
    }

    // ────────────────────────────────────────────
    // $_updatedAt type safety
    // ────────────────────────────────────────────

    public function testGetUpdatedAtReturnsNullWhenNotSet(): void
    {
        $entity = new Episciences_Paper_Citations();

        // Must not throw TypeError (previous bug: property initialized to string 'CURRENT_TIMESTAMP')
        self::assertNull($entity->getUpdatedAt());
    }

    public function testGetUpdatedAtReturnsDateTimeAfterSet(): void
    {
        $entity = new Episciences_Paper_Citations();
        $entity->setUpdatedAt('2024-06-15 10:00:00');

        $result = $entity->getUpdatedAt();

        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    // ────────────────────────────────────────────
    // Setters / getters round-trip
    // ────────────────────────────────────────────

    public function testSettersReturnFluentInterface(): void
    {
        $entity = new Episciences_Paper_Citations();

        self::assertSame($entity, $entity->setId(99));
        self::assertSame($entity, $entity->setCitation('{}'));
        self::assertSame($entity, $entity->setDocId(42));
        self::assertSame($entity, $entity->setSourceId(1));
        self::assertSame($entity, $entity->setUpdatedAt('2024-01-01'));
    }

    public function testSetCitationAndGetCitation(): void
    {
        $entity = new Episciences_Paper_Citations();
        $json = '{"author":"Doe, J","year":2024}';

        $entity->setCitation($json);

        self::assertSame($json, $entity->getCitation());
    }

    public function testSetDocIdAndGetDocId(): void
    {
        $entity = new Episciences_Paper_Citations();
        $entity->setDocId(1234);

        self::assertSame(1234, $entity->getDocId());
    }

    public function testSetSourceIdAndGetSourceId(): void
    {
        $entity = new Episciences_Paper_Citations();
        $entity->setSourceId(7);

        self::assertSame(7, $entity->getSourceId());
    }

    // ────────────────────────────────────────────
    // Constructor with options array
    // ────────────────────────────────────────────

    public function testConstructorWithOptionsInitializesProperties(): void
    {
        // 'source_id' maps to setSourceId via convertToCamelCase ('source_id' → 'SourceId' → setSourceId)
        // 'citation' maps to setCitation ('citation' → 'Citation' → setCitation)
        $entity = new Episciences_Paper_Citations([
            'source_id' => 3,
            'citation' => '{"foo":"bar"}',
        ]);

        self::assertSame(3, $entity->getSourceId());
        self::assertSame('{"foo":"bar"}', $entity->getCitation());
    }

    public function testConstructorWithNullOptionsLeavesUpdatedAtNull(): void
    {
        // Only test the fixed nullable property — the others are non-nullable typed
        // without defaults, so their getters must not be called without prior initialization
        $entity = new Episciences_Paper_Citations();

        self::assertNull($entity->getUpdatedAt());
    }
}
