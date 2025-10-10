<?php

namespace unit\library\Episciences;

use Episciences_OpenAireResearchGraphTools;
use Episciences_Tools;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for Episciences_OpenAireResearchGraphTools
 *
 * Tests focus on non-database helper methods and validation logic
 */
class OpenAireResearchGraphToolsTest extends TestCase
{
    /**
     * Get a private/protected method for testing using reflection
     *
     * @param string $methodName
     * @return ReflectionMethod
     * @throws \ReflectionException
     */
    private function getPrivateMethod(string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass(Episciences_OpenAireResearchGraphTools::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    // ============================================================================
    // Tests for validateDoi()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithValidDoi_ReturnsCleanedDoi(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        $validDoi = '10.1234/test-doi';
        $result = $method->invoke(null, $validDoi);

        $this->assertSame($validDoi, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithWhitespace_ReturnsTrimmedDoi(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        $result = $method->invoke(null, '  10.1234/test-doi  ');

        $this->assertSame('10.1234/test-doi', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithEmptyString_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DOI cannot be empty');

        $method->invoke(null, '');
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithInvalidFormat_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DOI format');

        $method->invoke(null, 'not-a-doi');
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithExcessiveLength_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        // Create a DOI that's longer than MAX_DOI_LENGTH (200 characters)
        $longDoi = '10.1234/' . str_repeat('a', 200);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DOI exceeds maximum length');

        $method->invoke(null, $longDoi);
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoi_WithComplexValidDoi_ReturnsCleanedDoi(): void
    {
        $method = $this->getPrivateMethod('validateDoi');

        // Test with complex but valid DOI containing special characters
        $validDoi = '10.1234/test-DOI.with_special(chars)';
        $result = $method->invoke(null, $validDoi);

        $this->assertSame($validDoi, $result);
    }

    // ============================================================================
    // Tests for generateCacheKey()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testGenerateCacheKey_WithDoi_ReturnsMd5Hash(): void
    {
        $method = $this->getPrivateMethod('generateCacheKey');

        $doi = '10.1234/test-doi';
        $result = $method->invoke(null, $doi, '.json');

        $expectedHash = md5($doi) . '.json';
        $this->assertSame($expectedHash, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGenerateCacheKey_WithDifferentSuffix_ReturnsCorrectKey(): void
    {
        $method = $this->getPrivateMethod('generateCacheKey');

        $doi = '10.1234/test-doi';
        $result = $method->invoke(null, $doi, '_creator.json');

        $expectedHash = md5($doi) . '_creator.json';
        $this->assertSame($expectedHash, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGenerateCacheKey_WithSameDoi_ReturnsSameKey(): void
    {
        $method = $this->getPrivateMethod('generateCacheKey');

        $doi = '10.1234/test-doi';
        $result1 = $method->invoke(null, $doi, '.json');
        $result2 = $method->invoke(null, $doi, '.json');

        $this->assertSame($result1, $result2);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGenerateCacheKey_WithDifferentDois_ReturnsDifferentKeys(): void
    {
        $method = $this->getPrivateMethod('generateCacheKey');

        $doi1 = '10.1234/test-doi-1';
        $doi2 = '10.1234/test-doi-2';

        $result1 = $method->invoke(null, $doi1, '.json');
        $result2 = $method->invoke(null, $doi2, '.json');

        $this->assertNotSame($result1, $result2);
    }

    // ============================================================================
    // Tests for validateOrcid()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testValidateOrcid_WithValidOrcid_ReturnsTrue(): void
    {
        $method = $this->getPrivateMethod('validateOrcid');

        $validOrcids = [
            '0000-0002-1825-0097',
            '0000-0001-5000-0007',
            '0000-0002-9079-593X', // X is valid checksum
            '0000-0003-1234-5678',
        ];

        foreach ($validOrcids as $orcid) {
            $result = $method->invoke(null, $orcid);
            $this->assertTrue($result, "ORCID $orcid should be valid");
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateOrcid_WithInvalidOrcid_ReturnsFalse(): void
    {
        $method = $this->getPrivateMethod('validateOrcid');

        $invalidOrcids = [
            '0000-0002-1825',        // Too short
            '0000-0002-1825-00971',  // Too long
            '0000-00021-1825-0097',  // Wrong format
            'not-an-orcid',          // Completely invalid
            '',                      // Empty
            '0000-0002-1825-009Y',   // Invalid checksum (Y not allowed)
        ];

        foreach ($invalidOrcids as $orcid) {
            $result = $method->invoke(null, $orcid);
            $this->assertFalse($result, "ORCID '$orcid' should be invalid");
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function testValidateOrcid_WithWhitespace_HandlesTrimming(): void
    {
        $method = $this->getPrivateMethod('validateOrcid');

        $result = $method->invoke(null, '  0000-0002-1825-0097  ');

        $this->assertTrue($result);
    }

    // ============================================================================
    // Tests for getCacheDirectory()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testGetCacheDirectory_ReturnsCorrectPath(): void
    {
        $method = $this->getPrivateMethod('getCacheDirectory');

        $result = $method->invoke(null);

        $expectedPath = dirname(APPLICATION_PATH) . '/cache/';
        $this->assertSame($expectedPath, $result);
    }

    // ============================================================================
    // Tests for encodeJson() and decodeJson()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testEncodeJson_WithArray_ReturnsJsonString(): void
    {
        $method = $this->getPrivateMethod('encodeJson');

        $data = ['key' => 'value', 'number' => 123];
        $result = $method->invoke(null, $data);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertSame($data, $decoded);
    }

    /**
     * @throws \ReflectionException
     */
    public function testEncodeJson_WithUnicodeCharacters_PreservesUnicode(): void
    {
        $method = $this->getPrivateMethod('encodeJson');

        $data = ['name' => 'José García', 'city' => 'München'];
        $result = $method->invoke(null, $data);

        // Should contain unescaped Unicode characters
        $this->assertStringContainsString('José', $result);
        $this->assertStringContainsString('München', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testDecodeJson_WithValidJson_ReturnsArray(): void
    {
        $method = $this->getPrivateMethod('decodeJson');

        $json = '{"key":"value","number":123}';
        $result = $method->invoke(null, $json);

        $expected = ['key' => 'value', 'number' => 123];
        $this->assertSame($expected, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testDecodeJson_WithInvalidJson_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('decodeJson');

        $this->expectException(JsonException::class);

        $method->invoke(null, 'not valid json{');
    }

    /**
     * @throws \ReflectionException
     */
    public function testDecodeJson_WithNonStringInput_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('decodeJson');

        // Episciences_Tools::isJson() returns false for non-strings, which causes JsonException
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Invalid JSON data');

        $method->invoke(null, ['array' => 'not string']); // Non-string input
    }

    // ============================================================================
    // Tests for buildApiUrl()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testBuildApiUrl_WithValidDoi_ReturnsCorrectUrl(): void
    {
        $method = $this->getPrivateMethod('buildApiUrl');

        $doi = '10.1234/test-doi';
        $result = $method->invoke(null, $doi);

        $this->assertStringStartsWith('https://api.openaire.eu/search/publications', $result);
        $this->assertStringContainsString(urlencode($doi), $result);
        $this->assertStringContainsString('format=json', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testBuildApiUrl_WithSpecialCharacters_EncodesCorrectly(): void
    {
        $method = $this->getPrivateMethod('buildApiUrl');

        $doi = '10.1234/test-doi(with)special/chars';
        $result = $method->invoke(null, $doi);

        // Special characters should be URL-encoded
        $this->assertStringContainsString(urlencode($doi), $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testBuildApiUrl_WithInvalidDoi_ThrowsException(): void
    {
        $method = $this->getPrivateMethod('buildApiUrl');

        $this->expectException(InvalidArgumentException::class);

        $method->invoke(null, 'invalid-doi');
    }

    // ============================================================================
    // Tests for createEmptyResultMarker() and isEmptyResult()
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testCreateEmptyResultMarker_ReturnsJsonString(): void
    {
        $method = $this->getPrivateMethod('createEmptyResultMarker');

        $result = $method->invoke(null);

        $this->assertJson($result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testIsEmptyResult_WithEmptyMarker_ReturnsTrue(): void
    {
        $method = $this->getPrivateMethod('isEmptyResult');

        $result1 = $method->invoke(null, [""]);
        $result2 = $method->invoke(null, [""]);  // Alternative format

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    /**
     * @throws \ReflectionException
     */
    public function testIsEmptyResult_WithValidData_ReturnsFalse(): void
    {
        $method = $this->getPrivateMethod('isEmptyResult');

        $validData = [
            ['key' => 'value'],
            ['author' => 'John Doe'],
            ['orcid' => '0000-0002-1825-0097']
        ];

        foreach ($validData as $data) {
            $result = $method->invoke(null, $data);
            $this->assertFalse($result);
        }
    }

    // ============================================================================
    // Tests for class constants
    // ============================================================================

    public function testConstants_AreDefinedCorrectly(): void
    {
        $reflection = new ReflectionClass(Episciences_OpenAireResearchGraphTools::class);

        // Public constants
        $this->assertTrue($reflection->hasConstant('ONE_MONTH'));
        $oneMonth = $reflection->getConstant('ONE_MONTH');
        $this->assertSame(3600 * 24 * 31, $oneMonth);
    }

    public function testPrivateConstants_HaveExpectedValues(): void
    {
        $reflection = new ReflectionClass(Episciences_OpenAireResearchGraphTools::class);

        // Verify API configuration constants exist
        $this->assertTrue($reflection->hasConstant('API_BASE_URL'));
        $this->assertTrue($reflection->hasConstant('API_USER_AGENT'));
        $this->assertTrue($reflection->hasConstant('API_TIMEOUT_SECONDS'));
        $this->assertTrue($reflection->hasConstant('API_MAX_REDIRECTS'));

        // Verify cache configuration constants exist
        $this->assertTrue($reflection->hasConstant('CACHE_POOL_OARG'));
        $this->assertTrue($reflection->hasConstant('CACHE_POOL_AUTHORS'));
        $this->assertTrue($reflection->hasConstant('CACHE_POOL_FUNDING'));

        // Verify security limits exist
        $this->assertTrue($reflection->hasConstant('MAX_DOI_LENGTH'));
        $this->assertTrue($reflection->hasConstant('JSON_MAX_DEPTH'));
        $this->assertTrue($reflection->hasConstant('MAX_RESPONSE_SIZE'));
    }

    // ============================================================================
    // Integration tests for method combinations
    // ============================================================================

    /**
     * @throws \ReflectionException
     */
    public function testValidateDoiAndGenerateCacheKey_WorkTogether(): void
    {
        $validateMethod = $this->getPrivateMethod('validateDoi');
        $generateMethod = $this->getPrivateMethod('generateCacheKey');

        $inputDoi = '  10.1234/test-doi  ';
        $validatedDoi = $validateMethod->invoke(null, $inputDoi);
        $cacheKey = $generateMethod->invoke(null, $validatedDoi, '.json');

        // Should use trimmed DOI for cache key
        $expectedKey = md5('10.1234/test-doi') . '.json';
        $this->assertSame($expectedKey, $cacheKey);
    }

    /**
     * @throws \ReflectionException
     */
    public function testEncodeAndDecodeJson_RoundTrip(): void
    {
        $encodeMethod = $this->getPrivateMethod('encodeJson');
        $decodeMethod = $this->getPrivateMethod('decodeJson');

        $originalData = [
            'author' => 'José García',
            'orcid' => '0000-0002-1825-0097',
            'affiliation' => 'Université de München'
        ];

        $encoded = $encodeMethod->invoke(null, $originalData);
        $decoded = $decodeMethod->invoke(null, $encoded);

        $this->assertSame($originalData, $decoded);
    }

    // ============================================================================
    // Tests for getOrcidApiForDb() deprecation
    // ============================================================================

    public function testGetOrcidApiForDb_IsDeprecated(): void
    {
        $reflection = new ReflectionClass(Episciences_OpenAireResearchGraphTools::class);
        $method = $reflection->getMethod('getOrcidApiForDb');
        $docComment = $method->getDocComment();

        $this->assertStringContainsString('@deprecated', $docComment);
        $this->assertStringContainsString('findOrcidForAuthor', $docComment);
    }
}
