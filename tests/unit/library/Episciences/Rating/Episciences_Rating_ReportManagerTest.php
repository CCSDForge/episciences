<?php

namespace unit\library\Episciences\Rating;

use Episciences_Rating_ReportManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Rating_ReportManager.
 *
 * DB-dependent methods (updateUidS, deleteByUidAndDocId, updateProcessing) are
 * covered via API-contract and source-inspection tests only.
 * renameGrid() is covered via source inspection (requires real filesystem for
 * full integration testing).
 *
 * Bugs documented/fixed:
 *   R1 — renameGrid(): logic inversion — always returned true regardless of
 *        whether rename() succeeded or failed.
 *   R2 — renameGrid(): error_log message missing spaces around $nameDir.
 *   R4 — updateProcessing(): trigger_error($msg, E_USER_ERROR) killed the process;
 *        replaced with error_log() inside a proper catch block.
 *   R5 — updateProcessing(): DELETE then INSERT without transaction — data loss
 *        if INSERT failed; wrapped in beginTransaction/commit/rollBack.
 *
 * @covers Episciences_Rating_ReportManager
 */
final class Episciences_Rating_ReportManagerTest extends TestCase
{
    // =========================================================================
    // API contract
    // =========================================================================

    public function testTableConstantIsDefined(): void
    {
        // T_REVIEWER_REPORTS is a compile-time constant resolved when the class loads
        self::assertIsString(Episciences_Rating_ReportManager::TABLE);
        self::assertNotEmpty(Episciences_Rating_ReportManager::TABLE);
    }

    public function testUpdateUidSHasTwoIntParameters(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'updateUidS');
        $params = $method->getParameters();

        self::assertCount(2, $params);

        self::assertSame('oldUid', $params[0]->getName());
        self::assertSame('int', $params[0]->getType()->getName());
        self::assertSame(0, $params[0]->getDefaultValue());

        self::assertSame('newUid', $params[1]->getName());
        self::assertSame('int', $params[1]->getType()->getName());
        self::assertSame(0, $params[1]->getDefaultValue());
    }

    public function testUpdateUidSReturnTypeIsInt(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'updateUidS');

        self::assertSame('int', $method->getReturnType()->getName());
    }

    public function testDeleteByUidAndDocIdHasTwoIntParameters(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'deleteByUidAndDocId');
        $params = $method->getParameters();

        self::assertCount(2, $params);
        self::assertSame('uid',   $params[0]->getName());
        self::assertSame('docId', $params[1]->getName());
    }

    public function testRenameGridHasTwoIntParameters(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'renameGrid');
        $params = $method->getParameters();

        self::assertCount(2, $params);
        self::assertSame('docId', $params[0]->getName());
        self::assertSame('uid',   $params[1]->getName());
    }

    public function testRenameGridReturnTypeIsBool(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'renameGrid');

        self::assertSame('bool', $method->getReturnType()->getName());
    }

    // =========================================================================
    // Bug R1 — renameGrid() logic inversion fix
    // =========================================================================

    /**
     * Fix R1: the original code was:
     *   if ($result = !rename($nameDir, $newName)) { ... return $result; }
     *   return !$result;
     *
     * This is an assignment inside the condition:
     *   - rename fails  → !false = true  → enters if → returns true  (WRONG: should be false)
     *   - rename succeeds → !true = false → skips if → returns !false = true (also returns true!)
     *
     * Both branches returned true. The fix uses a plain boolean expression:
     *   if (!rename(...)) { ... return false; }
     *   return true;
     */
    public function testRenameGridSourceUsesCorrectBooleanLogic(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'renameGrid');
        $source     = $this->getMethodSource($reflection);

        // Old pattern: assignment-in-condition inversion
        self::assertStringNotContainsString(
            '$result = !rename(',
            $source,
            'Fix R1: the assignment-in-condition pattern "$result = !rename()" must be removed'
        );

        // New pattern: plain negation
        self::assertStringContainsString(
            'if (!rename(',
            $source,
            'Fix R1: use "if (!rename(...))" for a clear, correct boolean branch'
        );

        // Explicit return values
        self::assertStringContainsString(
            'return false;',
            $source,
            'Fix R1: renameGrid() must explicitly return false on rename failure'
        );
        self::assertStringContainsString(
            'return true;',
            $source,
            'Fix R1: renameGrid() must explicitly return true on success'
        );
    }

    // =========================================================================
    // Bug R2 — error_log message spaces
    // =========================================================================

    /**
     * Fix R2: the original message was:
     *   'The filename' . $nameDir . 'not exists or is not a directory'
     * which produces "The filenamePATHnot exists..." with no spaces around the path.
     */
    public function testRenameGridErrorLogMessageHasSpacesAroundPath(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'renameGrid');
        $source     = $this->getMethodSource($reflection);

        self::assertStringNotContainsString(
            "'The filename' . \$nameDir . 'not exists",
            $source,
            'Fix R2: error_log message must have spaces around $nameDir'
        );

        self::assertStringContainsString(
            "'The filename '",
            $source,
            'Fix R2: error_log message must start with "The filename " (trailing space)'
        );
    }

    // =========================================================================
    // Bug R4 — fatal trigger_error replaced with error_log
    // =========================================================================

    /**
     * Fix R4: trigger_error($msg, E_USER_ERROR) was fatal — it terminated the
     * PHP process immediately, preventing any rollback or cleanup.
     * Replaced with error_log() inside the catch block.
     */
    public function testUpdateProcessingDoesNotUseFatalTriggerError(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'updateProcessing');
        $reflection->setAccessible(true);
        $source = $this->getMethodSource($reflection);

        self::assertStringNotContainsString(
            'E_USER_ERROR',
            $source,
            'Fix R4: E_USER_ERROR is fatal — must be replaced with error_log() or a logger'
        );

        self::assertStringNotContainsString(
            'trigger_error',
            $source,
            'Fix R4: trigger_error() must be removed from updateProcessing()'
        );
    }

    // =========================================================================
    // Bug R5 — transaction wrapping
    // =========================================================================

    /**
     * Fix R5: the original code deleted rows then inserted new ones without a
     * transaction. If the INSERT failed (exception), the DELETEd rows were gone
     * forever — irreversible data loss.
     *
     * The fix wraps DELETE + INSERT in beginTransaction/commit, with rollBack
     * on exception to restore the deleted rows.
     */
    public function testUpdateProcessingUsesTransaction(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'updateProcessing');
        $reflection->setAccessible(true);
        $source = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            'beginTransaction()',
            $source,
            'Fix R5: updateProcessing() must use a DB transaction to prevent data loss'
        );

        self::assertStringContainsString(
            'commit()',
            $source,
            'Fix R5: a commit() must be called after successful DELETE + INSERT'
        );

        self::assertStringContainsString(
            'rollBack()',
            $source,
            'Fix R5: a rollBack() must be called in the catch block to restore deleted rows'
        );
    }

    /**
     * The DELETE must be inside the try block (after beginTransaction) so it
     * is rolled back on INSERT failure.
     */
    public function testUpdateProcessingDeleteIsInsideTryBlock(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_ReportManager::class, 'updateProcessing');
        $reflection->setAccessible(true);
        $source = $this->getMethodSource($reflection);

        // beginTransaction must appear before delete in the source
        $posBegin  = strpos($source, 'beginTransaction()');
        $posDelete = strpos($source, '->delete(');
        $posCommit = strpos($source, 'commit()');

        self::assertNotFalse($posBegin,  'beginTransaction() must be present');
        self::assertNotFalse($posDelete, '->delete() must be present');
        self::assertNotFalse($posCommit, 'commit() must be present');

        self::assertLessThan($posDelete, $posBegin,  'beginTransaction() must come before delete()');
        self::assertLessThan($posCommit, $posDelete, 'delete() must come before commit()');
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
