<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_Tag;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_View_Helper_Tag
 *
 * @covers Episciences_View_Helper_Tag
 */
class TagTest extends TestCase
{
    /**
     * Test that empty string returns false
     */
    public function testEmptyTagReturnsFalse(): void
    {
        $this->assertFalse(Episciences_View_Helper_Tag::Tag(''));
    }

    /**
     * Test that null/false returns false
     */
    public function testFalseTagReturnsFalse(): void
    {
        $this->assertFalse(Episciences_View_Helper_Tag::Tag(null));
        $this->assertFalse(Episciences_View_Helper_Tag::Tag(false));
    }

    /**
     * Test that the string '0' returns false (PHP treats '0' as falsy).
     * Documents that Tag('0') short-circuits rather than looking up TAG_0.
     */
    public function testZeroStringReturnsFalse(): void
    {
        $this->assertFalse(Episciences_View_Helper_Tag::Tag('0'));
    }

    /**
     * Test that a valid tag name returns the corresponding mail tag constant value
     */
    public function testValidTagReturnsConstantValue(): void
    {
        $this->assertSame('%%REVIEW_CODE%%', Episciences_View_Helper_Tag::Tag('REVIEW_CODE'));
        $this->assertSame('%%REVIEW_NAME%%', Episciences_View_Helper_Tag::Tag('REVIEW_NAME'));
    }

    /**
     * Test that SENDER tags return their correct values
     */
    public function testSenderTags(): void
    {
        $this->assertSame('%%SENDER_FULL_NAME%%', Episciences_View_Helper_Tag::Tag('SENDER_FULL_NAME'));
        $this->assertSame('%%SENDER_EMAIL%%', Episciences_View_Helper_Tag::Tag('SENDER_EMAIL'));
        $this->assertSame('%%SENDER_FIRST_NAME%%', Episciences_View_Helper_Tag::Tag('SENDER_FIRST_NAME'));
        $this->assertSame('%%SENDER_LAST_NAME%%', Episciences_View_Helper_Tag::Tag('SENDER_LAST_NAME'));
    }

    /**
     * Test that RECIPIENT tags return their correct values
     */
    public function testRecipientTags(): void
    {
        $this->assertSame('%%RECIPIENT_FULL_NAME%%', Episciences_View_Helper_Tag::Tag('RECIPIENT_FULL_NAME'));
        $this->assertSame('%%RECIPIENT_EMAIL%%', Episciences_View_Helper_Tag::Tag('RECIPIENT_EMAIL'));
        $this->assertSame('%%RECIPIENT_USERNAME%%', Episciences_View_Helper_Tag::Tag('RECIPIENT_USERNAME'));
    }

    /**
     * Test that PAPER tags return their correct values
     */
    public function testPaperTags(): void
    {
        $this->assertSame('%%PAPER_RATING_URL%%', Episciences_View_Helper_Tag::Tag('PAPER_RATING_URL'));
        $this->assertSame('%%PAPER_VIEW_URL%%', Episciences_View_Helper_Tag::Tag('PAPER_VIEW_URL'));
        $this->assertSame('%%PAPER_ADMINISTRATION_URL%%', Episciences_View_Helper_Tag::Tag('PAPER_ADMINISTRATION_URL'));
    }

    /**
     * Test that an undefined tag name throws an Error (PHP 8.1+ raises Error for undefined class constants)
     */
    public function testUndefinedTagThrowsError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Undefined constant/');
        Episciences_View_Helper_Tag::Tag('NONEXISTENT_TAG_XYZ');
    }
}
