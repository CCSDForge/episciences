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
}