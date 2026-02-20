<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_FormatIssn;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_View_Helper_FormatIssn
 *
 * @covers Episciences_View_Helper_FormatIssn
 */
class FormatIssnTest extends TestCase
{
    /**
     * Test that null returns empty string
     */
    public function testNullReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_View_Helper_FormatIssn::FormatIssn(null));
    }

    /**
     * Test that empty string returns empty string
     */
    public function testEmptyStringReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_View_Helper_FormatIssn::FormatIssn(''));
    }

    /**
     * Test that a valid 8-char ISSN is formatted with a hyphen at position 4
     */
    public function testValidIssnIsFormatted(): void
    {
        $this->assertSame('1234-5678', Episciences_View_Helper_FormatIssn::FormatIssn('12345678'));
        $this->assertSame('0000-0000', Episciences_View_Helper_FormatIssn::FormatIssn('00000000'));
        $this->assertSame('2021-1234', Episciences_View_Helper_FormatIssn::FormatIssn('20211234'));
    }

    /**
     * Test that a valid ISSN ending with 'X' checksum is formatted correctly
     */
    public function testValidIssnWithXChecksumIsFormatted(): void
    {
        $this->assertSame('1234-567X', Episciences_View_Helper_FormatIssn::FormatIssn('1234567X'));
    }

    /**
     * Test that ISSNs shorter than 8 chars are returned as-is
     */
    public function testShortIssnReturnedAsIs(): void
    {
        $this->assertSame('123', Episciences_View_Helper_FormatIssn::FormatIssn('123'));
        $this->assertSame('1234567', Episciences_View_Helper_FormatIssn::FormatIssn('1234567'));
    }

    /**
     * Test that ISSNs longer than 8 chars are returned as-is
     */
    public function testLongIssnReturnedAsIs(): void
    {
        $this->assertSame('123456789', Episciences_View_Helper_FormatIssn::FormatIssn('123456789'));
        $this->assertSame('1234-5678', Episciences_View_Helper_FormatIssn::FormatIssn('1234-5678'));
    }

    /**
     * Test that the separator constant is a hyphen
     */
    public function testSeparatorConstant(): void
    {
        $this->assertSame('-', Episciences_View_Helper_FormatIssn::ISSN_NUMBER_SEPARATOR);
    }

    /**
     * Test that the string '0' returns empty string (PHP treats '0' as falsy)
     */
    public function testZeroStringReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_View_Helper_FormatIssn::FormatIssn('0'));
    }

    /**
     * Test that HTML chars in an 8-char ISSN are NOT escaped (caller must escape output).
     * Documents that FormatIssn is not XSS-safe on its own.
     */
    public function testHtmlCharsAreNotEscaped(): void
    {
        // '<b>12345' is 8 chars, formatted as '<b>1-2345' without escaping
        $result = Episciences_View_Helper_FormatIssn::FormatIssn('<b>12345');
        $this->assertSame('<b>1-2345', $result);
        $this->assertStringNotContainsString('&lt;', $result);
    }
}
