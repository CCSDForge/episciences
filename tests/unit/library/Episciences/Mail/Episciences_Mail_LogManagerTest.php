<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_LogManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Episciences_Mail_LogManager.
 *
 * deleteByDocid() has a guard clause for docid < 1 that can be tested
 * without DB access. The DB path (docid >= 1) requires the Docker environment.
 *
 * @covers Episciences_Mail_LogManager
 */
final class Episciences_Mail_LogManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // deleteByDocid — guard clause (no DB needed)
    // -------------------------------------------------------------------------

    public function testDeleteByDocidReturnsFalseForZero(): void
    {
        self::assertFalse(Episciences_Mail_LogManager::deleteByDocid(0));
    }

    public function testDeleteByDocidReturnsFalseForNegativeValue(): void
    {
        self::assertFalse(Episciences_Mail_LogManager::deleteByDocid(-1));
    }

    public function testDeleteByDocidReturnsFalseForLargeNegativeValue(): void
    {
        self::assertFalse(Episciences_Mail_LogManager::deleteByDocid(-99999));
    }

    // -------------------------------------------------------------------------
    // deleteByDocid — DB path (requires Docker / live DB)
    // -------------------------------------------------------------------------

    /**
     * deleteByDocid() with a valid docid (>= 1) executes the DB delete and
     * returns true. Even if no rows are found, the call succeeds.
     *
     * This test requires the live database provided by the Docker test environment.
     */
    public function testDeleteByDocidReturnsTrueForValidDocid(): void
    {
        // Use a docid that very likely does not exist in the test DB.
        // The method returns true regardless of how many rows were deleted.
        $result = Episciences_Mail_LogManager::deleteByDocid(PHP_INT_MAX);

        self::assertTrue($result);
    }

    /**
     * deleteByDocid() must return true for the minimum valid docid (1).
     */
    public function testDeleteByDocidReturnsTrueForMinimumValidDocid(): void
    {
        $result = Episciences_Mail_LogManager::deleteByDocid(1);

        self::assertTrue($result);
    }
}
