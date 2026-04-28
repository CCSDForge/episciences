<?php

namespace unit\library\Episciences\Form\Validate;

use Episciences_Form_Validate_CheckDefaultRatingDeadline;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;

/**
 * Unit tests for Episciences_Form_Validate_CheckDefaultRatingDeadline
 *
 * Tests the validation of default rating deadlines, ensuring they fall
 * between minimum and maximum deadlines with support for different time units.
 */
class CheckDefaultRatingDeadlineTest extends TestCase
{
    private Episciences_Form_Validate_CheckDefaultRatingDeadline $validator;
    private Zend_Controller_Request_Http $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Episciences_Form_Validate_CheckDefaultRatingDeadline();

        // Create a real request object instead of a mock
        $this->request = new Zend_Controller_Request_Http();

        // Inject request into Zend_Controller_Front singleton
        Zend_Controller_Front::getInstance()->setRequest($this->request);
    }

    protected function tearDown(): void
    {
        // Clean up: reset the request to avoid test pollution
        // Note: We create a new empty request instead of setting null
        Zend_Controller_Front::getInstance()->setRequest(new Zend_Controller_Request_Http());
        parent::tearDown();
    }

    /**
     * Helper method to configure POST data for the request
     */
    private function setPostData(array $postData): void
    {
        foreach ($postData as $key => $value) {
            $this->request->setPost($key, $value);
        }
    }

    // =========================================================================
    // GROUP A: Format validation tests
    // =========================================================================

    /**
     * Test that a valid numeric value is accepted
     */
    public function testIsValidWithNumericValue(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(14));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that non-numeric string value is invalid
     */
    public function testIsInvalidWithNonNumericString(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('abc'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::INVALID_ESTIMATION, $messages);
    }

    /**
     * Test that empty string is invalid
     */
    public function testIsInvalidWithEmptyString(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(''));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::INVALID_ESTIMATION, $messages);
    }

    // =========================================================================
    // GROUP B: Boundary validation tests
    // =========================================================================

    /**
     * Test that default deadline equal to minimum is valid (boundary case)
     */
    public function testIsValidWhenDefaultEqualsMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(7));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that default deadline equal to maximum is valid (boundary case)
     */
    public function testIsValidWhenDefaultEqualsMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(30));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that default deadline between min and max is valid
     */
    public function testIsValidWhenDefaultBetweenMinAndMax(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(14));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that default deadline less than minimum is invalid
     */
    public function testIsInvalidWhenDefaultLessThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(5));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_LESS_THAN_MIN, $messages);
    }

    /**
     * Test that default deadline greater than maximum is invalid
     */
    public function testIsInvalidWhenDefaultGreaterThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(35));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_GREATER_THAN_MAX, $messages);
    }

    /**
     * Test valid value close to lower boundary
     */
    public function testIsValidWhenCloseToLowerBoundary(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(8));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test valid value close to upper boundary
     */
    public function testIsValidWhenCloseToUpperBoundary(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(29));
        $this->assertEmpty($this->validator->getMessages());
    }

    // =========================================================================
    // GROUP C: Different time units tests
    // =========================================================================

    /**
     * Test valid default with different time units (days, weeks, months)
     */
    public function testIsValidWithDifferentTimeUnits(): void
    {
        // Default: 14 days, Min: 1 week (7 days), Max: 1 month (~30 days)
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '1',
            'rating_deadline_min_unit' => 'weeks',
            'rating_deadline_max' => '1',
            'rating_deadline_max_unit' => 'months',
        ]);

        $this->assertTrue($this->validator->isValid(14));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test invalid default less than minimum with different units
     */
    public function testIsInvalidWhenLessThanMinimumWithDifferentUnits(): void
    {
        // Default: 1 day, Min: 1 week (7 days), Max: 1 month (~30 days)
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '1',
            'rating_deadline_min_unit' => 'weeks',
            'rating_deadline_max' => '1',
            'rating_deadline_max_unit' => 'months',
        ]);

        $this->assertFalse($this->validator->isValid(1));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_LESS_THAN_MIN, $messages);
    }

    /**
     * Test invalid default greater than maximum with different units
     */
    public function testIsInvalidWhenGreaterThanMaximumWithDifferentUnits(): void
    {
        // Default: 2 months, Min: 1 week, Max: 1 month (~30 days)
        $this->setPostData([
            'rating_deadline_unit' => 'months',
            'rating_deadline_min' => '1',
            'rating_deadline_min_unit' => 'weeks',
            'rating_deadline_max' => '1',
            'rating_deadline_max_unit' => 'months',
        ]);

        $this->assertFalse($this->validator->isValid(2));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_GREATER_THAN_MAX, $messages);
    }

    /**
     * Test valid with all units identical
     */
    public function testIsValidWithSameUnits(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'weeks',
            'rating_deadline_min' => '1',
            'rating_deadline_min_unit' => 'weeks',
            'rating_deadline_max' => '4',
            'rating_deadline_max_unit' => 'weeks',
        ]);

        $this->assertTrue($this->validator->isValid(2));
        $this->assertEmpty($this->validator->getMessages());
    }

    // =========================================================================
    // GROUP D: Error message tests
    // =========================================================================

    /**
     * Test error message for invalid estimation (non-numeric)
     */
    public function testErrorMessageForInvalidEstimation(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('invalid'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::INVALID_ESTIMATION, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::INVALID_ESTIMATION]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::INVALID_ESTIMATION]);
    }

    /**
     * Test error message when deadline is less than minimum
     */
    public function testErrorMessageForDeadlineLessThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(3));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_LESS_THAN_MIN, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_LESS_THAN_MIN]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_LESS_THAN_MIN]);
    }

    /**
     * Test error message when deadline is greater than maximum (tests the bug fix)
     */
    public function testErrorMessageForDeadlineGreaterThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(40));
        $messages = $this->validator->getMessages();

        // This tests the bug fix: should use DEADLINE_GREATER_THAN_MAX, not DEADLINE_LESS_THAN_MIN
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_GREATER_THAN_MAX, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_GREATER_THAN_MAX]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckDefaultRatingDeadline::DEADLINE_GREATER_THAN_MAX]);
    }
}
