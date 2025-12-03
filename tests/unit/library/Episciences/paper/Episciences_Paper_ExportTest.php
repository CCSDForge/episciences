<?php

namespace unit\library\Episciences\paper;

require_once __DIR__ . '/../../../../../library/Episciences/Paper/Export.php';

use Episciences\Paper\Export;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class Episciences_Paper_ExportTest extends TestCase
{
    /**
     * Helper method to call private/protected methods for testing
     *
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws ReflectionException
     */
    private function callPrivateMethod(string $methodName, array $parameters)
    {
        $reflection = new ReflectionClass(Export::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $parameters);
    }

    /**
     * Test parseVolumeString with "Volume X, Issue Y" format
     */
    public function testParseVolumeStringWithVolumeAndIssue(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Volume X" format (no issue)
     */
    public function testParseVolumeStringWithVolumeOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13']);
        $this->assertSame('13', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "Vol. X, Issue Y" format
     */
    public function testParseVolumeStringWithVolAbbreviation(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Vol. 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Vol. X" format (no issue)
     */
    public function testParseVolumeStringWithVolAbbreviationOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Vol. 15']);
        $this->assertSame('15', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "Tome X, Issue Y" format (French)
     */
    public function testParseVolumeStringWithTome(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Tome 13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "Tome X" format (no issue)
     */
    public function testParseVolumeStringWithTomeOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Tome 20']);
        $this->assertSame('20', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with "X, Issue Y" format (numeric only)
     */
    public function testParseVolumeStringNumericWithIssue(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['13, Issue 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with "X" format (numeric only, no issue)
     */
    public function testParseVolumeStringNumericOnly(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['13']);
        $this->assertSame('13', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with whitespace variations
     */
    public function testParseVolumeStringWithExtraWhitespace(): void
    {
        // Extra spaces
        $result = $this->callPrivateMethod('parseVolumeString', ['  Volume  13  ,  Issue  2  ']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);

        // Tabs
        $result = $this->callPrivateMethod('parseVolumeString', ["Volume\t13,\tIssue\t2"]);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with case variations
     */
    public function testParseVolumeStringCaseInsensitive(): void
    {
        // Uppercase
        $result = $this->callPrivateMethod('parseVolumeString', ['VOLUME 13, ISSUE 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);

        // Mixed case
        $result = $this->callPrivateMethod('parseVolumeString', ['vOlUmE 13, iSsUe 2']);
        $this->assertSame('13', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString with large numbers
     */
    public function testParseVolumeStringWithLargeNumbers(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 999, Issue 888']);
        $this->assertSame('999', $result['volume']);
        $this->assertSame('888', $result['issue']);
    }

    /**
     * Test parseVolumeString with single digit numbers
     */
    public function testParseVolumeStringWithSingleDigit(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 1, Issue 2']);
        $this->assertSame('1', $result['volume']);
        $this->assertSame('2', $result['issue']);
    }

    /**
     * Test parseVolumeString returns original value for unrecognized formats
     */
    public function testParseVolumeStringFallbackToOriginal(): void
    {
        // Invalid format - should return original
        $result = $this->callPrivateMethod('parseVolumeString', ['Invalid Format']);
        $this->assertSame('Invalid Format', $result['volume']);
        $this->assertNull($result['issue']);

        // Text without numbers
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume ABC']);
        $this->assertSame('Volume ABC', $result['volume']);
        $this->assertNull($result['issue']);

        // Empty string
        $result = $this->callPrivateMethod('parseVolumeString', ['']);
        $this->assertSame('', $result['volume']);
        $this->assertNull($result['issue']);

        // Complex string
        $result = $this->callPrivateMethod('parseVolumeString', ['Special Edition 2024']);
        $this->assertSame('Special Edition 2024', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with edge cases
     */
    public function testParseVolumeStringEdgeCases(): void
    {
        // Just "Volume" without number
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume']);
        $this->assertSame('Volume', $result['volume']);
        $this->assertNull($result['issue']);

        // Number with "Issue" but no volume
        $result = $this->callPrivateMethod('parseVolumeString', ['Issue 5']);
        $this->assertSame('Issue 5', $result['volume']);
        $this->assertNull($result['issue']);

        // Volume with comma but no issue
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 10,']);
        $this->assertSame('Volume 10,', $result['volume']);
        $this->assertNull($result['issue']);
    }

    /**
     * Test parseVolumeString with zero values
     */
    public function testParseVolumeStringWithZero(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 0, Issue 0']);
        $this->assertSame('0', $result['volume']);
        $this->assertSame('0', $result['issue']);
    }

    /**
     * Test parseVolumeString return structure
     */
    public function testParseVolumeStringReturnStructure(): void
    {
        $result = $this->callPrivateMethod('parseVolumeString', ['Volume 13, Issue 2']);

        // Should be an array
        $this->assertIsArray($result);

        // Should have exactly 2 keys
        $this->assertCount(2, $result);

        // Should have 'volume' and 'issue' keys
        $this->assertArrayHasKey('volume', $result);
        $this->assertArrayHasKey('issue', $result);
    }
}
