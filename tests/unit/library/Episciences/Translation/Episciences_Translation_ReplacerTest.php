<?php

namespace unit\library\Episciences\Translation;

use Episciences_Translation_Replacer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Translation_Replacer.
 *
 * All tests are DB-free: Replacer is a pure string-transformation service.
 *
 * @covers Episciences_Translation_Replacer
 */
final class Episciences_Translation_ReplacerTest extends TestCase
{
    // =========================================================================
    // replace() — basic substitution
    // =========================================================================

    public function testReplaceSinglePairCaseInsensitive(): void
    {
        $content  = "'greeting' => 'Hello World'";
        $replacer = new Episciences_Translation_Replacer(['Hello'], ['Hi'], false);

        $result = $replacer->replace($content);

        self::assertSame("'greeting' => 'Hi World'", $result);
        self::assertSame(1, $replacer->getReplacementCount());
    }

    public function testReplaceSinglePairCaseSensitiveMatchesExactCase(): void
    {
        $content  = "'msg' => 'Hello World'";
        $replacer = new Episciences_Translation_Replacer(['hello'], ['Hi'], true);

        $result = $replacer->replace($content);

        self::assertSame($content, $result, 'Case-sensitive search must not match different case.');
        self::assertSame(0, $replacer->getReplacementCount());
    }

    public function testReplaceSinglePairCaseSensitiveMatchesSameCase(): void
    {
        $content  = "'msg' => 'Hello World'";
        $replacer = new Episciences_Translation_Replacer(['Hello'], ['Hi'], true);

        $result = $replacer->replace($content);

        self::assertSame("'msg' => 'Hi World'", $result);
        self::assertSame(1, $replacer->getReplacementCount());
    }

    public function testReplaceMultiplePairs(): void
    {
        $content = "'a' => 'foo bar baz'";
        $replacer = new Episciences_Translation_Replacer(
            ['foo', 'baz'],
            ['FOO', 'BAZ'],
            false
        );

        $result = $replacer->replace($content);

        self::assertSame("'a' => 'FOO bar BAZ'", $result);
        self::assertSame(2, $replacer->getReplacementCount());
    }

    public function testReplaceDoesNotTouchKeys(): void
    {
        $content  = "'search_me' => 'value here'";
        $replacer = new Episciences_Translation_Replacer(['search_me'], ['replaced'], false);

        $result = $replacer->replace($content);

        self::assertSame("'search_me' => 'value here'", $result, 'Key must not be modified.');
        self::assertSame(0, $replacer->getReplacementCount());
    }

    public function testReplacePreservesDoubleQuotes(): void
    {
        $content  = '"key" => "old value"';
        $replacer = new Episciences_Translation_Replacer(['old'], ['new'], false);

        $result = $replacer->replace($content);

        self::assertSame('"key" => "new value"', $result);
    }

    public function testReplacePreservesMixedQuoteStyles(): void
    {
        $content  = "'key' => \"old value\"";
        $replacer = new Episciences_Translation_Replacer(['old'], ['new'], false);

        $result = $replacer->replace($content);

        // The regex matches key-quote and value-quote independently via backreferences,
        // but the same backreference (\1 for key, \3 for value) means key and value
        // must each be consistently single- or double-quoted.
        // 'key' uses \1=single, "old value" uses \3=double → two separate matches,
        // only the value match is on "old value".
        self::assertStringContainsString('new value', $result);
    }

    public function testReplaceWithNoMatch(): void
    {
        $content  = "'key' => 'hello'";
        $replacer = new Episciences_Translation_Replacer(['xyz'], ['abc'], false);

        $result = $replacer->replace($content);

        self::assertSame($content, $result);
        self::assertSame(0, $replacer->getReplacementCount());
    }

    public function testReplaceWithEmptyContent(): void
    {
        $replacer = new Episciences_Translation_Replacer(['foo'], ['bar'], false);

        $result = $replacer->replace('');

        self::assertSame('', $result);
        self::assertSame(0, $replacer->getReplacementCount());
    }

    public function testReplaceMultipleLines(): void
    {
        $content = implode(PHP_EOL, [
            "<?php",
            "return [",
            "    'a' => 'old text one',",
            "    'b' => 'keep this',",
            "    'c' => 'old text two',",
            "];",
        ]);

        $replacer = new Episciences_Translation_Replacer(['old text'], ['new text'], false);
        $result   = $replacer->replace($content);

        self::assertStringContainsString("'a' => 'new text one'", $result);
        self::assertStringContainsString("'b' => 'keep this'", $result);
        self::assertStringContainsString("'c' => 'new text two'", $result);
        self::assertSame(2, $replacer->getReplacementCount());
    }

    public function testReplacementCountResetsOnSecondCall(): void
    {
        $replacer = new Episciences_Translation_Replacer(['old'], ['new'], false);
        $replacer->replace("'k' => 'old value'");

        self::assertSame(1, $replacer->getReplacementCount());

        $replacer->replace("'k' => 'nothing matches'");

        self::assertSame(0, $replacer->getReplacementCount(), 'Count must reset on each call.');
    }

    public function testReplaceWithEmptySearchReturnsContentUnchanged(): void
    {
        $content  = "'key' => 'value'";
        $replacer = new Episciences_Translation_Replacer([], [], false);

        $result = $replacer->replace($content);

        self::assertSame($content, $result);
        self::assertSame(0, $replacer->getReplacementCount());
    }

    // =========================================================================
    // countSignificantLines()
    // =========================================================================

    public function testCountSignificantLinesOnlyCountsKeyValueLines(): void
    {
        $content = implode(PHP_EOL, [
            "<?php",
            "// a comment",
            "'key1' => 'val1',",
            "'key2' => 'val2',",
            "];",
        ]);

        self::assertSame(2, Episciences_Translation_Replacer::countSignificantLines($content));
    }

    public function testCountSignificantLinesEmptyString(): void
    {
        self::assertSame(0, Episciences_Translation_Replacer::countSignificantLines(''));
    }

    public function testCountSignificantLinesNoKeyValuePairs(): void
    {
        $content = implode(PHP_EOL, ["<?php", "return [];", "// comment"]);

        self::assertSame(0, Episciences_Translation_Replacer::countSignificantLines($content));
    }

    public function testCountSignificantLinesWithDoubleQuotes(): void
    {
        $content = implode(PHP_EOL, [
            '"k1" => "v1",',
            '"k2" => "v2",',
        ]);

        self::assertSame(2, Episciences_Translation_Replacer::countSignificantLines($content));
    }

    // =========================================================================
    // getReplacementCount() initial state
    // =========================================================================

    public function testGetReplacementCountIsZeroBeforeAnyReplace(): void
    {
        $replacer = new Episciences_Translation_Replacer(['a'], ['b'], false);

        self::assertSame(0, $replacer->getReplacementCount());
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testReplaceHandlesSpecialRegexCharsInSearchTerm(): void
    {
        $content  = "'key' => 'price (EUR) 100'";
        $replacer = new Episciences_Translation_Replacer(['(EUR)'], ['[EUR]'], false);

        $result = $replacer->replace($content);

        self::assertStringContainsString('[EUR]', $result);
        self::assertSame(1, $replacer->getReplacementCount());
    }

    public function testCaseInsensitiveReplaceMatchesDifferentCases(): void
    {
        $content  = "'k' => 'HELLO hello Hello'";
        $replacer = new Episciences_Translation_Replacer(['hello'], ['hi'], false);

        $result = $replacer->replace($content);

        // str_ireplace replaces all occurrences of the term in one pass.
        // The replacement count tracks how many search/replace pairs produced a change,
        // not the number of individual occurrences within the string.
        self::assertStringContainsString("'k' => 'hi hi hi'", $result);
        self::assertSame(1, $replacer->getReplacementCount());
    }
}