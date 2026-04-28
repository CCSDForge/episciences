<?php

namespace unit\library\Episciences\Form\Validate;

use Episciences_Form_Validate_CheckMinimumDeadlineDelay;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;

/**
 * Unit tests for Episciences_Form_Validate_CheckMinimumDeadlineDelay
 *
 * Tests the validation of minimum rating deadlines, ensuring they are
 * less than or equal to maximum deadlines.
 */
class CheckMinimumDeadlineDelayTest extends TestCase
{
    private Episciences_Form_Validate_CheckMinimumDeadlineDelay $validator;
    private Zend_Controller_Request_Http $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Episciences_Form_Validate_CheckMinimumDeadlineDelay();

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
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(7));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that non-numeric string value is invalid
     */
    public function testIsInvalidWithNonNumericString(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('abc'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::INVALID_ESTIMATION, $messages);
    }

    /**
     * Test that empty string is invalid
     */
    public function testIsInvalidWithEmptyString(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(''));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::INVALID_ESTIMATION, $messages);
    }

    // =========================================================================
    // GROUP B: Consistency validation tests
    // =========================================================================

    /**
     * Test that minimum less than maximum is valid
     */
    public function testIsValidWhenMinimumLessThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(7));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that minimum equal to maximum is valid (boundary case)
     */
    public function testIsValidWhenMinimumEqualsMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(30));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that minimum greater than maximum is invalid
     */
    public function testIsInvalidWhenMinimumGreaterThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(35));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX, $messages);
    }

    /**
     * Test valid with minimum much smaller than maximum
     */
    public function testIsValidWhenMinimumMuchSmallerThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '90',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(1));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test invalid with minimum slightly greater than maximum
     */
    public function testIsInvalidWhenMinimumSlightlyGreaterThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(31));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX, $messages);
    }

    // =========================================================================
    // GROUP C: Different time units tests
    // =========================================================================

    /**
     * Test valid with different time units (Min: 1 week, Max: 30 days)
     */
    public function testIsValidWithDifferentUnits(): void
    {
        // Min: 1 week (7 days), Max: 30 days -> 7 < 30, valid
        $this->setPostData([
            'rating_deadline_min_unit' => 'weeks',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(1));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test invalid with different time units (Min: 30 days, Max: 1 week)
     */
    public function testIsInvalidWithDifferentUnits(): void
    {
        // Min: 30 days, Max: 1 week (7 days) -> 30 > 7, invalid
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '1',
            'rating_deadline_max_unit' => 'weeks',
        ]);

        $this->assertFalse($this->validator->isValid(30));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX, $messages);
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
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('invalid'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::INVALID_ESTIMATION, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckMinimumDeadlineDelay::INVALID_ESTIMATION]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckMinimumDeadlineDelay::INVALID_ESTIMATION]);
    }

    /**
     * Test error message when minimum is greater than maximum
     */
    public function testErrorMessageForMinimumGreaterThanMaximum(): void
    {
        $this->setPostData([
            'rating_deadline_min_unit' => 'days',
            'rating_deadline_max' => '30',
            'rating_deadline_max_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(40));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckMinimumDeadlineDelay::DEADLINE_GREATER_THAN_MAX]);
    }
}
