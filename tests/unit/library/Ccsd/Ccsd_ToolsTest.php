<?php

namespace unit\library\Ccsd;

use Ccsd_Tools;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Tools
 *
 * Covers: formatAuthor, upperWord, br2space, in_next_array,
 * preg_in_array_get_key (T4 fix), preg_in_array, ifsetor,
 * cleanFileName, spaces2Space, stripAccents, truncate,
 * in_array_r, generatePw, decodeLatex, curlSourceGetContents (S2 fix).
 */
class Ccsd_ToolsTest extends TestCase
{
    // ------------------------------------------------------------------
    // formatAuthor / upperWord
    // ------------------------------------------------------------------

    public function testFormatAuthorBasic(): void
    {
        $result = Ccsd_Tools::formatAuthor('doe', 'john');
        $this->assertSame('Doe John', $result);
    }

    public function testFormatAuthorWithCiv(): void
    {
        $result = Ccsd_Tools::formatAuthor('smith', 'alice', 'Dr.');
        $this->assertSame('Dr. Smith Alice', $result);
    }

    public function testFormatAuthorEmptyInputs(): void
    {
        $this->assertSame('', Ccsd_Tools::formatAuthor('', ''));
    }

    public function testFormatAuthorLastnameOnly(): void
    {
        $result = Ccsd_Tools::formatAuthor('dupont');
        $this->assertSame('Dupont', $result);
    }

    public function testFormatUserDelegatesToFormatAuthor(): void
    {
        $this->assertSame(
            Ccsd_Tools::formatAuthor('martin', 'jean'),
            Ccsd_Tools::formatUser('martin', 'jean')
        );
    }

    public function testUpperWordParticleVon(): void
    {
        $result = Ccsd_Tools::upperWord('von mises');
        $this->assertSame('von Mises', $result);
    }

    public function testUpperWordParticleVanDer(): void
    {
        $result = Ccsd_Tools::upperWord('van der waals');
        $this->assertSame('van der Waals', $result);
    }

    public function testUpperWordHyphenated(): void
    {
        $result = Ccsd_Tools::upperWord('jean-pierre');
        $this->assertSame('Jean-Pierre', $result);
    }

    public function testUpperWordEmpty(): void
    {
        $this->assertSame('', Ccsd_Tools::upperWord(''));
    }

    // ------------------------------------------------------------------
    // br2space
    // ------------------------------------------------------------------

    public function testBr2spaceSelfClosing(): void
    {
        $this->assertSame('line1 line2', Ccsd_Tools::br2space('line1<br/>line2'));
    }

    public function testBr2spaceLongForm(): void
    {
        $this->assertSame('a b', Ccsd_Tools::br2space('a<br>b'));
    }

    public function testBr2spaceNull(): void
    {
        $this->assertNull(Ccsd_Tools::br2space(null));
    }

    public function testBr2spaceEmpty(): void
    {
        $this->assertSame('', Ccsd_Tools::br2space(''));
    }

    public function testBr2spaceCaseInsensitive(): void
    {
        $this->assertSame('x y', Ccsd_Tools::br2space('x<BR>y'));
    }

    // ------------------------------------------------------------------
    // in_next_array
    // ------------------------------------------------------------------

    public function testInNextArrayFound(): void
    {
        $array = [
            ['id' => 1, 'name' => 'alice'],
            ['id' => 2, 'name' => 'bob'],
        ];
        $this->assertSame(1, Ccsd_Tools::in_next_array('bob', $array, 'name'));
    }

    public function testInNextArrayNotFound(): void
    {
        $array = [['id' => 1, 'name' => 'alice']];
        $this->assertNull(Ccsd_Tools::in_next_array('charlie', $array, 'name'));
    }

    public function testInNextArrayAll(): void
    {
        $array = [
            ['type' => 'x'],
            ['type' => 'x'],
            ['type' => 'y'],
        ];
        $result = Ccsd_Tools::in_next_array('x', $array, 'type', true);
        $this->assertSame([0, 1], $result);
    }

    public function testInNextArrayEmptyArray(): void
    {
        $this->assertNull(Ccsd_Tools::in_next_array('foo', [], 'key'));
    }

    // ------------------------------------------------------------------
    // preg_in_array_get_key — T4 fix: preg_quote($needle)
    // ------------------------------------------------------------------

    public function testPregInArrayGetKeySimpleMatch(): void
    {
        $array = ['hello world', 'foo bar', 'test'];
        $this->assertSame(0, Ccsd_Tools::preg_in_array_get_key('hello', $array));
    }

    public function testPregInArrayGetKeyNotFound(): void
    {
        $this->assertNull(Ccsd_Tools::preg_in_array_get_key('xyz', ['aaa', 'bbb']));
    }

    public function testPregInArrayGetKeyNeedleWithDot(): void
    {
        // T4: dot in needle must match literally (not any char) after preg_quote fix
        $array = ['10.5555/abc', '10Xfoo'];
        $key = Ccsd_Tools::preg_in_array_get_key('10.5555', $array);
        $this->assertSame(0, $key, 'Literal dot in needle should match element 0 only');
    }

    public function testPregInArrayGetKeyNeedleWithSlash(): void
    {
        // T4: slash in needle must not break the regex delimiter
        $array = ['math/0602059', 'other'];
        $key = Ccsd_Tools::preg_in_array_get_key('math/0602059', $array);
        $this->assertSame(0, $key);
    }

    public function testPregInArrayReturnsBool(): void
    {
        $array = ['hello', 'world'];
        $this->assertTrue(Ccsd_Tools::preg_in_array('hello', $array));
        $this->assertFalse(Ccsd_Tools::preg_in_array('nothere', $array));
    }

    // ------------------------------------------------------------------
    // ifsetor
    // ------------------------------------------------------------------

    public function testIfsetorWithValue(): void
    {
        $v = 'existing';
        $this->assertSame('existing', Ccsd_Tools::ifsetor($v, 'default'));
    }

    public function testIfsetorNullUsesDefault(): void
    {
        $v = null;
        $this->assertSame('default', Ccsd_Tools::ifsetor($v, 'default'));
    }

    public function testIfsetorDefaultIsEmpty(): void
    {
        $v = null;
        $this->assertSame('', Ccsd_Tools::ifsetor($v));
    }

    // ------------------------------------------------------------------
    // cleanFileName / spaces2Space / stripAccents
    // ------------------------------------------------------------------

    public function testCleanFileNameRemovesSpecialChars(): void
    {
        $result = Ccsd_Tools::cleanFileName('My File (1).txt');
        $this->assertMatchesRegularExpression('/^[a-z0-9_.\-\/\\\\]+$/i', $result);
    }

    public function testCleanFileNameCollapsesDots(): void
    {
        $result = Ccsd_Tools::cleanFileName('file...txt');
        $this->assertSame('file.txt', $result);
    }

    public function testSpaces2SpaceCollapsesMultiple(): void
    {
        $this->assertSame('a b c', Ccsd_Tools::spaces2Space('a  b   c'));
    }

    public function testSpaces2SpaceSingleSpace(): void
    {
        $this->assertSame('a b', Ccsd_Tools::spaces2Space('a b'));
    }

    public function testStripAccentsBasic(): void
    {
        $this->assertSame('eEaAiIuU', Ccsd_Tools::stripAccents('éÉàÀïÏùÙ'));
    }

    public function testStripAccentsCzech(): void
    {
        $this->assertSame('cCsS', Ccsd_Tools::stripAccents('čČšŠ'));
    }

    public function testStripAccentsLigatures(): void
    {
        $this->assertSame('aeAEoeOE', Ccsd_Tools::stripAccents('æÆœŒ'));
    }

    // ------------------------------------------------------------------
    // truncate (Ccsd_Tools version — already mb-safe)
    // ------------------------------------------------------------------

    public function testTruncateShortStringUnchanged(): void
    {
        $this->assertSame('hello', Ccsd_Tools::truncate('hello', 100));
    }

    public function testTruncateLongString(): void
    {
        $result = Ccsd_Tools::truncate('abcdefghij', 5);
        $this->assertSame('abcde...', $result);
    }

    public function testTruncateMultibyte(): void
    {
        // "café" is 4 characters but more than 4 bytes
        $result = Ccsd_Tools::truncate('café latte macchiato', 4);
        $this->assertSame('café...', $result);
    }

    public function testTruncateCustomReplacement(): void
    {
        $result = Ccsd_Tools::truncate('hello world', 5, ' [more]');
        $this->assertSame('hello [more]', $result);
    }

    // ------------------------------------------------------------------
    // in_array_r
    // ------------------------------------------------------------------

    public function testInArrayRFlat(): void
    {
        $this->assertTrue(Ccsd_Tools::in_array_r('b', ['a', 'b', 'c']));
    }

    public function testInArrayRNested(): void
    {
        $this->assertTrue(Ccsd_Tools::in_array_r('deep', ['a', ['b', ['deep']]]));
    }

    public function testInArrayRNotFound(): void
    {
        $this->assertFalse(Ccsd_Tools::in_array_r('x', ['a', ['b', 'c']]));
    }

    public function testInArrayRStrict(): void
    {
        $this->assertFalse(Ccsd_Tools::in_array_r('1', [1, 2], true));
        $this->assertTrue(Ccsd_Tools::in_array_r('1', ['1', '2'], true));
    }

    // ------------------------------------------------------------------
    // generatePw
    // ------------------------------------------------------------------

    public function testGeneratePwLength(): void
    {
        $pw = Ccsd_Tools::generatePw(10, 15);
        $len = strlen($pw);
        $this->assertGreaterThanOrEqual(10, $len);
        $this->assertLessThanOrEqual(15, $len);
    }

    public function testGeneratePwIsString(): void
    {
        $this->assertIsString(Ccsd_Tools::generatePw());
    }

    public function testGeneratePwUnique(): void
    {
        $this->assertNotSame(Ccsd_Tools::generatePw(), Ccsd_Tools::generatePw());
    }

    // ------------------------------------------------------------------
    // decodeLatex
    // ------------------------------------------------------------------

    public function testDecodeLatexAmpersand(): void
    {
        $this->assertSame('a \\& b', Ccsd_Tools::decodeLatex('a & b'));
    }

    public function testDecodeLatexAccent(): void
    {
        $this->assertSame("{\\'e}", Ccsd_Tools::decodeLatex('é'));
    }

    public function testDecodeLatexGreekAlpha(): void
    {
        $result = Ccsd_Tools::decodeLatex('α', true);
        $this->assertSame('$\\alpha$', $result);
    }

    public function testDecodeLatexGreekDisabled(): void
    {
        // With $greekRecode=false, Greek chars pass through unchanged
        $result = Ccsd_Tools::decodeLatex('α', false);
        $this->assertSame('α', $result);
    }

    public function testDecodeLatexEmpty(): void
    {
        $this->assertSame('', Ccsd_Tools::decodeLatex(''));
    }

    // ------------------------------------------------------------------
    // curlSourceGetContents — S2 security fix: SSRF scheme validation
    // ------------------------------------------------------------------

    public function testCurlSourceGetContentsRejectsFileScheme(): void
    {
        $this->expectException(\Ccsd_Error::class);
        Ccsd_Tools::curlSourceGetContents('file:///etc/passwd');
    }

    public function testCurlSourceGetContentsRejectsFtpScheme(): void
    {
        $this->expectException(\Ccsd_Error::class);
        Ccsd_Tools::curlSourceGetContents('ftp://example.com/data');
    }

    public function testCurlSourceGetContentsRejectsGopherScheme(): void
    {
        $this->expectException(\Ccsd_Error::class);
        Ccsd_Tools::curlSourceGetContents('gopher://example.com/');
    }
}
