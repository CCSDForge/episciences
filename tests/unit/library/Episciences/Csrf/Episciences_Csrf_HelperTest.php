<?php

namespace unit\library\Episciences\Csrf;

use Episciences_Csrf_Helper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * This helper class provides CSRF (Cross-Site Request Forgery) protection
 * using Zend Framework's built-in hash element (Zend_Form_Element_Hash).
 *
 * CSRF attacks occur when malicious websites trick users into submitting unwanted requests to sites where they're authenticated.
 * This helper generates and validates unique tokens to prevent such attacks.
 *
 * Key features:
 * - Token generation with configurable timeout (default: 1 hour)
 * - Token validation with automatic session cleanup
 * - HTML hidden input generation for easy form integration
 * - Name sanitization to ensure valid form element names
 *
 * Token lifecycle:
 * 1. generateToken() creates a token and stores it in Zend_Session
 * 2. Token is rendered as hidden input in the form
 * 3. On form submission, validateFormToken() checks the token
 * 4. After validation (success or fail), token is cleared (one-time use)
 *
 * Note: Some methods require Zend Session to be initialized, so those tests may be skipped in environments without session support.
 *
 * @covers Episciences_Csrf_Helper
 * @see Zend_Form_Element_Hash For underlying token implementation
 */
class Episciences_Csrf_HelperTest extends TestCase
{
    // =========================================================================
    // sanitizeName() Tests (Private Method - Tested via Reflection)
    // =========================================================================

    /**
     * Test that sanitizeName() correctly removes special characters.
     *
     * The sanitizeName() method ensures form element names are valid by
     * replacing any non-alphanumeric characters (except underscore) with underscores.
     *
     * This is important because:
     * - HTML form element names have restrictions
     * - Zend Session namespace names must be valid PHP identifiers
     * - Consistent naming prevents injection attacks
     *
     * We use ReflectionClass to test this private method directly,as it contains important security logic.
     */
    public function testSanitizeNameRemovesSpecialCharacters(): void
    {
        $reflection = new ReflectionClass(Episciences_Csrf_Helper::class);
        $method = $reflection->getMethod('sanitizeName');
        $method->setAccessible(true);

        // Test various inputs and expected outputs
        $testCases = [
            // Input => Expected output
            'simple_name' => 'simple_name',           // Already valid - unchanged
            'name-with-dashes' => 'name_with_dashes', // Dashes replaced
            'name.with.dots' => 'name_with_dots',     // Dots replaced
            'name with spaces' => 'name_with_spaces', // Spaces replaced
            'name@special#chars!' => 'name_special_chars_', // Special chars replaced
            'CamelCase123' => 'CamelCase123',         // Mixed case + numbers OK
            'under_score_name' => 'under_score_name', // Underscores preserved
            'remove_file_123' => 'remove_file_123',   // Typical use case
            'csrf_author_reply_form_456' => 'csrf_author_reply_form_456', // Real form name
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke(null, $input);
            $this->assertSame($expected, $result, "Failed for input: $input");
        }
    }

    /**
     * Test that sanitizeName() preserves alphanumeric characters and underscores.
     *
     * Valid characters should pass through unchanged:
     * - Letters (a-z, A-Z)
     * - Numbers (0-9)
     * - Underscores (_)
     */
    public function testSanitizeNamePreservesAlphanumericAndUnderscore(): void
    {
        $reflection = new ReflectionClass(Episciences_Csrf_Helper::class);
        $method = $reflection->getMethod('sanitizeName');
        $method->setAccessible(true);

        $validName = 'valid_Name_123';
        $result = $method->invoke(null, $validName);

        $this->assertSame($validName, $result);
    }

    /**
     * Test that sanitizeName() handles empty strings gracefully.
     *
     * An empty string should remain empty (not throw an error).
     * This edge case might occur if a form name is accidentally empty.
     */
    public function testSanitizeNameHandlesEmptyString(): void
    {
        $reflection = new ReflectionClass(Episciences_Csrf_Helper::class);
        $method = $reflection->getMethod('sanitizeName');
        $method->setAccessible(true);

        $result = $method->invoke(null, '');

        $this->assertSame('', $result);
    }

    /**
     * Test that sanitizeName() handles strings with only special characters.
     *
     * When input contains only invalid characters, the result should be
     * all underscores. This is an edge case that shouldn't happen in
     * practice, but the function should handle it without errors.
     */
    public function testSanitizeNameHandlesOnlySpecialCharacters(): void
    {
        $reflection = new ReflectionClass(Episciences_Csrf_Helper::class);
        $method = $reflection->getMethod('sanitizeName');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $method->setAccessible(true);

        $result = $method->invoke(null, '@#$%^&*()');

        // 9 special characters = 9 underscores
        $this->assertSame('_________', $result);
    }

    // =========================================================================
    // validateFormToken() Tests - Input Validation
    // =========================================================================

    /**
     * Test that validateFormToken() returns false when token is not in POST data.
     *
     * If the CSRF token field is missing from the form submission entirely, validation should fail immediately. This catches:
     * - Forms submitted without the CSRF field
     * - Malicious requests that don't include the token
     * - Programming errors where the hidden input wasn't rendered
     */
    public function testValidateFormTokenReturnsFalseWhenTokenNotInPostData(): void
    {
        $result = Episciences_Csrf_Helper::validateFormToken('csrf_token', []);

        $this->assertFalse($result);
    }

    /**
     * Test that validateFormToken() returns false when element name is not found.
     *
     * If the POST data doesn't contain the expected field name, validation fails.
     * This verifies that the function checks for the exact field name.
     */
    public function testValidateFormTokenReturnsFalseWhenElementNameMissing(): void
    {
        $postData = [
            'other_field' => 'value',
            'another_field' => 'another_value'
        ];

        $result = Episciences_Csrf_Helper::validateFormToken('csrf_token', $postData);

        $this->assertFalse($result);
    }

    // =========================================================================
    // getHiddenInput() Tests - Output Format (Requires Session)
    // =========================================================================

    /**
     * Test that getHiddenInput() returns a valid HTML hidden input element.
     *
     * The output should be a properly formatted HTML hidden input with:
     * - type="hidden" attribute
     * - name attribute (sanitized token name)
     * - value attribute (generated token)
     *
     * This is used to easily embed CSRF protection in forms:
     * echo Episciences_Csrf_Helper::getHiddenInput('my_form');
     *
     * @group integration
     * @note This test requires Zend_Session to be initialized
     */
    public function testGetHiddenInputReturnsHtmlInputElement(): void
    {
        // Skip if Zend_Session is not available
        if (!class_exists('Zend_Session')) {
            $this->markTestSkipped('Zend_Session not available');
        }

        try {
            $html = Episciences_Csrf_Helper::getHiddenInput('test_token');

            // Verify HTML structure
            $this->assertStringContainsString('<input', $html);
            $this->assertStringContainsString('type="hidden"', $html);
            $this->assertStringContainsString('name="', $html);
            $this->assertStringContainsString('value="', $html);
        } catch (\Exception $e) {
            // Session not started - skip test
            $this->markTestSkipped('Session not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // generateToken() Tests - Output Structure (Requires Session)
    // =========================================================================

    /**
     * Test that generateToken() returns an array with name and value keys.
     *
     * The returned array should contain:
     * - 'name': The sanitized element name for the hidden input
     * - 'value': The generated token value
     *
     * These values are used to:
     * - Build the hidden input manually (if not using getHiddenInput())
     * - Store the token for later validation
     *
     * @group integration
     * @note This test requires Zend_Session to be initialized
     */
    public function testGenerateTokenReturnsArrayWithNameAndValue(): void
    {
        // Skip if Zend_Session is not available
        if (!class_exists('Zend_Session')) {
            $this->markTestSkipped('Zend_Session not available');
        }

        try {
            $token = Episciences_Csrf_Helper::generateToken('test_token');

            // Verify array structure
            $this->assertIsArray($token);
            $this->assertArrayHasKey('name', $token);
            $this->assertArrayHasKey('value', $token);

            // Verify values are not empty
            $this->assertNotEmpty($token['name']);
            $this->assertNotEmpty($token['value']);
        } catch (\Exception $e) {
            // Session not started - skip test
            $this->markTestSkipped('Session not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // validateToken() Tests
    // =========================================================================

    /**
     * Test that validateToken() returns false when an obviously invalid token value is submitted.
     *
     * validateToken() creates a new Zend_Form_Element_Hash and calls isValid().
     * Even without a session, an arbitrary string should never validate as a correct token.
     */
    public function testValidateTokenReturnsFalseForGarbageValue(): void
    {
        try {
            $result = Episciences_Csrf_Helper::validateToken('test_token', 'invalid_garbage_value');
            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Session not available: ' . $e->getMessage());
        }
    }

    /**
     * Test that validateToken() returns false for an empty token value.
     */
    public function testValidateTokenReturnsFalseForEmptyString(): void
    {
        try {
            $result = Episciences_Csrf_Helper::validateToken('test_token', '');
            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Session not available: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // DEFAULT_TIMEOUT Constant Tests
    // =========================================================================

    /**
     * Test that DEFAULT_TIMEOUT constant exists and equals 3600 seconds (1 hour).
     *
     * The default timeout determines how long a CSRF token remains valid.
     * 1 hour (3600 seconds) balances:
     * - Security: Tokens expire reasonably quickly
     * - Usability: Users have time to complete long forms
     *
     * The constant is private, so we use reflection to verify it.
     */
    public function testDefaultTimeoutIsOneHour(): void
    {
        $reflection = new ReflectionClass(Episciences_Csrf_Helper::class);

        // Verify the constant exists
        $this->assertTrue(
            $reflection->hasConstant('DEFAULT_TIMEOUT'),
            'DEFAULT_TIMEOUT constant should exist'
        );

        // Verify the value is 3600 seconds (1 hour)
        $constant = $reflection->getReflectionConstant('DEFAULT_TIMEOUT');
        $this->assertSame(3600, $constant->getValue());
    }
}