<?php

namespace unit\library\Episciences\Website\Navigation\Page;

use Episciences_Website_Navigation_Page_Predefined;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Episciences_Website_Navigation_Page_Predefined
 *
 * Tests focus on static methods for retrieving predefined page permaliens
 */
class PredefinedTest extends TestCase
{
    /**
     * Reset the cache before each test to ensure test isolation
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCache();
    }

    /**
     * Reset the static cache using reflection
     */
    private function resetCache(): void
    {
        $reflection = new ReflectionClass(Episciences_Website_Navigation_Page_Predefined::class);
        $property = $reflection->getProperty('_cachedPermaliens');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Get the cached permaliens using reflection
     */
    private function getCachedValue(): ?array
    {
        $reflection = new ReflectionClass(Episciences_Website_Navigation_Page_Predefined::class);
        $property = $reflection->getProperty('_cachedPermaliens');
        $property->setAccessible(true);
        return $property->getValue();
    }

    // ============================================================================
    // Tests for getAllPermaliens()
    // ============================================================================

    public function testGetAllPermaliens_ReturnsArray(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        $this->assertIsArray($result);
    }

    public function testGetAllPermaliens_ReturnsNonEmptyArray(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        $this->assertNotEmpty($result);
    }

    public function testGetAllPermaliens_ContainsExpectedPermaliens(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Test for known predefined pages
        $expectedPermaliens = [
            'about',
            'credits',
            'editorial-board',
            'editorial-workflow',
            'ethical-charter',
            'for-conference-organisers',
            'former-members',
            'for-reviewers',
            'introduction-board',
            'journal-acknowledgements',
            'journal-indexing',
            'operating-charter-board',
            'prepare-submission',
            'publishing-policies',
            'reviewers-board',
            'scientific-advisory-board',
            'technical-board'
        ];

        foreach ($expectedPermaliens as $permalien) {
            $this->assertContains(
                $permalien,
                $result,
                "Expected permalien '$permalien' not found in result"
            );
        }
    }

    public function testGetAllPermaliens_ReturnsAssociativeArray(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Check that keys are class names and values are permaliens
        foreach ($result as $className => $permalien) {
            $this->assertIsString($className);
            $this->assertIsString($permalien);
            $this->assertStringStartsWith('Episciences_Website_Navigation_Page_', $className);
            $this->assertNotEmpty($permalien);
        }
    }

    public function testGetAllPermaliens_DoesNotIncludeEmptyPermaliens(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        foreach ($result as $permalien) {
            $this->assertNotEmpty($permalien);
        }
    }

    public function testGetAllPermaliens_ClassNamesMatchPermaliens(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Example: Episciences_Website_Navigation_Page_About should have 'about' as permalien
        $this->assertArrayHasKey('Episciences_Website_Navigation_Page_About', $result);
        $this->assertSame('about', $result['Episciences_Website_Navigation_Page_About']);

        $this->assertArrayHasKey('Episciences_Website_Navigation_Page_Credits', $result);
        $this->assertSame('credits', $result['Episciences_Website_Navigation_Page_Credits']);

        $this->assertArrayHasKey('Episciences_Website_Navigation_Page_PublishingPolicies', $result);
        $this->assertSame('publishing-policies', $result['Episciences_Website_Navigation_Page_PublishingPolicies']);
    }

    // ============================================================================
    // Tests for cache mechanism
    // ============================================================================

    public function testGetAllPermaliens_UsesCache(): void
    {
        // First call should populate cache
        $this->assertNull($this->getCachedValue(), 'Cache should be null initially');

        $result1 = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        $this->assertNotNull($this->getCachedValue(), 'Cache should be populated after first call');

        // Second call should return same cached result
        $result2 = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        $this->assertSame($result1, $result2, 'Both calls should return identical results');
        $this->assertSame($result1, $this->getCachedValue(), 'Result should match cached value');
    }

    public function testGetAllPermaliens_CacheReturnsSameReference(): void
    {
        $result1 = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();
        $result2 = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Both results should be the exact same array (same reference)
        $this->assertSame($result1, $result2);
    }

    // ============================================================================
    // Tests for isPredefinedPage()
    // ============================================================================

    public function testIsPredefinedPage_WithValidPermalien_ReturnsTrue(): void
    {
        $validPermaliens = [
            'about',
            'credits',
            'editorial-board',
            'publishing-policies',
            'ethical-charter'
        ];

        foreach ($validPermaliens as $permalien) {
            $result = Episciences_Website_Navigation_Page_Predefined::isPredefinedPage($permalien);
            $this->assertTrue($result, "Permalien '$permalien' should be recognized as predefined");
        }
    }

    public function testIsPredefinedPage_WithInvalidPermalien_ReturnsFalse(): void
    {
        $invalidPermaliens = [
            'custom-page',
            'non-existent',
            'random-string',
            'test-page',
            ''
        ];

        foreach ($invalidPermaliens as $permalien) {
            $result = Episciences_Website_Navigation_Page_Predefined::isPredefinedPage($permalien);
            $this->assertFalse($result, "Permalien '$permalien' should not be recognized as predefined");
        }
    }

    public function testIsPredefinedPage_IsCaseSensitive(): void
    {
        // 'about' is valid, but 'About' should not be
        $this->assertTrue(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('about'));
        $this->assertFalse(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('About'));
        $this->assertFalse(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('ABOUT'));
    }

    public function testIsPredefinedPage_WithEmptyString_ReturnsFalse(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('');

        $this->assertFalse($result);
    }

    public function testIsPredefinedPage_WithWhitespace_ReturnsFalse(): void
    {
        // Whitespace should not match any predefined pages
        $result = Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('  ');

        $this->assertFalse($result);
    }

    public function testIsPredefinedPage_DoesNotTrim(): void
    {
        // Method should not trim input, so ' about ' should not match 'about'
        $this->assertTrue(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('about'));
        $this->assertFalse(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage(' about '));
        $this->assertFalse(Episciences_Website_Navigation_Page_Predefined::isPredefinedPage('about '));
    }

    // ============================================================================
    // Integration tests
    // ============================================================================

    public function testGetAllPermaliens_AndIsPredefinedPage_WorkTogether(): void
    {
        // Get all permaliens
        $allPermaliens = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Each permalien should be recognized by isPredefinedPage
        foreach ($allPermaliens as $className => $permalien) {
            $result = Episciences_Website_Navigation_Page_Predefined::isPredefinedPage($permalien);
            $this->assertTrue(
                $result,
                "Permalien '$permalien' from class '$className' should be recognized as predefined"
            );
        }
    }

    public function testGetAllPermaliens_ReturnsExactly17PredefinedPages(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Based on current codebase, there should be exactly 17 predefined pages
        $this->assertCount(
            17,
            $result,
            'Expected exactly 17 predefined pages'
        );
    }

    // ============================================================================
    // Edge cases and robustness tests
    // ============================================================================

    public function testGetAllPermaliens_OnlyIncludesSubclassesOfPredefined(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Verify that all returned classes are subclasses of Predefined
        foreach ($result as $className => $permalien) {
            $this->assertTrue(
                is_subclass_of($className, Episciences_Website_Navigation_Page_Predefined::class),
                "Class '$className' should be a subclass of Episciences_Website_Navigation_Page_Predefined"
            );
        }
    }

    public function testGetAllPermaliens_DoesNotIncludeBaseClass(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // The base class itself should not be in the results
        $this->assertArrayNotHasKey(
            'Episciences_Website_Navigation_Page_Predefined',
            $result,
            'Base class Predefined should not be included in results'
        );
    }

    public function testGetAllPermaliens_DoesNotIncludeNonPredefinedPages(): void
    {
        $result = Episciences_Website_Navigation_Page_Predefined::getAllPermaliens();

        // Classes that don't extend Predefined should not be included
        $nonPredefinedClasses = [
            'Episciences_Website_Navigation_Page_Custom',
            'Episciences_Website_Navigation_Page_Link',
            'Episciences_Website_Navigation_Page_Folder',
        ];

        foreach ($nonPredefinedClasses as $className) {
            $this->assertArrayNotHasKey(
                $className,
                $result,
                "Non-predefined class '$className' should not be in results"
            );
        }
    }
}
