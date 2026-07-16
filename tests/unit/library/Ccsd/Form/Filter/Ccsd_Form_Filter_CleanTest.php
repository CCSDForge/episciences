<?php
declare(strict_types=1);

namespace unit\library\Ccsd\Form\Filter;

use Ccsd_Form_Filter_Clean;
use PHPUnit\Framework\TestCase;

class Ccsd_Form_Filter_CleanTest extends TestCase
{
    private Ccsd_Form_Filter_Clean $filter;

    protected function setUp(): void
    {
        $this->filter = new Ccsd_Form_Filter_Clean();
    }

    /**
     * Test cleaning simple string values with control characters.
     */
    public function testFilterStringWithControlChars(): void
    {
        // \x00 (Null byte), \x08 (Backspace), \x1b (Escape) should be stripped
        $input = "Hello\x00 World\x08!\x1b";
        $expected = "Hello World!";
        $this->assertSame($expected, $this->filter->filter($input));
    }

    /**
     * Test that normal characters and newlines are preserved.
     */
    public function testFilterStringPreservesAllowedCharacters(): void
    {
        // \n (\x0a) and \r (\x0d) are preserved when stripCtrlChars's $allCtrl is false.
        $input = "Line 1\nLine 2\r\nSpecial: àéïôûç 123 !@#";
        $this->assertSame($input, $this->filter->filter($input));
    }

    /**
     * Test filtering a flat array of values.
     */
    public function testFilterFlatArray(): void
    {
        $input = [
            "name\x00" => "John\x08 Doe",
            "email"   => "john.doe\x1b@example.com",
            "age"     => 42,
        ];
        $expected = [
            "name\x00" => "John Doe",
            "email"   => "john.doe@example.com",
            "age"     => 42,
        ];
        $this->assertSame($expected, $this->filter->filter($input));
    }

    /**
     * Test filtering a nested array (recursive).
     */
    public function testFilterNestedArray(): void
    {
        $input = [
            "user" => [
                "name" => "Alice\x00",
                "roles" => ["admin\x08", "user"],
            ],
            "metadata" => [
                "tags" => [
                    ["tag1\x1b", "tag2"],
                ]
            ]
        ];
        $expected = [
            "user" => [
                "name" => "Alice",
                "roles" => ["admin", "user"],
            ],
            "metadata" => [
                "tags" => [
                    ["tag1", "tag2"],
                ]
            ]
        ];
        $this->assertSame($expected, $this->filter->filter($input));
    }

    /**
     * Test that non-string and non-array types are preserved unchanged.
     */
    public function testFilterPreservesOtherTypes(): void
    {
        $this->assertSame(42, $this->filter->filter(42));
        $this->assertSame(3.14, $this->filter->filter(3.14));
        $this->assertTrue($this->filter->filter(true));
        $this->assertNull($this->filter->filter(null));

        $obj = new \stdClass();
        $obj->prop = "value\x00";
        $this->assertSame($obj, $this->filter->filter($obj));
    }
}
