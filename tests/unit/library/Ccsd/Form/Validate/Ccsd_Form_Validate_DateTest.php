<?php

namespace unit\library\Ccsd\Form\Validate;

use Ccsd_Form_Validate_Date;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Form_Validate_Date
 *
 * Bug fixes covered:
 * D1 - getEnd/setEnd/getEndFormat made protected (allows end-bound tests)
 * D2 - strict === comparison in isValid() (was ==)
 */
class Ccsd_Form_Validate_DateTest extends TestCase
{
    // ------------------------------------------------------------------
    // Basic format validation
    // ------------------------------------------------------------------

    public function testValidDateYmd(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertTrue($v->isValid('2024-06-15'));
    }

    public function testInvalidDateYmd(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertFalse($v->isValid('not-a-date'));
    }

    public function testInvalidMonthOutOfRange(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertFalse($v->isValid('2024-13-01'));
    }

    public function testInvalidDayOutOfRange(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertFalse($v->isValid('2024-02-30'));
    }

    public function testValidYearMonth(): void
    {
        // YYYY-MM special case: appends -01 internally
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertTrue($v->isValid('2024-06'));
    }

    public function testMultipleFormats(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => ['Y', 'Y-m', 'Y-m-d']]);
        $this->assertTrue($v->isValid('2024'));
        $this->assertTrue($v->isValid('2024-06'));
        $this->assertTrue($v->isValid('2024-06-15'));
    }

    // ------------------------------------------------------------------
    // Start bound
    // ------------------------------------------------------------------

    public function testStartBoundRejectsDateBefore(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'      => 'Y-m-d',
            'startFormat' => 'Y-m-d',
            'start'       => '2020-01-01',
        ]);
        $this->assertFalse($v->isValid('2019-12-31'));
        $messages = $v->getMessages();
        $this->assertArrayHasKey(Ccsd_Form_Validate_Date::WRONGDATE, $messages);
    }

    public function testStartBoundAcceptsDateEqual(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'      => 'Y-m-d',
            'startFormat' => 'Y-m-d',
            'start'       => '2020-01-01',
        ]);
        $this->assertTrue($v->isValid('2020-01-01'));
    }

    public function testStartBoundAcceptsDateAfter(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'      => 'Y-m-d',
            'startFormat' => 'Y-m-d',
            'start'       => '2020-01-01',
        ]);
        $this->assertTrue($v->isValid('2024-06-15'));
    }

    public function testStartRequiresStartFormat(): void
    {
        $this->expectException(\Zend_Date_Exception::class);
        new Ccsd_Form_Validate_Date([
            'format' => 'Y-m-d',
            'start'  => '2020-01-01',
            // missing startFormat
        ]);
    }

    // ------------------------------------------------------------------
    // End bound — D1 fix: getEnd/setEnd/getEndFormat now protected
    // ------------------------------------------------------------------

    public function testEndBoundRejectsDateAfter(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'    => 'Y-m-d',
            'endFormat' => 'Y-m-d',
            'end'       => '2024-12-31',
        ]);
        $this->assertFalse($v->isValid('2025-01-01'));
        $messages = $v->getMessages();
        $this->assertArrayHasKey(Ccsd_Form_Validate_Date::WRONGDATE, $messages);
    }

    public function testEndBoundAcceptsDateBefore(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'    => 'Y-m-d',
            'endFormat' => 'Y-m-d',
            'end'       => '2024-12-31',
        ]);
        $this->assertTrue($v->isValid('2024-06-15'));
    }

    public function testEndBoundAcceptsDateEqual(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'    => 'Y-m-d',
            'endFormat' => 'Y-m-d',
            'end'       => '2024-12-31',
        ]);
        $this->assertTrue($v->isValid('2024-12-31'));
    }

    public function testStartAndEndBoundValid(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'      => 'Y-m-d',
            'startFormat' => 'Y-m-d',
            'start'       => '2020-01-01',
            'endFormat'   => 'Y-m-d',
            'end'         => '2024-12-31',
        ]);
        $this->assertTrue($v->isValid('2022-06-15'));
    }

    public function testStartAndEndBoundRejectsOutside(): void
    {
        $v = new Ccsd_Form_Validate_Date([
            'format'      => 'Y-m-d',
            'startFormat' => 'Y-m-d',
            'start'       => '2020-01-01',
            'endFormat'   => 'Y-m-d',
            'end'         => '2024-12-31',
        ]);
        $this->assertFalse($v->isValid('2019-01-01'));
        $this->assertFalse($v->isValid('2025-01-01'));
    }

    // ------------------------------------------------------------------
    // D2 fix: strict === comparison
    // ------------------------------------------------------------------

    public function testStrictComparisonDoesNotAcceptLooseMatch(): void
    {
        // A format-rounded date like 2024-02-30 should fail even if PHP's DateTime rounds it
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertFalse($v->isValid('2024-02-30'), 'D2 fix: strict check must reject date that rounds differently');
    }

    // ------------------------------------------------------------------
    // Accessor methods
    // ------------------------------------------------------------------

    public function testGetStartReturnsNullByDefault(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertNull($v->getStart());
    }

    public function testGetStartFormatReturnsNullByDefault(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $this->assertNull($v->getStartFormat());
    }

    public function testSetStartFormatChangesFormat(): void
    {
        $v = new Ccsd_Form_Validate_Date(['format' => 'Y-m-d']);
        $v->setStartFormat('d/m/Y');
        $this->assertSame('d/m/Y', $v->getStartFormat());
    }
}
