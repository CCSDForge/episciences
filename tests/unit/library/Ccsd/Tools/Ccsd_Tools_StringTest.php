<?php

namespace unit\library\Ccsd\Tools;

use Ccsd_Tools_String;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Tools_String
 *
 * Covers: stripCtrlChars, truncate (ST1 fix: mb_strlen/mb_substr),
 * utf8_ucfirst, xmlSafe, validateDate, stringToIso8601,
 * getAlphaLetter, cleanString (ST3 fix: $begreg→$endreg).
 */
class Ccsd_Tools_StringTest extends TestCase
{
    // ------------------------------------------------------------------
    // stripCtrlChars
    // ------------------------------------------------------------------

    public function testStripCtrlCharsRemovesNullByte(): void
    {
        $this->assertSame('hello', Ccsd_Tools_String::stripCtrlChars("hel\x00lo"));
    }

    public function testStripCtrlCharsKeepsNormalText(): void
    {
        $input = 'Hello World 123!';
        $this->assertSame($input, Ccsd_Tools_String::stripCtrlChars($input));
    }

    public function testStripCtrlCharsReplaceWithChar(): void
    {
        $result = Ccsd_Tools_String::stripCtrlChars("a\x01b", '-');
        $this->assertSame('a-b', $result);
    }

    public function testStripCtrlCharsPreserveNewlinesMode(): void
    {
        // $allCtrl=false: only strip non-newline control chars
        $result = Ccsd_Tools_String::stripCtrlChars("a\nb\x01c", '', false);
        $this->assertSame("a\nbc", $result);
    }

    public function testStripCtrlCharsEmptyInput(): void
    {
        $this->assertSame('', Ccsd_Tools_String::stripCtrlChars(''));
    }

    public function testStripCtrlCharsPreserveNewlinesWithNl2br(): void
    {
        $result = Ccsd_Tools_String::stripCtrlChars("line1\nline2", '', true, true);
        $this->assertSame('line1line2', $result);
    }

    // ------------------------------------------------------------------
    // truncate — ST1 fix: mb_strlen/mb_substr for multi-byte
    // ------------------------------------------------------------------

    public function testTruncateUnderLimit(): void
    {
        $this->assertSame('hello', Ccsd_Tools_String::truncate('hello', 10));
    }

    public function testTruncateExactLimit(): void
    {
        $this->assertSame('hello', Ccsd_Tools_String::truncate('hello', 5));
    }

    public function testTruncateOverLimitCutsAtSpace(): void
    {
        $result = Ccsd_Tools_String::truncate('hello world foo', 8);
        $this->assertSame('hello', $result, 'Should cut at space before position 8');
    }

    public function testTruncateOverLimitNoCutAtSpace(): void
    {
        $result = Ccsd_Tools_String::truncate('helloworld', 5, '', false);
        $this->assertSame('hello', $result);
    }

    public function testTruncateWithPostTruncateString(): void
    {
        $result = Ccsd_Tools_String::truncate('hello world foo', 8, '...', false);
        $this->assertSame('hello wo...', $result);
    }

    public function testTruncateZeroMaxLength(): void
    {
        $this->assertSame('', Ccsd_Tools_String::truncate('hello', 0));
    }

    /**
     * ST1 fix: before fix, strlen() counted bytes, causing wrong result on multi-byte strings.
     * A 5-char UTF-8 string with 2-byte chars (10 bytes total) should NOT be truncated at limit=7.
     */
    public function testTruncateMultibyteUtf8(): void
    {
        // "éàùîô" = 5 UTF-8 characters, each 2 bytes = 10 bytes total
        $input = 'éàùîô';
        // limit=5 chars: should return unchanged (exactly 5 chars)
        $result = Ccsd_Tools_String::truncate($input, 5, '...');
        $this->assertSame('éàùîô', $result, 'ST1 fix: limit >= mb_strlen should return unchanged');
    }

    public function testTruncateMultibyteCutsAtCharBoundary(): void
    {
        // "éàùîô world" = 11 UTF-8 chars, limit=5: should cut at 5 chars (not 5 bytes)
        $input = 'éàùîô world';
        $result = Ccsd_Tools_String::truncate($input, 5, '', false);
        $this->assertSame('éàùîô', $result, 'ST1 fix: truncation must respect char boundaries');
    }

    // ------------------------------------------------------------------
    // utf8_ucfirst
    // ------------------------------------------------------------------

    public function testUtf8UcfirstAscii(): void
    {
        $this->assertSame('Hello', Ccsd_Tools_String::utf8_ucfirst('hello'));
    }

    public function testUtf8UcfirstUtf8(): void
    {
        $this->assertSame('Élan', Ccsd_Tools_String::utf8_ucfirst('élan'));
    }

    public function testUtf8UcfirstEmpty(): void
    {
        $this->assertSame('', Ccsd_Tools_String::utf8_ucfirst(''));
    }

    public function testUtf8UcfirstSingleChar(): void
    {
        $this->assertSame('A', Ccsd_Tools_String::utf8_ucfirst('a'));
    }

    // ------------------------------------------------------------------
    // xmlSafe
    // ------------------------------------------------------------------

    public function testXmlSafeEscapesAmpersand(): void
    {
        $this->assertSame('a &amp; b', Ccsd_Tools_String::xmlSafe('a & b'));
    }

    public function testXmlSafeEscapesAngleBrackets(): void
    {
        $this->assertSame('&lt;tag&gt;', Ccsd_Tools_String::xmlSafe('<tag>'));
    }

    public function testXmlSafeEscapesQuotes(): void
    {
        $this->assertSame('say &quot;hi&quot;', Ccsd_Tools_String::xmlSafe('say "hi"'));
    }

    public function testXmlSafePassesThroughFalsy(): void
    {
        $this->assertSame('', Ccsd_Tools_String::xmlSafe(''));
    }

    public function testXmlSafePreservesNormalText(): void
    {
        $this->assertSame('Hello World', Ccsd_Tools_String::xmlSafe('Hello World'));
    }

    // ------------------------------------------------------------------
    // validateDate
    // ------------------------------------------------------------------

    public function testValidateDateValid(): void
    {
        $this->assertTrue(Ccsd_Tools_String::validateDate('2024-01-15'));
    }

    public function testValidateDateInvalid(): void
    {
        $this->assertFalse(Ccsd_Tools_String::validateDate('2024-13-01'));
    }

    public function testValidateDateCustomFormat(): void
    {
        $this->assertTrue(Ccsd_Tools_String::validateDate('15/01/2024', 'd/m/Y'));
    }

    public function testValidateDateBadFormat(): void
    {
        $this->assertFalse(Ccsd_Tools_String::validateDate('2024-01', 'Y-m-d'));
    }

    // ------------------------------------------------------------------
    // stringToIso8601
    // ------------------------------------------------------------------

    public function testStringToIso8601FullDate(): void
    {
        $result = Ccsd_Tools_String::stringToIso8601('2024-03-15');
        $this->assertSame('2024-03-15T00:00:00Z', $result);
    }

    public function testStringToIso8601YearMonth(): void
    {
        $result = Ccsd_Tools_String::stringToIso8601('2024-03');
        $this->assertSame('2024-03-01T00:00:00Z', $result);
    }

    public function testStringToIso8601Empty(): void
    {
        $this->assertSame('', Ccsd_Tools_String::stringToIso8601(''));
    }

    public function testStringToIso8601ZeroDate(): void
    {
        $this->assertSame('', Ccsd_Tools_String::stringToIso8601('0000-00-00'));
    }

    public function testStringToIso8601YearOnly(): void
    {
        $result = Ccsd_Tools_String::stringToIso8601('2024');
        $this->assertSame('2024-01-01T00:00:00Z', $result);
    }

    // ------------------------------------------------------------------
    // getAlphaLetter
    // ------------------------------------------------------------------

    public function testGetAlphaLetterAscii(): void
    {
        $this->assertSame('H', Ccsd_Tools_String::getAlphaLetter('Hello'));
    }

    public function testGetAlphaLetterLowercase(): void
    {
        $this->assertSame('W', Ccsd_Tools_String::getAlphaLetter('world'));
    }

    public function testGetAlphaLetterAccented(): void
    {
        $this->assertSame('E', Ccsd_Tools_String::getAlphaLetter('Élan'));
    }

    public function testGetAlphaLetterNumericReturnsOther(): void
    {
        $this->assertSame('other', Ccsd_Tools_String::getAlphaLetter('123 title'));
    }

    public function testGetAlphaLetterCustomMissing(): void
    {
        $this->assertSame('?', Ccsd_Tools_String::getAlphaLetter('42', '?'));
    }

    public function testGetAlphaLetterStripsLeadingPunctuation(): void
    {
        $this->assertSame('T', Ccsd_Tools_String::getAlphaLetter('"The title"'));
    }

    // ------------------------------------------------------------------
    // cleanString — ST3 fix: was using $begreg instead of $endreg for end-space
    // ------------------------------------------------------------------

    public function testCleanStringTrimBothEnds(): void
    {
        $result = Ccsd_Tools_String::cleanString('  hello  ', Ccsd_Tools_String::CLEAN_SPACES);
        $this->assertSame('hello', $result, 'ST3 fix: trailing spaces must be removed');
    }

    public function testCleanStringTrimBeginOnly(): void
    {
        $result = Ccsd_Tools_String::cleanString('  hello  ', Ccsd_Tools_String::CLEAN_BEG_SPACE);
        $this->assertSame('hello  ', $result);
    }

    public function testCleanStringTrimEndOnly(): void
    {
        // ST3 fix: before fix, $begreg was used in end regex so begin-only spaces were left
        $result = Ccsd_Tools_String::cleanString('  hello  ', Ccsd_Tools_String::CLEAN_END_SPACE);
        $this->assertSame('  hello', $result, 'ST3 fix: only trailing spaces should be removed');
    }

    public function testCleanStringRemoveInteriorSpaces(): void
    {
        $result = Ccsd_Tools_String::cleanString('a b c', Ccsd_Tools_String::CLEAN_ALL_SPACES);
        $this->assertSame('abc', $result);
    }

    public function testCleanStringKeepAlphaNumOnly(): void
    {
        $result = Ccsd_Tools_String::cleanString('hello, world!', Ccsd_Tools_String::CLEAN_EXCEPT_AZ);
        $this->assertSame('helloworld', $result);
    }
}
