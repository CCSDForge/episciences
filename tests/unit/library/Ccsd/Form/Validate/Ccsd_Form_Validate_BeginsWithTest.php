<?php

namespace unit\library\Ccsd\Form\Validate;

use Ccsd_Form_Validate_BeginsWith;
use Ccsd_Form_Validate_NotBeginsWith;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Form_Validate_BeginsWith and Ccsd_Form_Validate_NotBeginsWith.
 *
 * Exercises all three adapters: String, Array, Multi.
 */
class Ccsd_Form_Validate_BeginsWithTest extends TestCase
{
    // ------------------------------------------------------------------
    // String adapter
    // ------------------------------------------------------------------

    private function makeStringValidator(string $start, bool $all = true): Ccsd_Form_Validate_BeginsWith
    {
        return new Ccsd_Form_Validate_BeginsWith([
            'adapter' => 'string',
            'start'   => $start,
            'all'     => $all,
        ]);
    }

    public function testStringAdapterValid(): void
    {
        $v = $this->makeStringValidator('http');
        $this->assertTrue($v->isValid('https://example.com'));
    }

    public function testStringAdapterInvalid(): void
    {
        $v = $this->makeStringValidator('http');
        $this->assertFalse($v->isValid('ftp://example.com'));
        $messages = $v->getMessages();
        $this->assertNotEmpty($messages);
    }

    public function testStringAdapterNonStringValueFails(): void
    {
        $v = $this->makeStringValidator('http');
        $this->assertFalse($v->isValid(42));
        $this->assertArrayHasKey(Ccsd_Form_Validate_BeginsWith::INVALID, $v->getMessages());
    }

    public function testStringAdapterEmptyStringValid(): void
    {
        // Empty string has no prefix requirement — starts with "" trivially
        $v = $this->makeStringValidator('');
        $this->assertTrue($v->isValid('anything'));
    }

    // ------------------------------------------------------------------
    // Array adapter (all=true: every element must start with prefix)
    // ------------------------------------------------------------------

    private function makeArrayValidator(string $start, bool $all = true): Ccsd_Form_Validate_BeginsWith
    {
        return new Ccsd_Form_Validate_BeginsWith([
            'adapter' => 'array',
            'start'   => $start,
            'all'     => $all,
        ]);
    }

    public function testArrayAdapterAllValidWithAllTrue(): void
    {
        $v = $this->makeArrayValidator('10.');
        $this->assertTrue($v->isValid(['10.1000/abc', '10.2000/def']));
    }

    public function testArrayAdapterAllInvalidWithAllTrue(): void
    {
        $v = $this->makeArrayValidator('10.');
        $this->assertFalse($v->isValid(['10.1000/abc', 'notadoi']));
        $this->assertArrayHasKey(Ccsd_Form_Validate_BeginsWith::INVALID_ALL, $v->getMessages());
    }

    public function testArrayAdapterOneOfWithAllFalse(): void
    {
        // all=false: at least one element must start with prefix
        $v = $this->makeArrayValidator('10.', false);
        $this->assertTrue($v->isValid(['notadoi', '10.1000/abc']));
    }

    public function testArrayAdapterNoneMatchAllFalse(): void
    {
        // Known behavior: isValid() initializes $is_starting=true and uses OR, so it
        // always returns true regardless of whether any element matches when all=false.
        // This means the INVALID_ONE error can never be triggered for a non-empty array.
        $v = $this->makeArrayValidator('10.', false);
        $this->assertTrue($v->isValid(['foo', 'bar']), 'all=false: $is_starting=true with OR always stays true (known logic quirk)');
    }

    public function testArrayAdapterNonArrayValueFails(): void
    {
        $v = $this->makeArrayValidator('http');
        $this->assertFalse($v->isValid('not-an-array'));
        $this->assertArrayHasKey(Ccsd_Form_Validate_BeginsWith::INVALID, $v->getMessages());
    }

    // ------------------------------------------------------------------
    // Multi adapter (flattens nested arrays)
    // ------------------------------------------------------------------

    private function makeMultiValidator(string $start, bool $all = true): Ccsd_Form_Validate_BeginsWith
    {
        return new Ccsd_Form_Validate_BeginsWith([
            'adapter' => 'multi',
            'start'   => $start,
            'all'     => $all,
        ]);
    }

    public function testMultiAdapterFlatArrayAllValid(): void
    {
        $v = $this->makeMultiValidator('10.');
        $this->assertTrue($v->isValid(['10.1/a', '10.2/b']));
    }

    public function testMultiAdapterNestedArrayAllValid(): void
    {
        $v = $this->makeMultiValidator('10.');
        $this->assertTrue($v->isValid([['10.1/a', '10.2/b'], ['10.3/c']]));
    }

    public function testMultiAdapterNestedArrayOneInvalid(): void
    {
        $v = $this->makeMultiValidator('10.');
        $this->assertFalse($v->isValid([['10.1/a'], ['nope']]));
    }

    public function testMultiAdapterNonArrayFails(): void
    {
        $v = $this->makeMultiValidator('10.');
        $this->assertFalse($v->isValid('not-an-array'));
    }

    // ------------------------------------------------------------------
    // Missing adapter option throws exception
    // ------------------------------------------------------------------

    public function testMissingAdapterThrows(): void
    {
        $this->expectException(\Zend_Validate_Exception::class);
        new Ccsd_Form_Validate_BeginsWith(['start' => 'http']);
    }

    // ------------------------------------------------------------------
    // NotBeginsWith validator
    // ------------------------------------------------------------------

    private function makeNotBeginsWithValidator(string|array $start): Ccsd_Form_Validate_NotBeginsWith
    {
        return new Ccsd_Form_Validate_NotBeginsWith([
            'adapter' => 'string',
            'start'   => $start,
        ]);
    }

    public function testNotBeginsWithValidWhenNotInForbiddenList(): void
    {
        // NotBeginsWith uses in_array() exact match, not strpos prefix check.
        $v = $this->makeNotBeginsWithValidator('ftp://');
        $this->assertTrue($v->isValid('https://example.com'));
    }

    public function testNotBeginsWithInvalidWhenExactMatch(): void
    {
        // NotBeginsWith rejects when the value is exactly one of the forbidden strings.
        // Note: it does NOT check prefix — 'ftp://example.com' would NOT be rejected
        // because in_array('ftp://example.com', ['ftp://']) is false.
        $v = $this->makeNotBeginsWithValidator('ftp://');
        $this->assertFalse($v->isValid('ftp://'));
        $this->assertArrayHasKey(Ccsd_Form_Validate_NotBeginsWith::INVALID_DATA, $v->getMessages());
    }

    public function testNotBeginsWithPrefixOnlyStringDoesNotBlock(): void
    {
        // Documented behavior: 'ftp://something' passes because in_array is exact match.
        $v = $this->makeNotBeginsWithValidator('ftp://');
        $this->assertTrue($v->isValid('ftp://example.com'), 'NotBeginsWith uses exact match, not prefix check');
    }

    public function testNotBeginsWithStringAdapterNonStringFails(): void
    {
        $v = $this->makeNotBeginsWithValidator('http');
        $this->assertFalse($v->isValid(123));
    }
}
