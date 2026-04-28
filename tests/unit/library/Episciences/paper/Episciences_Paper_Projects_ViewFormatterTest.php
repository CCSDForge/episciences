<?php

namespace unit\library\Episciences;

use Episciences_Paper_Projects_EnrichmentService;
use Episciences_Paper_Projects_ViewFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers Episciences_Paper_Projects_ViewFormatter
 */
final class Episciences_Paper_Projects_ViewFormatterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // formatForView() should always return array (never '')
    // -------------------------------------------------------------------------

    /**
     * Paper ID -1 does not exist → Repository returns empty → formatForView returns [].
     */
    public function testFormatForViewReturnsEmptyArrayWhenNoData(): void
    {
        $result = Episciences_Paper_Projects_ViewFormatter::formatForView(-1);
        self::assertIsArray($result);
        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // URL must be HTML-escaped in href attribute
    // -------------------------------------------------------------------------

    /**
     * A URL containing double-quote and ampersand must be escaped so the
     * rendered href cannot inject HTML or break attribute parsing.
     */
    public function testUrlIsHtmlEscapedInHref(): void
    {
        $tr = $this->buildTranslator();

        $vfunding = [
            'projectTitle' => 'Test Project',
            'funderName'   => 'Test Funder',
            'url'          => 'https://example.com/?a=1&b=2"evil',
        ];

        $html = $this->invokeRenderFundingEntry($vfunding, $tr);

        // The raw url must NOT appear unescaped in the href
        self::assertStringNotContainsString('"evil', $html);
        // The & must be escaped as &amp;
        self::assertStringContainsString('&amp;', $html);
        // The double-quote must be escaped as &quot;
        self::assertStringContainsString('&quot;', $html);
        // The href attribute must use the escaped version
        self::assertStringContainsString('href="https://example.com/?a=1&amp;b=2&quot;evil"', $html);
    }

    // -------------------------------------------------------------------------
    // Display text of URL must also be HTML-escaped
    // -------------------------------------------------------------------------

    public function testUrlDisplayTextIsHtmlEscaped(): void
    {
        $tr = $this->buildTranslator();

        $vfunding = [
            'projectTitle' => 'Test Project',
            'funderName'   => 'Test Funder',
            'url'          => 'https://example.com/<script>',
        ];

        $html = $this->invokeRenderFundingEntry($vfunding, $tr);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    // -------------------------------------------------------------------------
    // Unidentified title must not produce a visible <li>
    // -------------------------------------------------------------------------

    public function testUnidentifiedProjectTitleIsNotRendered(): void
    {
        $tr = $this->buildTranslator();

        $vfunding = [
            'projectTitle' => Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED,
            'funderName'   => Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED,
        ];

        $html = $this->invokeRenderFundingEntry($vfunding, $tr);

        // Both title and funderName are UNIDENTIFIED → nothing to render
        self::assertSame('', $html);
    }

    public function testEntryWithOnlyFunderNameRendersWithoutTitle(): void
    {
        $tr = $this->buildTranslator();

        $vfunding = [
            'funderName' => 'My Funder',
        ];

        $html = $this->invokeRenderFundingEntry($vfunding, $tr);

        self::assertStringContainsString('My Funder', $html);
        self::assertStringContainsString('<li>', $html);
        self::assertStringNotContainsString('<em>', $html);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a minimal Zend_Translate that returns the input string unchanged.
     * Zend_Translate delegates translate() via __call() so it cannot be mocked;
     * we use a real array-adapter instance with disableNotices to avoid log noise.
     */
    private function buildTranslator(): \Zend_Translate
    {
        return new \Zend_Translate([
            'adapter'        => 'array',
            'content'        => [],
            'locale'         => 'en',
            'disableNotices' => true,
        ]);
    }

    /**
     * Invoke the private static renderFundingEntry() method via reflection.
     */
    private function invokeRenderFundingEntry(array $vfunding, \Zend_Translate $tr): string
    {
        $method = new ReflectionMethod(Episciences_Paper_Projects_ViewFormatter::class, 'renderFundingEntry');
        $method->setAccessible(true);
        return (string) $method->invoke(null, $vfunding, $tr);
    }
}
