<?php

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Page_Manager.
 *
 * DB-dependent methods (add, update, delete) are excluded.
 * Pure-logic: resolvePageTag() and tryRevalidate() via reflection.
 *
 * @covers Episciences_Page_Manager
 */
class Episciences_Page_ManagerTest extends TestCase
{
    // =========================================================================
    // resolvePageTag() — skip list
    // =========================================================================

    public function testResolvePageTag_EditorialWorkflow_ReturnsNull(): void
    {
        self::assertNull($this->resolvePageTag('editorial-workflow', 'epijinfo'));
    }

    public function testResolvePageTag_EthicalCharter_ReturnsNull(): void
    {
        self::assertNull($this->resolvePageTag('ethical-charter', 'epijinfo'));
    }

    public function testResolvePageTag_PrepareSubmission_ReturnsNull(): void
    {
        self::assertNull($this->resolvePageTag('prepare-submission', 'epijinfo'));
    }

    // =========================================================================
    // resolvePageTag() — known mappings
    // =========================================================================

    public function testResolvePageTag_About_ReturnsMappedTag(): void
    {
        self::assertSame('about-epijinfo', $this->resolvePageTag('about', 'epijinfo'));
    }

    public function testResolvePageTag_Indexing_ReturnsMappedTag(): void
    {
        self::assertSame('indexing-epijinfo', $this->resolvePageTag('indexing', 'epijinfo'));
    }

    public function testResolvePageTag_IndexationMetrics_ReturnsMappedTag(): void
    {
        // 'indexation-metrics' maps to 'indexation', not 'indexation-metrics'
        self::assertSame('indexation-epijinfo', $this->resolvePageTag('indexation-metrics', 'epijinfo'));
    }

    public function testResolvePageTag_Credits_ReturnsMappedTag(): void
    {
        self::assertSame('credits-epijinfo', $this->resolvePageTag('credits', 'epijinfo'));
    }

    public function testResolvePageTag_ForReviewers_ReturnsMappedTag(): void
    {
        self::assertSame('for-reviewers-epijinfo', $this->resolvePageTag('for-reviewers', 'epijinfo'));
    }

    public function testResolvePageTag_Acknowledgements_ReturnsMappedTag(): void
    {
        self::assertSame('acknowledgements-epijinfo', $this->resolvePageTag('acknowledgements', 'epijinfo'));
    }

    // =========================================================================
    // resolvePageTag() — unknown page_code falls back to page-{code}-{rvcode}
    // =========================================================================

    public function testResolvePageTag_UnknownCode_ReturnsPrefixedTag(): void
    {
        self::assertSame('page-custom-section-epijinfo', $this->resolvePageTag('custom-section', 'epijinfo'));
    }

    public function testResolvePageTag_UnknownCode_IncludesRvcode(): void
    {
        $tag = $this->resolvePageTag('my-page', 'mathinfo');
        self::assertSame('page-my-page-mathinfo', $tag);
    }

    // =========================================================================
    // tryRevalidate() — no exception propagated when feature is disabled
    // =========================================================================

    public function testTryRevalidate_FeatureFlagOff_DoesNotThrow(): void
    {
        // EPISCIENCES_ENABLE_NEXT_FRONT is not defined in the test environment.
        // RevalidationService::revalidateOrEnqueue() is a no-op → no exception.
        $this->callTryRevalidate('epijinfo', 'about-epijinfo', 'test');
        self::assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function resolvePageTag(string $pageCode, string $rvcode): ?string
    {
        $method = new \ReflectionMethod(\Episciences_Page_Manager::class, 'resolvePageTag');
        $method->setAccessible(true);
        return $method->invoke(null, $pageCode, $rvcode);
    }

    private function callTryRevalidate(string $rvcode, string $tag, string $context): void
    {
        $method = new \ReflectionMethod(\Episciences_Page_Manager::class, 'tryRevalidate');
        $method->setAccessible(true);
        $method->invoke(null, $rvcode, $tag, $context);
    }
}
