<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Mail.
 *
 * DB-dependent methods (getHistory, getHistoryQuery) are covered via source
 * inspection tests that verify the security fix is in place without requiring
 * a real database connection.
 *
 * Bugs documented:
 *   S1 — getHistoryQuery(): implode(',', $docIds) injected into sprintf() without
 *        sanitisation → SQL injection if $docIds contains non-numeric strings.
 *        Fix: array_map('intval', array_filter($docIds, 'is_numeric')) before implode.
 *
 * @covers Episciences_Mail
 */
final class Episciences_MailTest extends TestCase
{
    // =========================================================================
    // Security S1 — SQL injection via $docIds in getHistoryQuery()
    // =========================================================================

    /**
     * Regression S1: getHistoryQuery() must sanitise $docIds via array_map('intval', ...)
     * before passing them to sprintf() / raw SQL.
     */
    public function testGetHistoryQuerySanitisesDocIdsWithIntval(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'getHistoryQuery');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString(
            "array_map('intval',",
            $source,
            "Security S1: getHistoryQuery() must cast \$docIds elements to int via array_map('intval', ...)"
        );
        self::assertStringContainsString(
            "array_filter(\$docIds, 'is_numeric')",
            $source,
            "Security S1: getHistoryQuery() must filter non-numeric values via array_filter(..., 'is_numeric')"
        );
    }

    /**
     * Regression S1: UID from Episciences_Auth::getUid() must also be cast to int.
     */
    public function testGetHistoryQueryCastsUidToInt(): void
    {
        $method = new ReflectionMethod(Episciences_Mail::class, 'getHistoryQuery');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertMatchesRegularExpression(
            '/\(int\)\s*Episciences_Auth::getUid\(\)/',
            $source,
            'Security S1: Episciences_Auth::getUid() result must be cast to (int) before SQL injection'
        );
    }

    // =========================================================================
    // Sanitisation logic (pure, no DB)
    // =========================================================================

    /**
     * Verify the sanitisation pattern itself: array_map('intval', array_filter(..., 'is_numeric'))
     * correctly drops non-numeric values and casts remaining ones to int.
     */
    public function testSanitisationPatternDropsMaliciousIds(): void
    {
        $malicious = ['1', '2 OR 1=1', "'; DROP TABLE mail_log; --", '3', ''];
        $safe      = array_map('intval', array_filter($malicious, 'is_numeric'));

        self::assertSame([1, 3], array_values($safe));
    }

    public function testSanitisationPatternPreservesValidIds(): void
    {
        $valid = ['42', '100', '7'];
        $safe  = array_map('intval', array_filter($valid, 'is_numeric'));

        self::assertSame([42, 100, 7], array_values($safe));
    }

    public function testSanitisationPatternReturnsEmptyArrayForAllInvalid(): void
    {
        $invalid = ['abc', 'OR 1=1', '--', ''];
        $safe    = array_map('intval', array_filter($invalid, 'is_numeric'));

        self::assertSame([], array_values($safe));
    }

    public function testSanitisationPatternCastsStringIntegersCorrectly(): void
    {
        $input = ['0', '1', '999'];
        $safe  = array_map('intval', array_filter($input, 'is_numeric'));

        self::assertSame([0, 1, 999], array_values($safe));
    }
}
