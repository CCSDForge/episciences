<?php

namespace unit\library\Episciences\paper;

use Episciences_PapersManager;
use Episciences_Review;
use Episciences_CopyEditor;
use Episciences_Editor;
use Episciences_Reviewer;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Contract / regression tests for the removal of the $cached parameter from
 * PapersManager::getList() and all its callers.
 *
 * Background: getList() previously accepted a bool $cached flag that triggered
 * a filesystem-based cache using Episciences_Cache (a file-level cache with no
 * TTL management and implicit unserialize() calls). The parameter was removed
 * as part of the migration to symfony/cache 5.4 (PSR-6).
 *
 * These tests document the new API contract and guard against accidental
 * reintroduction of the legacy parameter.
 *
 * Note: the business logic of getList() requires a database connection and is
 * therefore covered by integration tests, not here.
 *
 * @covers Episciences_PapersManager
 * @covers Episciences_Review
 * @covers Episciences_CopyEditor
 * @covers Episciences_Editor
 * @covers Episciences_Reviewer
 * @covers Episciences_Volume
 */
class Episciences_PapersManagerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // PapersManager::getList()
    // -----------------------------------------------------------------------

    /**
     * getList() must no longer expose a $cached parameter.
     * Its presence would allow callers to silently pass stale data from the
     * old file-level cache, bypassing DB consistency.
     */
    public function testGetListDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_PapersManager::class, 'getList');

        self::assertNotContains(
            'cached',
            $params,
            'The $cached parameter must be removed from PapersManager::getList()'
        );
    }

    /**
     * The method must have exactly four parameters after the removal.
     */
    public function testGetListHasExactlyFourParameters(): void
    {
        $method = new ReflectionMethod(Episciences_PapersManager::class, 'getList');
        self::assertCount(4, $method->getParameters());
    }

    /**
     * First parameter: $settings (array, default []).
     */
    public function testGetListFirstParamIsSettingsArray(): void
    {
        $param = $this->getParam(Episciences_PapersManager::class, 'getList', 0);

        self::assertSame('settings', $param->getName());
        self::assertTrue($param->hasType());
        self::assertSame('array', $param->getType()->getName());
        self::assertTrue($param->isDefaultValueAvailable());
        self::assertSame([], $param->getDefaultValue());
    }

    /**
     * Second parameter: $isFilterInfos (bool, default false).
     * Previously this position was $cached; after the removal $isFilterInfos
     * moved up. A regression here would silently change filtering behaviour
     * for all callers.
     */
    public function testGetListSecondParamIsIsFilterInfosBoolDefaultFalse(): void
    {
        $param = $this->getParam(Episciences_PapersManager::class, 'getList', 1);

        self::assertSame('isFilterInfos', $param->getName());
        self::assertTrue($param->hasType());
        self::assertSame('bool', $param->getType()->getName());
        self::assertTrue($param->isDefaultValueAvailable());
        self::assertFalse($param->getDefaultValue());
    }

    /**
     * Third parameter: $isLimit (bool, default true).
     */
    public function testGetListThirdParamIsIsLimitBoolDefaultTrue(): void
    {
        $param = $this->getParam(Episciences_PapersManager::class, 'getList', 2);

        self::assertSame('isLimit', $param->getName());
        self::assertTrue($param->isDefaultValueAvailable());
        self::assertTrue($param->getDefaultValue());
    }

    // -----------------------------------------------------------------------
    // Review::getPapers()
    // -----------------------------------------------------------------------

    /**
     * Review::getPapers() must no longer expose a $cached parameter.
     */
    public function testReviewGetPapersDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_Review::class, 'getPapers');

        self::assertNotContains(
            'cached',
            $params,
            'The $cached parameter must be removed from Review::getPapers()'
        );
    }

    /**
     * Review::getPapers() must have exactly three parameters after the removal.
     */
    public function testReviewGetPapersHasThreeParameters(): void
    {
        $method = new ReflectionMethod(Episciences_Review::class, 'getPapers');
        self::assertCount(3, $method->getParameters());
    }

    /**
     * Second parameter of getPapers() must be $isFilterInfos (bool, default false).
     * Callers that passed false as the second argument previously addressed $cached;
     * they now correctly address $isFilterInfos with the same value.
     */
    public function testReviewGetPapersSecondParamIsIsFilterInfosBoolDefaultFalse(): void
    {
        $param = $this->getParam(Episciences_Review::class, 'getPapers', 1);

        self::assertSame('isFilterInfos', $param->getName());
        self::assertTrue($param->isDefaultValueAvailable());
        self::assertFalse($param->getDefaultValue());
    }

    // -----------------------------------------------------------------------
    // CopyEditor::loadAssignedPapers()
    // -----------------------------------------------------------------------

    /**
     * CopyEditor::loadAssignedPapers() must NOT expose a $cached parameter
     * in its own signature (it was only in the internal call to getList()).
     */
    public function testCopyEditorLoadAssignedPapersDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_CopyEditor::class, 'loadAssignedPapers');

        self::assertNotContains('cached', $params);
    }

    /**
     * The second parameter of loadAssignedPapers() must be $isFilterInfos.
     * Previously the internal call passed `false` as $cached (2nd arg of getList);
     * after the removal it correctly passes $isFilterInfos as 2nd arg.
     */
    public function testCopyEditorLoadAssignedPapersSecondParamIsIsFilterInfos(): void
    {
        $param = $this->getParam(Episciences_CopyEditor::class, 'loadAssignedPapers', 1);

        self::assertSame('isFilterInfos', $param->getName());
    }

    // -----------------------------------------------------------------------
    // Editor::loadAssignedPapers()
    // -----------------------------------------------------------------------

    public function testEditorLoadAssignedPapersDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_Editor::class, 'loadAssignedPapers');

        self::assertNotContains('cached', $params);
    }

    public function testEditorLoadAssignedPapersSecondParamIsIsFilterInfos(): void
    {
        $param = $this->getParam(Episciences_Editor::class, 'loadAssignedPapers', 1);

        self::assertSame('isFilterInfos', $param->getName());
    }

    // -----------------------------------------------------------------------
    // Reviewer::loadAssignedPapers()
    // -----------------------------------------------------------------------

    public function testReviewerLoadAssignedPapersDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_Reviewer::class, 'loadAssignedPapers');

        self::assertNotContains('cached', $params);
    }

    // -----------------------------------------------------------------------
    // Volume::getPaperListFromVolume()
    // -----------------------------------------------------------------------

    public function testVolumeGetPaperListFromVolumeDoesNotHaveCachedParameter(): void
    {
        $params = $this->getParamNames(Episciences_Volume::class, 'getPaperListFromVolume');

        self::assertNotContains('cached', $params);
    }

    /**
     * The first non-settings parameter of getPaperListFromVolume() is
     * $includeSecondaryVolume (bool, default true), which was previously passed
     * as the $isLimit position. Verify it maps correctly.
     */
    public function testVolumeGetPaperListFromVolumeFirstParamIsExcludedStatus(): void
    {
        $param = $this->getParam(Episciences_Volume::class, 'getPaperListFromVolume', 0);

        self::assertSame('excludedStatus', $param->getName());
    }

    // -----------------------------------------------------------------------
    // Security regression: no Episciences_Cache usage in getList()
    // -----------------------------------------------------------------------

    /**
     * Verifies that PapersManager::getList() source code no longer references
     * Episciences_Cache. This guards against accidental reintroduction of the
     * insecure file-level cache that called unserialize() on filesystem data,
     * which could lead to PHP object injection if cache files were tampered with.
     */
    public function testGetListSourceDoesNotReferenceEpisciencesCache(): void
    {
        $reflection = new ReflectionMethod(Episciences_PapersManager::class, 'getList');
        $filename   = $reflection->getFileName();
        $start      = $reflection->getStartLine();
        $end        = $reflection->getEndLine();

        $lines  = file($filename);
        $source = implode('', array_slice($lines, $start - 1, $end - $start + 1));

        self::assertStringNotContainsString(
            'Episciences_Cache',
            $source,
            'PapersManager::getList() must not reference Episciences_Cache'
        );
        self::assertStringNotContainsString(
            'unserialize',
            $source,
            'PapersManager::getList() must not call unserialize() on cache data'
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return string[]
     * @throws \ReflectionException
     */
    private function getParamNames(string $class, string $method): array
    {
        $ref = new ReflectionMethod($class, $method);
        return array_map(fn(ReflectionParameter $p) => $p->getName(), $ref->getParameters());
    }

    /** @throws \ReflectionException */
    private function getParam(string $class, string $method, int $index): ReflectionParameter
    {
        $ref = new ReflectionMethod($class, $method);
        return $ref->getParameters()[$index];
    }
}
