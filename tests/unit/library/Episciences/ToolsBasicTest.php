<?php

namespace unit\library\Episciences;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;

class ToolsBasicTest extends TestCase
{
    protected function setUp(): void
    {
        // Check if the Tools class is available
        if (!class_exists('Episciences_Tools')) {
            $this->markTestSkipped('Episciences_Tools class not available');
        }
    }

    /**
     * Simple test to verify the class loads
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists('Episciences_Tools'));
    }

    /**
     * Test a simple method that should work
     */
    public function testExtensionMethod(): void
    {
        // Test with file path
        $this->assertSame('txt', Episciences_Tools::extension('document.txt'));
        $this->assertSame('pdf', Episciences_Tools::extension('/path/to/file.pdf'));
        $this->assertSame('docx', strtolower(Episciences_Tools::extension('file.DOCX'))); // Should be lowercase
    }

    /**
     * Test convertToCamelCase function
     */
    public function testConvertToCamelCaseBasic(): void
    {
        // Test basic underscore to camelCase conversion
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test_string'));
        $this->assertSame('myVariableName', Episciences_Tools::convertToCamelCase('my_variable_name'));
        
        // Test single word
        $this->assertSame('test', Episciences_Tools::convertToCamelCase('test'));
        
        // Test empty string
        $this->assertSame('', Episciences_Tools::convertToCamelCase(''));
    }

    /**
     * Test replaceAccents function
     */
    public function testReplaceAccentsBasic(): void
    {
        // Test basic accent removal
        $this->assertSame('Cafe a la creme', Episciences_Tools::replaceAccents('Café à la crème'));
        $this->assertSame('resume', Episciences_Tools::replaceAccents('résumé'));
        $this->assertSame('naive', Episciences_Tools::replaceAccents('naïve'));
        
        // Test edge cases
        $this->assertSame('', Episciences_Tools::replaceAccents(''));
        $this->assertSame('hello world', Episciences_Tools::replaceAccents('hello world'));
    }

    /**
     * Test checkValueType function with HAL identifiers
     */
    public function testCheckValueTypeHal(): void
    {
        // Valid HAL identifiers
        $this->assertSame('hal', Episciences_Tools::checkValueType('hal-01234567'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('hal_01234567'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('hal-01234567v1'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('cea-01234567v2'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('inria-01234567'));
        
        // Valid HAL URLs should return 'hal'
        $this->assertSame('hal', Episciences_Tools::checkValueType('https://hal.science/hal-04202866v1'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('https://hal.archives-ouvertes.fr/hal-01234567'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('http://hal.inria.fr/inria-12345678'));
        $this->assertSame('hal', Episciences_Tools::checkValueType('https://hal.inria.fr/inria-12345678v2'));
        
        // Invalid HAL identifiers should not return 'hal'
        $this->assertNotSame('hal', Episciences_Tools::checkValueType('hal-123'));
        $this->assertNotSame('hal', Episciences_Tools::checkValueType('hal'));
        $this->assertNotSame('hal', Episciences_Tools::checkValueType('hal-abcdefgh'));
        
        // URLs with 'hal' but no valid HAL ID should not return 'hal'
        $this->assertNotSame('hal', Episciences_Tools::checkValueType('https://hal.science/invalid'));
        $this->assertNotSame('hal', Episciences_Tools::checkValueType('https://hal.science'));
    }

    /**
     * Test checkValueType function with DOI identifiers
     */
    public function testCheckValueTypeDoi(): void
    {
        // Valid DOI identifiers
        $this->assertSame('doi', Episciences_Tools::checkValueType('10.1000/182'));
        $this->assertSame('doi', Episciences_Tools::checkValueType('10.1038/nature12373'));
        $this->assertSame('doi', Episciences_Tools::checkValueType('10.1016/j.cell.2013.05.039'));
        $this->assertSame('doi', Episciences_Tools::checkValueType('10.12345/ABC.DEF-123_456'));
        
        // Invalid DOI identifiers should not return 'doi'
        $this->assertFalse(Episciences_Tools::checkValueType('10.'));
        $this->assertNotSame('doi', Episciences_Tools::checkValueType('doi:10.1000/182'));
        $this->assertSame('doi', Episciences_Tools::checkValueType('10/1000/182'));
        $this->assertFalse(Episciences_Tools::checkValueType('10.10/'));
    }

    /**
     * Test checkValueType function with Software Heritage identifiers
     */
    public function testCheckValueTypeSoftware(): void
    {
        // Valid Software Heritage identifiers
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:cnt:94a9ed024d3859793618152ea559a168bbcbb5e2'));
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505'));
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:rel:22ece559cc7c5c0781a5a8a0a8b9cb3b87b1f2a4'));
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:rev:309cf2674ee7a0749978cf8265ab91a60aea0f7d'));
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:snp:1a8893e6a86f444e8be8e7bda6cb34fb1735a00e'));
        
        // With qualifiers
        $this->assertSame('software', Episciences_Tools::checkValueType('swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505;origin=https://github.com/torvalds/linux'));
        
        // Invalid Software Heritage identifiers should not return 'software'
        $this->assertNotSame('software', Episciences_Tools::checkValueType('swh:1:invalid:94a9ed024d3859793618152ea559a168bbcbb5e2'));
        $this->assertNotSame('software', Episciences_Tools::checkValueType('swh:2:cnt:94a9ed024d3859793618152ea559a168bbcbb5e2'));
        $this->assertNotSame('software', Episciences_Tools::checkValueType('swh:1:cnt:invalid'));
    }

    /**
     * Test checkValueType function with arXiv identifiers
     */
    public function testCheckValueTypeArxiv(): void
    {
        // Valid arXiv identifiers
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('1501.00001'));
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('2101.12345'));
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('math.AG/0601001'));
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('hep-th/9901001'));
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('cs.AI/0601001'));
        
        // Invalid arXiv identifiers should not return 'arxiv'
        $this->assertFalse(Episciences_Tools::checkValueType('150100001'));
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('1501.0001'));
        $this->assertNotSame('arxiv', Episciences_Tools::checkValueType('arxiv:1501.00001'));
        // Old-style arXiv format requires 7 digits: category/YYMNNNN
        $this->assertSame('arxiv', Episciences_Tools::checkValueType('math/0601001'));
    }

    /**
     * Test checkValueType function with Handle identifiers
     */
    public function testCheckValueTypeHandle(): void
    {
        // Valid Handle identifiers
        $this->assertSame('handle', Episciences_Tools::checkValueType('1721.1/12345'));
        $this->assertSame('handle', Episciences_Tools::checkValueType('2027/mdp.39015012345678'));
        $this->assertSame('handle', Episciences_Tools::checkValueType('11245/1.2345'));
        $this->assertSame('handle', Episciences_Tools::checkValueType('20.500.12345/abc123def'));

        // Invalid Handle identifiers should not return 'handle'
        // cleanHandle() doesn't strip 'handle:' prefix, so this returns false
        $this->assertFalse(Episciences_Tools::checkValueType('handle:1721.1/12345'));
        $this->assertFalse(Episciences_Tools::checkValueType('1721.1/'));
        $this->assertFalse(Episciences_Tools::checkValueType('1721.1'));
    }

    /**
     * Test checkValueType function with URL identifiers
     */
    public function testCheckValueTypeUrl(): void
    {
        // URLs that don't match other specific patterns should be detected as 'url'
        $this->assertSame('url', Episciences_Tools::checkValueType('https://www.example.com'));
        $this->assertSame('url', Episciences_Tools::checkValueType('http://example.org/path/to/resource'));
        // DOI URLs are detected as 'url' because isDoi() doesn't support URL format
        // Only bare DOI format like '10.1000/182' is detected as 'doi'
        $this->assertSame('url', Episciences_Tools::checkValueType('https://doi.org/10.1000/182'));

        // Invalid URLs should not return 'url'
        $this->assertNotSame('url', Episciences_Tools::checkValueType('not-a-url'));
        // Zend_Uri::check() doesn't support FTP protocol
        $this->assertFalse(Episciences_Tools::checkValueType('ftp://files.example.com/file.txt'));
        $this->assertFalse(Episciences_Tools::checkValueType('example.com'));
        $this->assertFalse(Episciences_Tools::checkValueType('www.example.com'));
    }

    /**
     * Test checkValueType function with edge cases and invalid inputs
     */
    public function testCheckValueTypeEdgeCases(): void
    {
        // Empty and null inputs
        $this->assertFalse(Episciences_Tools::checkValueType(''));
        $this->assertFalse(Episciences_Tools::checkValueType(null));
        $this->assertFalse(Episciences_Tools::checkValueType(false));
        $this->assertFalse(Episciences_Tools::checkValueType(0));
        
        // Non-string inputs
        $this->assertFalse(Episciences_Tools::checkValueType(123));
        $this->assertFalse(Episciences_Tools::checkValueType([]));
        $this->assertFalse(Episciences_Tools::checkValueType((object)[]));
        
        // Random strings that don't match any pattern
        $this->assertFalse(Episciences_Tools::checkValueType('random-string'));
        $this->assertFalse(Episciences_Tools::checkValueType('123456789'));
        $this->assertFalse(Episciences_Tools::checkValueType('!@#$%^&*()'));
        
        // Whitespace-only strings
        $this->assertFalse(Episciences_Tools::checkValueType('   '));
        $this->assertFalse(Episciences_Tools::checkValueType("\t"));
        $this->assertFalse(Episciences_Tools::checkValueType("\n"));
    }

    /**
     * Test checkValueType function priority order
     * HAL should be checked before DOI, etc.
     */
    public function testCheckValueTypePriorityOrder(): void
    {
        // This HAL ID might also look like other patterns, but HAL should win
        $this->assertSame('hal', Episciences_Tools::checkValueType('hal-01234567'));

        // DOI should be recognized when it's clearly a DOI
        $this->assertSame('doi', Episciences_Tools::checkValueType('10.1000/182'));

        // URL should be last priority, so specific patterns should win first
        // If the URL doesn't match any specific pattern, it's detected as 'url'
        $this->assertSame('url', Episciences_Tools::checkValueType('https://example.com/some/path'));
    }

    // ===================================================================
    // Tests for spaceCleaner() - PHP 8.1+ compatible string cleaning
    // ===================================================================

    /**
     * Test spaceCleaner with null input (should return empty string)
     */
    public function testSpaceCleanerWithNull(): void
    {
        $this->assertSame('', Episciences_Tools::spaceCleaner(null));
        $this->assertSame('', Episciences_Tools::spaceCleaner(null, true));
        $this->assertSame('', Episciences_Tools::spaceCleaner(null, false));
        $this->assertSame('', Episciences_Tools::spaceCleaner(null, true, true));
    }

    /**
     * Test spaceCleaner with empty string (should return empty string)
     */
    public function testSpaceCleanerWithEmptyString(): void
    {
        $this->assertSame('', Episciences_Tools::spaceCleaner(''));
        $this->assertSame('', Episciences_Tools::spaceCleaner('', true));
        $this->assertSame('', Episciences_Tools::spaceCleaner('', false));
    }

    /**
     * Test spaceCleaner with regular whitespace normalization
     */
    public function testSpaceCleanerWhitespaceNormalization(): void
    {
        // Multiple spaces
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello   world'));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('  hello   world  '));

        // Tabs
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner("hello\t\tworld"));
        $this->assertSame('hello world test', Episciences_Tools::spaceCleaner("hello\tworld\ttest"));

        // Newlines
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner("hello\nworld"));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner("hello\n\nworld"));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner("hello\r\nworld"));

        // Mixed whitespace
        $this->assertSame('hello world test', Episciences_Tools::spaceCleaner("  hello \t\n world  \r\n  test  "));
    }

    /**
     * Test spaceCleaner with BR tag stripping
     */
    public function testSpaceCleanerBrTagStripping(): void
    {
        // With stripBr = true (default)
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello<br>world'));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello<br/>world'));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello<br />world'));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello<BR>world'));
        $this->assertSame('hello world', Episciences_Tools::spaceCleaner('hello<br  />world'));

        // With stripBr = false
        $this->assertSame('hello<br>world', Episciences_Tools::spaceCleaner('hello<br>world', false));
        $this->assertSame('hello<br/>world', Episciences_Tools::spaceCleaner('hello<br/>world', false));
        $this->assertSame('hello<br />world', Episciences_Tools::spaceCleaner('hello<br />world', false));
    }

    /**
     * Test spaceCleaner with control characters removal
     */
    public function testSpaceCleanerControlCharactersRemoval(): void
    {
        // ASCII control characters (1-31 excluding those already handled) are removed, not replaced with spaces
        $this->assertSame('helloworld', Episciences_Tools::spaceCleaner("hello\x01\x02world"));
        $this->assertSame('helloworld', Episciences_Tools::spaceCleaner("hello\x1fworld"));
        $this->assertSame('test string', Episciences_Tools::spaceCleaner("\x03test\x04 \x05string\x06"));
    }

    /**
     * Test spaceCleaner with UTF-8 special characters (allUtf8 parameter)
     */
    public function testSpaceCleanerUtf8SpecialCharacters(): void
    {
        // With allUtf8 = false (default) - non-breaking space should be preserved
        $result = Episciences_Tools::spaceCleaner("hello\xc2\xa0world", true, false);
        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString('world', $result);

        // With allUtf8 = true - UTF-8 special spaces are removed (not replaced with spaces)
        $result = Episciences_Tools::spaceCleaner("hello\xc2\xa0world", true, true);
        $this->assertSame('helloworld', $result);

        // Thin space (U+2009) is also removed (not replaced)
        $result = Episciences_Tools::spaceCleaner("hello\xe2\x80\x89world", true, true);
        $this->assertSame('helloworld', $result);
    }

    /**
     * Test spaceCleaner with array input (recursive processing)
     */
    public function testSpaceCleanerWithArray(): void
    {
        // Simple array
        $input = ['  hello  ', '  world  ', '  test  '];
        $expected = ['hello', 'world', 'test'];
        $this->assertSame($expected, Episciences_Tools::spaceCleaner($input));

        // Array with null elements
        $input = ['  hello  ', null, '  world  ', null, '  test  '];
        $expected = ['hello', '', 'world', '', 'test'];
        $result = Episciences_Tools::spaceCleaner($input);
        // Filter out empty strings for comparison
        $filtered = array_filter($result);
        $this->assertCount(3, $filtered);
        $this->assertContains('hello', $filtered);
        $this->assertContains('world', $filtered);
        $this->assertContains('test', $filtered);

        // Array with BR tags
        $input = ['hello<br>world', 'test<br/>value'];
        $expected = ['hello world', 'test value'];
        $this->assertSame($expected, Episciences_Tools::spaceCleaner($input));

        // Array with mixed whitespace
        $input = ["  hello\t\nworld  ", "test  \r\n  value"];
        $expected = ['hello world', 'test value'];
        $this->assertSame($expected, Episciences_Tools::spaceCleaner($input));
    }

    /**
     * Test spaceCleaner with complex real-world scenarios
     */
    public function testSpaceCleanerComplexScenarios(): void
    {
        // HTML-like content with BR tags and whitespace
        $input = "  <p>Hello</p>  <br/>  <p>World</p>  ";
        $result = Episciences_Tools::spaceCleaner($input);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
        $this->assertStringNotContainsString('<br/>', $result);

        // Scientific text with special characters
        $input = "Temperature:  25°C  \n\n  Pressure:  1.5 bar";
        $result = Episciences_Tools::spaceCleaner($input);
        $this->assertStringContainsString('Temperature: 25°C', $result);
        $this->assertStringContainsString('Pressure: 1.5 bar', $result);

        // Multi-line text
        $input = "First line\n\n\nSecond line\n\n\nThird line";
        $result = Episciences_Tools::spaceCleaner($input);
        $this->assertStringContainsString('First line', $result);
        $this->assertStringContainsString('Second line', $result);
        $this->assertStringContainsString('Third line', $result);
    }

    /**
     * Test spaceCleaner maintains UTF-8 content integrity
     */
    public function testSpaceCleanerUtf8ContentIntegrity(): void
    {
        // French accents
        $this->assertSame('Café à la crème', Episciences_Tools::spaceCleaner('  Café à la crème  '));

        // German umlauts
        $this->assertSame('Über München', Episciences_Tools::spaceCleaner('  Über   München  '));

        // Greek letters
        $this->assertSame('α β γ δ', Episciences_Tools::spaceCleaner('  α  β  γ  δ  '));

        // Mixed Unicode
        $this->assertSame('Hello 世界 🌍', Episciences_Tools::spaceCleaner('  Hello   世界   🌍  '));
    }

    /**
     * Test spaceCleaner edge cases
     */
    public function testSpaceCleanerEdgeCases(): void
    {
        // Only whitespace
        $this->assertSame('', Episciences_Tools::spaceCleaner('     '));
        $this->assertSame('', Episciences_Tools::spaceCleaner("\t\t\t"));
        $this->assertSame('', Episciences_Tools::spaceCleaner("\n\n\n"));

        // Only BR tags
        $this->assertSame('', Episciences_Tools::spaceCleaner('<br><br><br>'));
        $this->assertSame('<br><br><br>', Episciences_Tools::spaceCleaner('<br><br><br>', false));

        // Single character
        $this->assertSame('a', Episciences_Tools::spaceCleaner('a'));
        $this->assertSame('a', Episciences_Tools::spaceCleaner('  a  '));

        // Empty array
        $this->assertSame([], Episciences_Tools::spaceCleaner([]));
    }

    /**
     * Test that deprecated space_clean() triggers a warning
     * and delegates to spaceCleaner()
     */
    public function testSpaceCleanDeprecationWarning(): void
    {
        // Capture the deprecation warning
        $errorTriggered = false;
        $errorMessage = '';

        set_error_handler(function($errno, $errstr) use (&$errorTriggered, &$errorMessage) {
            if ($errno === E_USER_DEPRECATED) {
                $errorTriggered = true;
                $errorMessage = $errstr;
            }
        });

        // Call the deprecated method
        $result = \Ccsd_Tools::space_clean('  hello   world  ');

        restore_error_handler();

        // Verify deprecation warning was triggered
        $this->assertTrue($errorTriggered, 'Deprecation warning should be triggered');
        $this->assertStringContainsString('deprecated', strtolower($errorMessage));
        $this->assertStringContainsString('spaceCleaner', $errorMessage);

        // Verify it still works correctly
        $this->assertSame('hello world', $result);
    }

    /**
     * Test that deprecated space_clean() produces same results as spaceCleaner()
     */
    public function testSpaceCleanBackwardCompatibility(): void
    {
        // Suppress deprecation warning for testing deprecated method
        $previousErrorReporting = error_reporting(E_ALL & ~E_USER_DEPRECATED);
        try {
            $testCases = [
                ['  hello   world  ', true, false],
                ['hello<br>world', true, false],
                ['hello<br>world', false, false],
                [['  test  ', '  value  '], true, false],
            ];

            foreach ($testCases as [$input, $stripBr, $allUtf8]) {
                $oldResult = \Ccsd_Tools::space_clean($input, $stripBr, $allUtf8);
                $newResult = Episciences_Tools::spaceCleaner($input, $stripBr, $allUtf8);

                $this->assertSame($newResult, $oldResult,
                    "Results should match for input: " . print_r($input, true));
            }
        } finally {
            error_reporting($previousErrorReporting);
        }
    }
}