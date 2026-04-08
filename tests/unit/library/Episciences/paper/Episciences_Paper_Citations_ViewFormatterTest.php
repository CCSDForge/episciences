<?php

namespace unit\library\Episciences;

use Episciences_Paper_Citations_ViewFormatter;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_Citations_ViewFormatterTest extends TestCase
{
    // ────────────────────────────────────────────
    // sortAuthorAndYear
    // ────────────────────────────────────────────

    public function testSortByYearAndAuthorDescYear(): void
    {
        $citations = [
            ['author' => 'Zebra, Anne', 'year' => 2020],
            ['author' => 'Alpha, Bob', 'year' => 2023],
        ];

        $result = Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear($citations);

        self::assertSame(2023, $result[0]['year']);
        self::assertSame(2020, $result[1]['year']);
    }

    public function testSortByYearAndAuthorAscAuthorWhenSameYear(): void
    {
        $citations = [
            ['author' => 'Zebra, Anne', 'year' => 2022],
            ['author' => 'Alpha, Bob', 'year' => 2022],
        ];

        $result = Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear($citations);

        self::assertSame('Alpha, Bob', $result[0]['author']);
        self::assertSame('Zebra, Anne', $result[1]['author']);
    }

    public function testSortStabilityCompound(): void
    {
        $citations = [
            ['author' => 'Omega, Z', 'year' => 2021],
            ['author' => 'Alpha, A', 'year' => 2023],
            ['author' => 'Beta, B',  'year' => 2021],
        ];

        $result = Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear($citations);

        // Year 2023 first
        self::assertSame(2023, $result[0]['year']);
        self::assertSame('Alpha, A', $result[0]['author']);
        // Year 2021: Alpha before Omega alphabetically
        self::assertSame(2021, $result[1]['year']);
        self::assertSame('Beta, B', $result[1]['author']);
        self::assertSame(2021, $result[2]['year']);
        self::assertSame('Omega, Z', $result[2]['author']);
    }

    public function testSortEmptyArrayReturnsEmpty(): void
    {
        self::assertSame([], Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear([]));
    }

    public function testSortWithMissingYearAndAuthorKeys(): void
    {
        $citations = [
            [],
            ['author' => 'Smith, J', 'year' => 2024],
        ];

        $result = Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear($citations);

        // 2024 must come before a missing year (0)
        self::assertSame('Smith, J', $result[0]['author']);
    }

    // ────────────────────────────────────────────
    // formatAuthors
    // ────────────────────────────────────────────

    public function testFormatAuthorsNoOrcid(): void
    {
        $author = 'Doe, John;Smith, Jane';
        self::assertSame('Doe, John;Smith, Jane', Episciences_Paper_Citations_ViewFormatter::formatAuthors($author));
    }

    public function testFormatAuthorsWithOrcidInjectsLink(): void
    {
        $author = 'Doe, John, 0000-0001-2345-678X';
        $result = Episciences_Paper_Citations_ViewFormatter::formatAuthors($author);

        self::assertStringContainsString('https://orcid.org/0000-0001-2345-678X', $result);
        self::assertStringContainsString('href="', $result);
        self::assertStringContainsString('<img', $result);
    }

    public function testFormatAuthorsNoDoubleEncoding(): void
    {
        // Ampersand in author name: should appear as &amp; exactly once, not &amp;amp;
        $author = 'Smith &amp; Jones';
        $result = Episciences_Paper_Citations_ViewFormatter::formatAuthors($author);

        self::assertStringContainsString('&amp;', $result);
        self::assertStringNotContainsString('&amp;amp;', $result);
    }

    // ────────────────────────────────────────────
    // reduceAuthorsView
    // ────────────────────────────────────────────

    public function testReduceAuthorsViewUnderLimit(): void
    {
        $author = 'A;B;C';
        self::assertSame('A;B;C', Episciences_Paper_Citations_ViewFormatter::reduceAuthorsView($author));
    }

    public function testReduceAuthorsViewAtLimit(): void
    {
        $author = 'A;B;C;D;E';
        $result = Episciences_Paper_Citations_ViewFormatter::reduceAuthorsView($author);

        self::assertSame('A;B;C;D;E', $result);
        self::assertStringNotContainsString('et al.', $result);
    }

    public function testReduceAuthorsViewOverLimit(): void
    {
        $author = 'A;B;C;D;E;F;G';
        $result = Episciences_Paper_Citations_ViewFormatter::reduceAuthorsView($author);

        self::assertStringContainsString('et al.', $result);

        // Only first 5 authors plus "et al." — F and G must be removed
        $parts = explode(';', $result);
        self::assertCount(6, $parts);
        self::assertSame('et al.', trim(end($parts)));
        self::assertStringNotContainsString('F', $result);
        self::assertStringNotContainsString('G', $result);
    }

    // ────────────────────────────────────────────
    // createOrcidStringForView
    // ────────────────────────────────────────────

    public function testCreateOrcidStringForViewValidOrcid(): void
    {
        $result = Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView('0000-0001-2345-678X');

        self::assertStringContainsString('href="https://orcid.org/0000-0001-2345-678X"', $result);
        self::assertStringContainsString('<img', $result);
    }

    public function testCreateOrcidStringForViewStripsLeadingComma(): void
    {
        // This mimics how formatAuthors calls it with the regex match (includes leading ", ")
        $result = Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView(', 0000-0001-2345-678X');

        self::assertStringContainsString('href="https://orcid.org/0000-0001-2345-678X"', $result);
    }

    public function testCreateOrcidStringForViewInvalidFormat(): void
    {
        $result = Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView('not-an-orcid');

        self::assertSame('', $result);
    }

    public function testCreateOrcidStringForViewXssSafe(): void
    {
        $malicious = '"><script>alert(1)</script>';
        $result = Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView($malicious);

        // Invalid ORCID → empty string, no injection possible
        self::assertSame('', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    public function testCreateOrcidStringForViewHrefIsDoubleQuoted(): void
    {
        $result = Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView('0000-0002-9193-9560');

        // href must be properly quoted to prevent attribute injection
        self::assertMatchesRegularExpression('/href="https:\/\/orcid\.org\/0000-0002-9193-9560"/', $result);
    }

    // ────────────────────────────────────────────
    // reorganizeForBookChapter
    // ────────────────────────────────────────────

    public function testNormalizeForBookChapterPreservesOrder(): void
    {
        $citation = [
            'doi' => '10.1234/test',
            'author' => 'Doe, J',
            'year' => '2022',
            'title' => 'A Title',
            'type' => 'book-chapter',
        ];

        $result = Episciences_Paper_Citations_ViewFormatter::reorganizeForBookChapter($citation);
        $keys = array_keys($result);

        // 'author' must come first
        self::assertSame('author', $keys[0]);
        // 'source_title' must be second
        self::assertSame('source_title', $keys[1]);
        // doi and oa_link must be present (even if empty)
        self::assertArrayHasKey('doi', $result);
        self::assertArrayHasKey('oa_link', $result);
        self::assertSame('10.1234/test', $result['doi']);
    }

    public function testNormalizeForBookChapterFillsEmptyDefaults(): void
    {
        $result = Episciences_Paper_Citations_ViewFormatter::reorganizeForBookChapter([]);

        foreach (['author', 'source_title', 'title', 'volume', 'issue', 'page', 'year', 'doi', 'oa_link'] as $key) {
            self::assertArrayHasKey($key, $result);
            self::assertSame('', $result[$key]);
        }
    }

    // ────────────────────────────────────────────
    // reorganizeForProceedingsArticle
    // ────────────────────────────────────────────

    public function testNormalizeProceedingsArticlePreservesOrder(): void
    {
        $citation = ['event_place' => 'Paris', 'author' => 'Smith, J'];

        $result = Episciences_Paper_Citations_ViewFormatter::reorganizeForProceedingsArticle($citation);
        $keys = array_keys($result);

        self::assertSame('author', $keys[0]);
        self::assertArrayHasKey('event_place', $result);
        self::assertSame('Paris', $result['event_place']);
    }

    public function testNormalizeProceedingsArticleHasAllRequiredKeys(): void
    {
        $result = Episciences_Paper_Citations_ViewFormatter::reorganizeForProceedingsArticle([]);

        foreach (['author', 'source_title', 'title', 'volume', 'page', 'issue', 'year', 'event_place', 'doi', 'oa_link'] as $key) {
            self::assertArrayHasKey($key, $result);
        }
    }
}
