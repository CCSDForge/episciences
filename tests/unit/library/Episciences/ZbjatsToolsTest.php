<?php

namespace unit\library\Episciences;

use Episciences_ZbjatsTools;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_ZbjatsTools.
 * Focuses on bug regressions from the refactoring plan.
 */
class ZbjatsToolsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // cslToJats() — missing-key regressions
    // -------------------------------------------------------------------------

    /**
     * Missing ISSN key must not throw TypeError.
     */
    public function testCslToJats_MissingIssnKey_NoTypeError(): void
    {
        $csl = json_encode([
            'type'  => 'journal-article',
            'title' => 'Some article',
            // 'ISSN' intentionally absent
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('issn', $result);
    }

    /**
     * Missing 'published' key must not throw TypeError.
     */
    public function testCslToJats_MissingPublishedKey_NoTypeError(): void
    {
        $csl = json_encode([
            'type'  => 'journal-article',
            'title' => 'Some article',
            // 'published' intentionally absent
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('year', $result);
    }

    /**
     * ISSN array present but empty must not throw TypeError.
     */
    public function testCslToJats_EmptyIssnArray_NoTypeError(): void
    {
        $csl = json_encode([
            'type' => 'journal-article',
            'ISSN' => [], // empty array
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('issn', $result);
    }

    /**
     * Published entry with missing date-parts must not throw TypeError.
     */
    public function testCslToJats_PublishedWithEmptyDateParts_NoTypeError(): void
    {
        $csl = json_encode([
            'type'      => 'journal-article',
            'published' => ['date-parts' => [[]]],  // inner array has no year
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('year', $result);
    }

    /**
     * Successful path: ISSN and year extracted correctly.
     */
    public function testCslToJats_WithIssnAndPublished_ExtractsBoth(): void
    {
        $csl = json_encode([
            'type'      => 'journal-article',
            'ISSN'      => ['1234-5678', '8765-4321'],
            'published' => ['date-parts' => [[2023, 5, 15]]],
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertEquals('1234-5678', $result['issn']);
        $this->assertEquals(2023, $result['year']);
    }

    // -------------------------------------------------------------------------
    // cslToJats() — general behaviour
    // -------------------------------------------------------------------------

    public function testCslToJats_JournalArticle_ExtractsTitle(): void
    {
        $csl = json_encode([
            'type'  => 'journal-article',
            'title' => 'My Paper Title',
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertEquals('My Paper Title', $result['article-title']);
    }

    public function testCslToJats_NonJournalType_ExtractsSource(): void
    {
        $csl = json_encode([
            'type'  => 'book',
            'title' => 'My Book',
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertEquals('My Book', $result['source']);
    }

    public function testCslToJats_WithDoi_ExtractsDoi(): void
    {
        $csl = json_encode([
            'type' => 'journal-article',
            'DOI'  => '10.1234/test',
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertEquals('10.1234/test', $result['doi']);
    }

    public function testCslToJats_WithAuthors_ExtractsAuthors(): void
    {
        $csl = json_encode([
            'type'   => 'journal-article',
            'author' => [
                ['family' => 'Smith', 'given' => 'John'],
                ['family' => 'Doe'],
            ],
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertArrayHasKey('authors', $result);
        $this->assertEquals('Smith', $result['authors'][0]['surname']);
        $this->assertEquals('John', $result['authors'][0]['given-names']);
        $this->assertEquals('Doe', $result['authors'][1]['surname']);
    }

    public function testCslToJats_WithPages_ExtractsFpageAndLpage(): void
    {
        $csl = json_encode([
            'type' => 'journal-article',
            'page' => '100-120',
        ]);

        $result = Episciences_ZbjatsTools::cslToJats($csl);
        $this->assertEquals('100', $result['fpage']);
        $this->assertEquals('120', $result['lpage']);
    }
}
