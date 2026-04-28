<?php

namespace unit\library\Episciences;

use Episciences_ReviewersManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_ReviewersManager.
 *
 * DB-dependent methods (getList, getSuggestedReviewers, getUnwantedReviewers,
 * addReviewerToPool) and Zend_Form-dependent methods (acceptInvitationForm,
 * refuseInvitationForm, reviewer_answer_form) are covered via API-contract
 * and source-inspection tests only.
 *
 * Bugs documented/fixed:
 *   RM1 — getList(): $settings parameter is accepted but never used; the method
 *          always delegates to Episciences_Review::getReviewers() without filters.
 *          Documented only — changing would require a larger refactoring and
 *          aligning with callers.
 *   RM2 — addReviewerToPool(): DB exception was unhandled — the method always
 *          returned true regardless of success or failure.
 *          Fixed: wrapped in try/catch; returns false on exception.
 *   RM3 — getSuggestedReviewers() / getUnwantedReviewers(): literal string values
 *          were embedded in where() without parameterization
 *          (e.g. ->where("SETTING = 'suggestedReviewer'")).
 *          Fixed: use parameterized where('SETTING = ?', 'value').
 *
 * @covers Episciences_ReviewersManager
 */
final class Episciences_ReviewersManagerTest extends TestCase
{
    // =========================================================================
    // API contract — method signatures
    // =========================================================================

    public function testGetListAcceptsSettingsArray(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getList');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('settings', $params[0]->getName());
        self::assertTrue($params[0]->isOptional(), 'getList() $settings must be optional');
        self::assertSame([], $params[0]->getDefaultValue(), 'getList() $settings must default to []');
    }

    public function testGetSuggestedReviewersAcceptsDocid(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('docid', $params[0]->getName());
    }

    public function testGetUnwantedReviewersAcceptsDocid(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame('docid', $params[0]->getName());
    }

    public function testAddReviewerToPoolReturnTypeIsBool(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');

        self::assertNotNull($method->getReturnType());
        self::assertSame('bool', $method->getReturnType()->getName());
    }

    public function testAddReviewerToPoolHasThreeParameters(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $params = $method->getParameters();

        self::assertCount(3, $params);
        self::assertSame('uid',  $params[0]->getName());
        self::assertSame('vid',  $params[1]->getName());
        self::assertSame('rvid', $params[2]->getName());
    }

    public function testAddReviewerToPoolSecondParamDefaultsToZero(): void
    {
        $method = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $params = $method->getParameters();

        self::assertTrue($params[1]->isOptional());
        self::assertSame(0, $params[1]->getDefaultValue());
    }

    // =========================================================================
    // Bug RM1 — getList() ignores $settings (documented, not fixed)
    // =========================================================================

    /**
     * Design issue RM1 (documented, not fixed): getList() accepts a $settings
     * parameter but passes it to nothing — it always delegates to
     * Episciences_Review::getReviewers() with no filter arguments.
     *
     * Callers that pass filters (e.g. volume id, role) silently get the full
     * reviewer list instead of the filtered one.
     *
     * This test documents the current behaviour by inspecting the source to
     * confirm the dead parameter, without breaking the existing API.
     */
    public function testGetListSourceIgnoresSettingsParameter(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getList');
        $source     = $this->getMethodSource($reflection);

        // The $settings variable is never used in the method body
        self::assertStringNotContainsString(
            '$settings',
            // Remove the parameter declaration line so we only check the body
            preg_replace('/function\s+getList[^{]*\{/', '', $source),
            'Design issue RM1: $settings is declared but never used inside getList() — filters are silently ignored'
        );

        // Always delegates to getReviewers() with no arguments
        self::assertStringContainsString(
            'getReviewers()',
            $source,
            'getList() must delegate to Episciences_Review::getReviewers()'
        );
    }

    // =========================================================================
    // Bug RM2 — addReviewerToPool() exception handling
    // =========================================================================

    /**
     * Fix RM2: the original method had no try/catch — any DB exception propagated
     * uncaught and the declared ": bool" return type was never false.
     * Fixed: wrapped in try/catch; logs the exception and returns false on failure.
     */
    public function testAddReviewerToPoolSourceHasTryCatch(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            'try {',
            $source,
            'Fix RM2: addReviewerToPool() must have a try block to catch DB exceptions'
        );

        self::assertStringContainsString(
            'catch (',
            $source,
            'Fix RM2: addReviewerToPool() must have a catch block'
        );

        self::assertStringContainsString(
            'return false;',
            $source,
            'Fix RM2: addReviewerToPool() must return false when an exception is caught'
        );

        self::assertStringContainsString(
            'return true;',
            $source,
            'Fix RM2: addReviewerToPool() must return true on success'
        );
    }

    public function testAddReviewerToPoolSourceLogsException(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'addReviewerToPool');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            'error_log(',
            $source,
            'Fix RM2: addReviewerToPool() must log the exception message before returning false'
        );
    }

    // =========================================================================
    // Bug RM3 — getSuggestedReviewers() / getUnwantedReviewers() parameterization
    // =========================================================================

    /**
     * Fix RM3: the original where() calls embedded the setting name as a raw
     * string literal in the SQL fragment, bypassing Zend_Db quoting:
     *   ->where("SETTING = 'suggestedReviewer'")
     *   ->where("SETTING = 'unwantedReviewer'")
     *
     * Fixed: use the parameterized form:
     *   ->where('SETTING = ?', 'suggestedReviewer')
     *   ->where('SETTING = ?', 'unwantedReviewer')
     */
    public function testGetSuggestedReviewersSourceUsesParameterizedWhere(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $source     = $this->getMethodSource($reflection);

        // Old pattern: literal string embedded in the where clause
        self::assertStringNotContainsString(
            "'SETTING = \\'suggestedReviewer\\''",
            $source,
            'Fix RM3: where() must not embed the setting name as a raw string literal'
        );

        // New pattern: parameterized placeholder
        self::assertStringContainsString(
            "where('SETTING = ?', 'suggestedReviewer')",
            $source,
            'Fix RM3: getSuggestedReviewers() must use parameterized where() for SETTING'
        );
    }

    public function testGetUnwantedReviewersSourceUsesParameterizedWhere(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $source     = $this->getMethodSource($reflection);

        // Old pattern: literal string embedded in the where clause
        self::assertStringNotContainsString(
            "'SETTING = \\'unwantedReviewer\\''",
            $source,
            'Fix RM3: where() must not embed the setting name as a raw string literal'
        );

        // New pattern: parameterized placeholder
        self::assertStringContainsString(
            "where('SETTING = ?', 'unwantedReviewer')",
            $source,
            'Fix RM3: getUnwantedReviewers() must use parameterized where() for SETTING'
        );
    }

    public function testGetSuggestedReviewersAlsoParameterizesDocid(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getSuggestedReviewers');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            "where('DOCID = ?', \$docid)",
            $source,
            'getSuggestedReviewers() must use parameterized where() for DOCID'
        );
    }

    public function testGetUnwantedReviewersAlsoParameterizesDocid(): void
    {
        $reflection = new ReflectionMethod(Episciences_ReviewersManager::class, 'getUnwantedReviewers');
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            "where('DOCID = ?', \$docid)",
            $source,
            'getUnwantedReviewers() must use parameterized where() for DOCID'
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getMethodSource(ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
