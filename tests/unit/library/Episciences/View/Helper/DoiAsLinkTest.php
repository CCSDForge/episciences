<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_DoiAsLink;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_View_Helper_DoiAsLink
 *
 * @covers Episciences_View_Helper_DoiAsLink
 */
class DoiAsLinkTest extends TestCase
{
    /**
     * Test that empty DOI returns empty string
     */
    public function testEmptyDoiReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_View_Helper_DoiAsLink::DoiAsLink(''));
        $this->assertSame('', Episciences_View_Helper_DoiAsLink::DoiAsLink());
    }

    /**
     * Test HTML link with DOI used as link text when no custom text is provided
     */
    public function testHtmlLinkWithDoiAsText(): void
    {
        $result = Episciences_View_Helper_DoiAsLink::DoiAsLink('10.1000/xyz', '', true);

        $this->assertSame(
            '<a rel="noopener noreferrer" href="https://doi.org/10.1000/xyz">https://doi.org/10.1000/xyz</a>',
            $result
        );
    }

    /**
     * Test HTML link with custom text replacing the DOI in the visible part
     */
    public function testHtmlLinkWithCustomText(): void
    {
        $result = Episciences_View_Helper_DoiAsLink::DoiAsLink('10.1000/xyz', 'Read article', true);

        $this->assertSame(
            '<a rel="noopener noreferrer" href="https://doi.org/10.1000/xyz">Read article</a>',
            $result
        );
    }

    /**
     * Test plain URL (no HTML) returns just the doi.org URL
     */
    public function testPlainUrlReturnsDoiOrgUrl(): void
    {
        $result = Episciences_View_Helper_DoiAsLink::DoiAsLink('10.1000/xyz', '', false);

        $this->assertSame('https://doi.org/10.1000/xyz', $result);
    }

    /**
     * Test that asHtml defaults to true
     */
    public function testAsHtmlDefaultsToTrue(): void
    {
        $result = Episciences_View_Helper_DoiAsLink::DoiAsLink('10.1234/test');

        $this->assertStringStartsWith('<a ', $result);
        $this->assertStringContainsString('https://doi.org/10.1234/test', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    /**
     * Test that a DOI with special characters is correctly embedded in URL
     */
    public function testDoiWithSpecialCharactersInUrl(): void
    {
        $doi = '10.1016/j.cell.2013.05.039';
        $result = Episciences_View_Helper_DoiAsLink::DoiAsLink($doi, '', false);

        $this->assertSame('https://doi.org/10.1016/j.cell.2013.05.039', $result);
    }

    /**
     * Test that null/false DOI returns empty string
     */
    public function testFalseDoiReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_View_Helper_DoiAsLink::DoiAsLink(null));
        $this->assertSame('', Episciences_View_Helper_DoiAsLink::DoiAsLink(false));
    }
}
