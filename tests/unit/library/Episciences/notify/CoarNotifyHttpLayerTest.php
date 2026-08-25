<?php

namespace unit\library\Episciences\notify;

use Episciences\Notify\CoarNotifyHttpLayer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CoarNotifyHttpLayerTest extends TestCase
{
    private CoarNotifyHttpLayer $layer;

    protected function setUp(): void
    {
        $this->layer = new CoarNotifyHttpLayer();
    }

    public function testBuildHeaderLinesFromAssociativeArrayProducesNameColonValue(): void
    {
        $lines = $this->layer->buildHeaderLines([
            'Content-Type' => 'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
        ]);

        self::assertContains(
            'Content-Type: application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
            $lines
        );
    }

    public function testBuildHeaderLinesDoesNotDuplicateContentTypeWhenCallerProvidesOne(): void
    {
        $lines = $this->layer->buildHeaderLines([
            'Content-Type' => 'application/ld+json',
        ]);

        $contentTypeLines = array_filter($lines, static fn(string $line): bool => str_starts_with(strtolower($line), 'content-type:'));

        self::assertCount(1, $contentTypeLines);
        self::assertSame(['Content-Type: application/ld+json'], array_values($contentTypeLines));
    }

    public function testBuildHeaderLinesDefaultsToApplicationJsonWhenNoContentTypeGiven(): void
    {
        $lines = $this->layer->buildHeaderLines(['X-Custom' => 'value']);

        self::assertContains('Content-Type: application/json', $lines);
        self::assertContains('X-Custom: value', $lines);
    }

    public function testBuildHeaderLinesHandlesNullHeaders(): void
    {
        $lines = $this->layer->buildHeaderLines(null);

        self::assertSame(['Content-Type: application/json'], $lines);
    }

    public function testBuildHeaderLinesPassesThroughAlreadyFormattedLines(): void
    {
        $lines = $this->layer->buildHeaderLines(['Authorization: Bearer token']);

        self::assertContains('Authorization: Bearer token', $lines);
        self::assertContains('Content-Type: application/json', $lines);
    }

    /**
     * HTTP/2 responses are lower-cased by cURL (e.g. HAL's inbox); getHeader("Location")
     * must still resolve regardless of the case actually sent on the wire.
     */
    public function testParseHeadersNormalizesCaseSoLocationHeaderIsFound(): void
    {
        $method = new ReflectionMethod(CoarNotifyHttpLayer::class, 'parseHeaders');
        $method->setAccessible(true);

        $parsed = $method->invoke($this->layer, "HTTP/2 201\r\nlocation: https://hal.example/inbox/123\r\ncontent-type: application/ld+json\r\n\r\n");

        self::assertSame('https://hal.example/inbox/123', $parsed['Location'] ?? null);
        self::assertSame('application/ld+json', $parsed['Content-Type'] ?? null);
    }
}