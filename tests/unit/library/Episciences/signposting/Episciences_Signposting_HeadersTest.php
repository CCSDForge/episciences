<?php

namespace unit\library\Episciences\signposting;

use Episciences\Signposting\Headers;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class to exercise the Headers trait in isolation.
 */
class ConcreteHeaders
{
    use Headers;
}

class Episciences_Signposting_HeadersTest extends TestCase
{
    // The DOI_ORG_PREFIX constant value replicated here so tests remain readable.
    private const DOI_PREFIX = 'https://doi.org/';

    private const PAPER_URL = 'https://journal.example.org/papers/42';
    private const PAPER_DOI = '10.1234/test.42';

    // -------------------------------------------------------------------------
    // Normal cases
    // -------------------------------------------------------------------------

    public function testWithDoiProducesCiteAsLink(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);

        self::assertContains(
            '<' . self::DOI_PREFIX . self::PAPER_DOI . '>; rel="cite-as"',
            $links
        );
    }

    public function testWithoutDoiNoCiteAsLink(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(false, self::PAPER_URL, self::PAPER_DOI);

        foreach ($links as $link) {
            self::assertStringNotContainsString('cite-as', $link, 'No cite-as link expected when paperHasDoi=false');
        }
    }

    public function testAlwaysContainsTypeScholarlyArticle(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);

        self::assertContains('<https://schema.org/ScholarlyArticle>; rel="type"', $links);
    }

    public function testContainsSixDescribedByLinks(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);

        $describedBy = array_filter($links, static fn(string $l) => str_contains($l, 'rel="describedby"'));
        self::assertCount(6, $describedBy, 'Expected exactly 6 describedby links');
    }

    public function testDescribedByLinksWithFormats(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);

        // tei, dc, openaire, crossref must include a formats= attribute
        $typesWithFormats = ['tei', 'dc', 'openaire', 'crossref'];
        foreach ($typesWithFormats as $type) {
            $found = array_filter($links, static fn(string $l) => str_contains($l, '/' . $type . '>') && str_contains($l, 'formats='));
            self::assertNotEmpty($found, "Expected a describedby link with formats= for type '$type'");
        }
    }

    public function testDescribedByLinksWithoutFormats(): void
    {
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);

        // pdf and bibtex must NOT include a formats= attribute
        $typesWithoutFormats = ['pdf', 'bibtex'];
        foreach ($typesWithoutFormats as $type) {
            $matching = array_filter($links, static fn(string $l) => str_contains($l, '/' . $type . '>'));
            foreach ($matching as $link) {
                self::assertStringNotContainsString('formats=', $link, "Link for '$type' should not have formats=");
            }
        }
    }

    public function testExistingHeaderLinksArePreserved(): void
    {
        $existing = ['<https://example.org>; rel="custom"'];
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI, $existing);

        self::assertContains('<https://example.org>; rel="custom"', $links);
        // Existing link should appear first
        self::assertSame('<https://example.org>; rel="custom"', $links[0]);
    }

    public function testReturnsTotalLinkCount(): void
    {
        // With DOI: 1 cite-as + 1 type + 6 describedby = 8
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, self::PAPER_DOI);
        self::assertCount(8, $links);

        // Without DOI: 0 cite-as + 1 type + 6 describedby = 7
        $linksNoDoi = ConcreteHeaders::getPaperHeaderLinks(false, self::PAPER_URL, self::PAPER_DOI);
        self::assertCount(7, $linksNoDoi);
    }

    // -------------------------------------------------------------------------
    // Bug regression: empty DOI with $paperHasDoi = true
    // -------------------------------------------------------------------------

    public function testEmptyDoiWithHasDoiTrueProducesNoCiteAsLink(): void
    {
        // Bug: previously produced <https://doi.org/>; rel="cite-as" (invalid)
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, '');

        foreach ($links as $link) {
            self::assertStringNotContainsString('cite-as', $link, 'Empty DOI must not produce a cite-as link');
        }
    }

    // -------------------------------------------------------------------------
    // Security: HTTP header injection via CRLF
    // -------------------------------------------------------------------------

    public function testCrlfInUrlIsStripped(): void
    {
        // CRLF injection would allow an attacker to inject extra HTTP headers.
        // After sanitization the line-break chars must be gone (the remaining text is harmless within the header value).
        $maliciousUrl = "https://journal.example.org/papers/42\r\nX-Injected: evil";
        $links = ConcreteHeaders::getPaperHeaderLinks(true, $maliciousUrl, self::PAPER_DOI);

        foreach ($links as $link) {
            self::assertStringNotContainsString("\r", $link, 'CR character must be stripped from URL');
            self::assertStringNotContainsString("\n", $link, 'LF character must be stripped from URL');
        }
    }

    public function testCrlfInDoiIsStripped(): void
    {
        // Same as above but for the DOI value used in the cite-as header.
        $maliciousDoi = "10.1234/test\r\nX-Injected: evil";
        $links = ConcreteHeaders::getPaperHeaderLinks(true, self::PAPER_URL, $maliciousDoi);

        foreach ($links as $link) {
            self::assertStringNotContainsString("\r", $link, 'CR character must be stripped from DOI');
            self::assertStringNotContainsString("\n", $link, 'LF character must be stripped from DOI');
        }
    }

    public function testLfOnlyInUrlIsStripped(): void
    {
        $maliciousUrl = "https://journal.example.org/papers/42\nX-Injected: evil";
        $links = ConcreteHeaders::getPaperHeaderLinks(false, $maliciousUrl, '');

        foreach ($links as $link) {
            self::assertStringNotContainsString("\n", $link);
        }
    }
}
