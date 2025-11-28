<?php

namespace unit\library\Episciences\Form\Validate;

use Episciences_Form_Validate_CheckMaximumDeadlineDelay;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;

/**
 * Unit tests for Episciences_Form_Validate_CheckMaximumDeadlineDelay
 *
 * Tests the validation of maximum rating deadlines, ensuring they are
 * greater than or equal to minimum deadlines.
 */
class CheckMaximumDeadlineDelayTest extends TestCase
{
    private Episciences_Form_Validate_CheckMaximumDeadlineDelay $validator;
    private Zend_Controller_Request_Http $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Episciences_Form_Validate_CheckMaximumDeadlineDelay();

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
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
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
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('abc'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::INVALID_ESTIMATION, $messages);
    }

    /**
     * Test that empty string is invalid
     */
    public function testIsInvalidWithEmptyString(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(''));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::INVALID_ESTIMATION, $messages);
    }

    // =========================================================================
    // GROUP B: Consistency validation tests
    // =========================================================================

    /**
     * Test that maximum greater than minimum is valid
     */
    public function testIsValidWhenMaximumGreaterThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(30));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that maximum equal to minimum is valid (boundary case)
     */
    public function testIsValidWhenMaximumEqualsMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(7));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test that maximum less than minimum is invalid
     */
    public function testIsInvalidWhenMaximumLessThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(5));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN, $messages);
    }

    /**
     * Test valid with maximum much greater than minimum
     */
    public function testIsValidWhenMaximumMuchGreaterThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertTrue($this->validator->isValid(90));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test invalid with maximum slightly less than minimum
     */
    public function testIsInvalidWhenMaximumSlightlyLessThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(6));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN, $messages);
    }

    // =========================================================================
    // GROUP C: Different time units tests
    // =========================================================================

    /**
     * Test valid with different time units (Max: 30 days, Min: 1 week)
     */
    public function testIsValidWithDifferentUnits(): void
    {
        // Max: 30 days, Min: 1 week (7 days) -> 30 > 7, valid
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '1',
            'rating_deadline_min_unit' => 'weeks',
        ]);

        $this->assertTrue($this->validator->isValid(30));
        $this->assertEmpty($this->validator->getMessages());
    }

    /**
     * Test invalid with different time units (Max: 1 week, Min: 30 days)
     */
    public function testIsInvalidWithDifferentUnits(): void
    {
        // Max: 1 week (7 days), Min: 30 days -> 7 < 30, invalid
        $this->setPostData([
            'rating_deadline_max_unit' => 'weeks',
            'rating_deadline_min' => '30',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(1));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN, $messages);
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
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid('invalid'));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::INVALID_ESTIMATION, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckMaximumDeadlineDelay::INVALID_ESTIMATION]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckMaximumDeadlineDelay::INVALID_ESTIMATION]);
    }

    /**
     * Test error message when maximum is less than minimum
     */
    public function testErrorMessageForMaximumLessThanMinimum(): void
    {
        $this->setPostData([
            'rating_deadline_max_unit' => 'days',
            'rating_deadline_min' => '7',
            'rating_deadline_min_unit' => 'days',
        ]);

        $this->assertFalse($this->validator->isValid(3));
        $messages = $this->validator->getMessages();
        $this->assertArrayHasKey(Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN, $messages);
        $this->assertIsString($messages[Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN]);
        $this->assertNotEmpty($messages[Episciences_Form_Validate_CheckMaximumDeadlineDelay::DEADLINE_LESS_THAN_MIN]);
    }
}
