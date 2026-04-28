<?php

namespace unit\library\Episciences\Form\Validate;

use Episciences_Form_Validate_VolumeYear;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Form_Validate_VolumeYear validator
 *
 * Tests the validation of volume years in format YYYY or YYYY-YYYY
 * with temporal boundaries (1970 to current year + 5) and chronological order validation
 */
class VolumeYearTest extends TestCase
{
    private Episciences_Form_Validate_VolumeYear $validator;
    private int $currentYear;
    private int $maxYear;
    private int $minYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Episciences_Form_Validate_VolumeYear();
        $this->currentYear = (int)date('Y');
        $this->maxYear = $this->currentYear + 5;
        $this->minYear = Episciences_Form_Validate_VolumeYear::MIN_YEAR;
    }

    // =========================================================================
    // GROUP A: Valid format tests
    // =========================================================================

    /**
     * Test that current year is valid
     */
    public function testIsValidWithCurrentYear(): void
    {
        $this->assertTrue($this->validator->isValid((string)$this->currentYear));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that a valid single year is accepted
     */
    public function testIsValidWithValidSingleYear(): void
    {
        $this->assertTrue($this->validator->isValid('2020'));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that a valid year range is accepted
     */
    public function testIsValidWithValidYearRange(): void
    {
        $this->assertTrue($this->validator->isValid('2020-2023'));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that minimum year (1970) is valid
     */
    public function testIsValidWithMinimumYear(): void
    {
        $this->assertTrue($this->validator->isValid('1970'));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that maximum year (current + 5) is valid
     */
    public function testIsValidWithMaximumYear(): void
    {
        $this->assertTrue($this->validator->isValid((string)$this->maxYear));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that a range at boundaries (1970 to current+5) is valid
     */
    public function testIsValidWithValidRangeAtBoundaries(): void
    {
        $this->assertTrue($this->validator->isValid("1970-{$this->maxYear}"));
        $this->assertEmpty($this->validator->getMessages());
    }

    // =========================================================================
    // GROUP B: Invalid format tests
    // =========================================================================

    /**
     * Test that empty string is invalid
     */
    public function testIsInvalidWithEmptyString(): void
    {
        $this->assertFalse($this->validator->isValid(''));
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that non-numeric characters are invalid
     */
    public function testIsInvalidWithNonNumericCharacters(): void
    {
        $this->assertFalse($this->validator->isValid('abc'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that three-digit year is invalid
     */
    public function testIsInvalidWithThreeDigitYear(): void
    {
        $this->assertFalse($this->validator->isValid('202'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that five-digit year is invalid
     */
    public function testIsInvalidWithFiveDigitYear(): void
    {
        $this->assertFalse($this->validator->isValid('20202'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that invalid separator (slash instead of dash) is invalid
     */
    public function testIsInvalidWithInvalidSeparator(): void
    {
        $this->assertFalse($this->validator->isValid('2020/2023'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that multiple dashes are invalid
     */
    public function testIsInvalidWithMultipleDashes(): void
    {
        $this->assertFalse($this->validator->isValid('2020-2021-2022'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that trailing dash is invalid
     */
    public function testIsInvalidWithTrailingDash(): void
    {
        $this->assertFalse($this->validator->isValid('2020-'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test that leading dash is invalid
     */
    public function testIsInvalidWithLeadingDash(): void
    {
        $this->assertFalse($this->validator->isValid('-2020'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    // =========================================================================
    // GROUP C: Invalid chronology tests
    // =========================================================================

    /**
     * Test that equal years in range (YYYY-YYYY) are invalid
     */
    public function testIsInvalidWithEqualYears(): void
    {
        $this->assertFalse($this->validator->isValid('2020-2020'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_RANGE, $messages);
    }

    /**
     * Test that reversed years are invalid
     */
    public function testIsInvalidWithReversedYears(): void
    {
        $this->assertFalse($this->validator->isValid('2023-2020'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_RANGE, $messages);
    }

    /**
     * Test that a range with year difference of one is valid (year2 > year1)
     */
    public function testIsValidWithYearDifferenceOfOne(): void
    {
        $this->assertTrue($this->validator->isValid('2020-2021'));
        $this->assertEmpty($this->validator->getMessages());
    }

    // =========================================================================
    // GROUP D: Temporal bounds tests
    // =========================================================================

    /**
     * Test that year before 1970 is invalid
     */
    public function testIsInvalidWithYearBefore1970(): void
    {
        $this->assertFalse($this->validator->isValid('1969'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
    }

    /**
     * Test that year after maximum (current + 5) is invalid
     */
    public function testIsInvalidWithYearAfterMaximum(): void
    {
        $yearAfterMax = $this->maxYear + 1;
        $this->assertFalse($this->validator->isValid((string)$yearAfterMax));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
    }

    /**
     * Test that range starting before 1970 is invalid
     */
    public function testIsInvalidWithRangeStartingBefore1970(): void
    {
        $this->assertFalse($this->validator->isValid('1969-2020'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
    }

    /**
     * Test that range ending after maximum is invalid
     */
    public function testIsInvalidWithRangeEndingAfterMaximum(): void
    {
        $yearAfterMax = $this->maxYear + 1;
        $this->assertFalse($this->validator->isValid("2020-{$yearAfterMax}"));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
    }

    /**
     * Test that range with both years outside bounds is invalid
     */
    public function testIsInvalidWithBothYearsOutsideBounds(): void
    {
        $year1 = $this->maxYear + 1;
        $year2 = $this->maxYear + 5;
        $this->assertFalse($this->validator->isValid("{$year1}-{$year2}"));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
    }

    // =========================================================================
    // GROUP E: Error message tests
    // =========================================================================

    /**
     * Test error message for invalid format
     */
    public function testGetMessagesForInvalidFormat(): void
    {
        $this->assertFalse($this->validator->isValid('invalid'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_VolumeYear::INVALID_FORMAT]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_VolumeYear::INVALID_FORMAT]);
    }

    /**
     * Test error message for invalid range
     */
    public function testGetMessagesForInvalidRange(): void
    {
        $this->assertFalse($this->validator->isValid('2023-2020'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_RANGE, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_VolumeYear::INVALID_RANGE]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_VolumeYear::INVALID_RANGE]);
    }

    /**
     * Test dynamic error message for outside bounds
     */
    public function testGetMessagesForOutsideBounds(): void
    {
        $this->assertFalse($this->validator->isValid('1969'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);
        $message = $messages[Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS];
        $this->assertIsString($message);

        // Check that the message contains the dynamic bounds
        $this->assertStringContainsString('1970', $message);
        $this->assertStringContainsString((string)$this->maxYear, $message);
    }

    /**
     * Test error message without translator (should be in French)
     */
    public function testErrorMessageWithoutTranslator(): void
    {
        // Without translator, messages should be in French
        $this->assertFalse($this->validator->isValid('1969'));
        $messages = $this->validator->getMessages();
        $message = $messages[Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS];

        // Should contain French text
        $this->assertStringContainsString('année', $message);
        $this->assertStringContainsString('comprise entre', $message);
    }

    // =========================================================================
    // GROUP F: Edge cases tests
    // =========================================================================

    /**
     * Test years with leading zeros
     * Leading zeros are valid in format (still 4 digits) but must pass temporal bounds check
     */
    public function testWithLeadingZeros(): void
    {
        // '0970' is 4 digits (format valid) but interpreted as integer 970, which is < 1970
        // So it fails the temporal bounds check
        $this->assertFalse($this->validator->isValid('0970'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::OUTSIDE_BOUNDS, $messages);

        // '1970' with no leading zero should be valid (at the boundary)
        $this->assertTrue($this->validator->isValid('1970'));

        // Years that start with 0 but have 4 digits will always be < 1970, so always invalid
        $this->assertFalse($this->validator->isValid('0999'));
    }

    /**
     * Test that whitespace is invalid
     */
    public function testIsInvalidWithWhitespace(): void
    {
        $this->assertFalse($this->validator->isValid(' 2020'));
        $this->assertFalse($this->validator->isValid('2020 '));
        $this->assertFalse($this->validator->isValid('2020 - 2023'));
    }

    /**
     * Test that null value is invalid
     */
    public function testIsInvalidWithNull(): void
    {
        $this->assertFalse($this->validator->isValid(null));
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);
    }

    /**
     * Test that boolean value is invalid
     */
    public function testIsInvalidWithBoolean(): void
    {
        $this->assertFalse($this->validator->isValid(true));
        $this->assertFalse($this->validator->isValid(false));
    }

    /**
     * Test that array value is invalid
     * Note: Arrays are cast to string "Array" which is then rejected by the format validator
     */
    public function testIsInvalidWithArray(): void
    {
        // Array is cast to string "Array", which fails format validation
        $this->assertFalse($this->validator->isValid(['2020']));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_VolumeYear::INVALID_FORMAT, $messages);
    }

    /**
     * Test multiple consecutive validations
     */
    public function testMultipleConsecutiveValidations(): void
    {
        // First validation: valid
        $this->assertTrue($this->validator->isValid('2020'));
        $this->assertEmpty($this->validator->getMessages());

        // Second validation: invalid
        $this->assertFalse($this->validator->isValid('invalid'));
        $messages = $this->validator->getMessages();
        $this->assertNotEmpty($messages);

        // Third validation: valid again (ensure state is cleared)
        $this->assertTrue($this->validator->isValid('2021'));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test year at exact boundary: 1970
     */
    public function testYearAtLowerBoundary(): void
    {
        $this->assertTrue($this->validator->isValid('1970'));
        $this->assertFalse($this->validator->isValid('1969'));
    }

    /**
     * Test year at exact upper boundary: current + 5
     */
    public function testYearAtUpperBoundary(): void
    {
        $this->assertTrue($this->validator->isValid((string)$this->maxYear));
        $yearAfterMax = $this->maxYear + 1;
        $this->assertFalse($this->validator->isValid((string)$yearAfterMax));
    }
}
