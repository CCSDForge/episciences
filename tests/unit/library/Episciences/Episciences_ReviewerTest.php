<?php

namespace unit\library\Episciences;

use Episciences_Reviewer;
use Episciences_User_Invitation;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for Episciences_Reviewer.
 *
 * DB-dependent methods (loadAssignments, loadAssignedPapers, getReport,
 * loadReviewing, loadInvitations) are covered via API-contract and
 * source-inspection tests only.
 * Pure-logic methods and entity state are tested with real objects.
 *
 * Bugs documented/fixed:
 *   Rv1 — getAssignment($docId): direct array access → undefined index warning;
 *          fixed with ?? null.
 *   Rv2 — loadReviewingById(): created a fresh local $reviewings array that
 *          overwrote $this->_reviewings, destroying previously loaded reviewings;
 *          fixed to merge with getReviewings()/setReviewings().
 *   Rv3 — getComments(int $docId): cache stored in $this->_comments (flat)
 *          instead of $this->_comments[$docId]; second call for a different
 *          docId returned stale results from the first call.
 *   Rv4 — getAssignedPapers() / loadAssignedPapers(): $isLimit parameter missing
 *          bool type hint.
 *   Rv5 — unassign(): used legacy Ccsd_Tools::ifsetor() instead of ?? operator,
 *          inconsistent with assign().
 *
 * @covers Episciences_Reviewer
 */
final class Episciences_ReviewerTest extends TestCase
{
    // =========================================================================
    // Status constants
    // =========================================================================

    public function testStatusConstants(): void
    {
        self::assertSame('pending',    Episciences_Reviewer::STATUS_PENDING);
        self::assertSame('active',     Episciences_Reviewer::STATUS_ACTIVE);
        self::assertSame('declined',   Episciences_Reviewer::STATUS_DECLINED);
        self::assertSame('cancelled',  Episciences_Reviewer::STATUS_CANCELLED);
        self::assertSame('expired',    Episciences_Reviewer::STATUS_EXPIRED);
        self::assertSame('uninvited',  Episciences_Reviewer::STATUS_UNINVITED);
        self::assertSame('inactive',   Episciences_Reviewer::STATUS_INACTIVE);
    }

    // =========================================================================
    // setStatus / getStatus / setWhen / getWhen
    // =========================================================================

    public function testSetAndGetStatus(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setStatus(Episciences_Reviewer::STATUS_ACTIVE);

        self::assertSame(Episciences_Reviewer::STATUS_ACTIVE, $reviewer->getStatus());
    }

    public function testSetStatusReturnsFluentInterface(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertSame($reviewer, $reviewer->setStatus(Episciences_Reviewer::STATUS_PENDING));
    }

    public function testSetAndGetWhen(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setWhen('2024-06-15 10:00:00');

        self::assertSame('2024-06-15 10:00:00', $reviewer->getWhen());
    }

    public function testSetWhenReturnsFluentInterface(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertSame($reviewer, $reviewer->setWhen('2024-01-01'));
    }

    // =========================================================================
    // Assignments
    // =========================================================================

    public function testHasAssignmentsReturnsFalseWhenEmpty(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertFalse($reviewer->hasAssignments());
    }

    public function testHasAssignmentsReturnsTrueAfterSetAssignments(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setAssignments([42 => 'assignment_object']);

        self::assertTrue($reviewer->hasAssignments());
    }

    public function testSetAssignmentsReturnsFluentInterface(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertSame($reviewer, $reviewer->setAssignments([]));
    }

    // =========================================================================
    // Bug Rv1 — getAssignment() undefined index fix
    // =========================================================================

    /**
     * Fix Rv1: accessing $this->_assignments[$docId] directly throws an
     * "undefined index" notice (PHP 7.x) or returns null with deprecation (PHP 8.x)
     * when $docId is not in the array.
     * Fixed with ?? null.
     */
    public function testGetAssignmentReturnsNullForMissingDocId(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setAssignments([]);

        self::assertNull($reviewer->getAssignment(999), 'Fix Rv1: must return null for missing docId, not undefined index');
    }

    public function testGetAssignmentReturnsValueWhenPresent(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setAssignments([42 => 'my_assignment']);

        self::assertSame('my_assignment', $reviewer->getAssignment(42));
    }

    // =========================================================================
    // Reviewings
    // =========================================================================

    public function testGetReviewingsReturnsEmptyArrayByDefault(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertIsArray($reviewer->getReviewings());
        self::assertEmpty($reviewer->getReviewings());
    }

    public function testSetAndGetReviewings(): void
    {
        $reviewer = new Episciences_Reviewer();
        $reviewer->setReviewings([10 => 'reviewing_a', 20 => 'reviewing_b']);

        self::assertCount(2, $reviewer->getReviewings());
        self::assertSame('reviewing_a', $reviewer->getReviewings()[10]);
    }

    // =========================================================================
    // Bug Rv2 — loadReviewingById() destroys existing reviewings
    // =========================================================================

    /**
     * Fix Rv2: loadReviewingById() created a fresh uninitialized local variable
     * $reviewings[$docId] = $oReviewing, then did $this->_reviewings = $reviewings,
     * erasing all previously loaded reviewings.
     *
     * The fix mirrors loadReviewing(): load existing reviewings first, merge,
     * then setReviewings().
     */
    public function testLoadReviewingByIdSourceMergesWithExistingReviewings(): void
    {
        $reflection = new ReflectionMethod(Episciences_Reviewer::class, 'loadReviewingById');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        // Old pattern: orphan local var assignment directly to $this->_reviewings
        self::assertStringNotContainsString(
            '$this->_reviewings = $reviewings',
            $source,
            'Fix Rv2: must not assign directly to $this->_reviewings — use setReviewings() after merging'
        );

        // New pattern: load existing, merge, set
        self::assertStringContainsString(
            'getReviewings()',
            $source,
            'Fix Rv2: must call getReviewings() to load existing reviewings before merging'
        );
        self::assertStringContainsString(
            'setReviewings(',
            $source,
            'Fix Rv2: must call setReviewings() to persist the merged result'
        );
    }

    // =========================================================================
    // Bug Rv3 — getComments() cache not keyed by docId
    // =========================================================================

    /**
     * Fix Rv3: $this->_comments was a flat array filled on the first call.
     * Subsequent calls for a different $docId returned the first call's results.
     *
     * The fix uses $this->_comments[$docId] as cache key, so each docId has
     * its own entry.
     */
    public function testGetCommentsSourceUsesDocIdAsCacheKey(): void
    {
        $reflection = new ReflectionMethod(Episciences_Reviewer::class, 'getComments');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        // Old pattern: if (empty($this->_comments)) — cache not keyed by docId
        self::assertStringNotContainsString(
            'empty($this->_comments)',
            $source,
            'Fix Rv3: empty() check on $this->_comments is not keyed by docId — returns stale data for different docIds'
        );

        // New pattern: array_key_exists keyed by docId
        self::assertStringContainsString(
            'array_key_exists($docId, $this->_comments)',
            $source,
            'Fix Rv3: cache must be indexed by $docId to prevent cross-docId contamination'
        );

        self::assertStringContainsString(
            '$this->_comments[$docId]',
            $source,
            'Fix Rv3: comments must be stored and returned as $this->_comments[$docId]'
        );
    }

    // =========================================================================
    // Bug Rv4 — $isLimit missing bool type hint
    // =========================================================================

    /**
     * Fix Rv4: getAssignedPapers() and loadAssignedPapers() declared $isLimit
     * without a type hint while the other three parameters were typed bool.
     */
    public function testGetAssignedPapersIsLimitParameterIsTypedBool(): void
    {
        $method = new ReflectionMethod(Episciences_Reviewer::class, 'getAssignedPapers');
        $param  = $method->getParameters()[3]; // 4th param: $isLimit

        self::assertSame('isLimit', $param->getName());
        self::assertNotNull($param->getType(), 'Fix Rv4: $isLimit must have a type hint');
        self::assertSame('bool', $param->getType()->getName(), 'Fix Rv4: $isLimit must be typed bool');
        self::assertTrue($param->getDefaultValue(), '$isLimit default must be true');
    }

    public function testLoadAssignedPapersIsLimitParameterIsTypedBool(): void
    {
        $method = new ReflectionMethod(Episciences_Reviewer::class, 'loadAssignedPapers');
        $param  = $method->getParameters()[3]; // 4th param: $isLimit

        self::assertSame('isLimit', $param->getName());
        self::assertNotNull($param->getType(), 'Fix Rv4: $isLimit must have a type hint in loadAssignedPapers()');
        self::assertSame('bool', $param->getType()->getName());
    }

    // =========================================================================
    // Bug Rv5 — unassign() uses legacy ifsetor instead of ??
    // =========================================================================

    /**
     * Fix Rv5: unassign() used Ccsd_Tools::ifsetor($params['rvid'], RVID)
     * while assign() already uses $params['rvid'] ?? RVID.
     * Standardized to the ?? operator for consistency.
     */
    public function testUnassignSourceUsesNullCoalescingNotIfsetor(): void
    {
        $reflection = new ReflectionMethod(Episciences_Reviewer::class, 'unassign');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        self::assertStringNotContainsString(
            'ifsetor(',
            $source,
            'Fix Rv5: Ccsd_Tools::ifsetor() must be replaced with ?? operator (consistent with assign())'
        );

        self::assertStringContainsString(
            "?? RVID",
            $source,
            'Fix Rv5: use $params[\'rvid\'] ?? RVID like assign() does'
        );
    }

    // =========================================================================
    // filterInvitations() — pure logic, no DB
    // =========================================================================

    public function testFilterInvitationsWithNoFiltersReturnsAll(): void
    {
        $reviewer = new Episciences_Reviewer();
        $inv1     = $this->makeInvitation(Episciences_User_Invitation::STATUS_PENDING);
        $inv2     = $this->makeInvitation(Episciences_User_Invitation::STATUS_DECLINED);

        $method = new ReflectionMethod(Episciences_Reviewer::class, 'filterInvitations');
        $method->setAccessible(true);

        $result = $method->invoke($reviewer, [10 => $inv1, 20 => $inv2], []);

        self::assertCount(2, $result);
    }

    public function testFilterInvitationsFiltersByStatusArray(): void
    {
        $reviewer = new Episciences_Reviewer();
        $pending  = $this->makeInvitation(Episciences_User_Invitation::STATUS_PENDING);
        $declined = $this->makeInvitation(Episciences_User_Invitation::STATUS_DECLINED);
        $accepted = $this->makeInvitation(Episciences_User_Invitation::STATUS_ACCEPTED);

        $method = new ReflectionMethod(Episciences_Reviewer::class, 'filterInvitations');
        $method->setAccessible(true);

        $result = $method->invoke($reviewer, [10 => $pending, 20 => $declined, 30 => $accepted], [
            'status' => [Episciences_User_Invitation::STATUS_PENDING, Episciences_User_Invitation::STATUS_DECLINED]
        ]);

        self::assertCount(2, $result);
        self::assertArrayHasKey(10, $result);
        self::assertArrayHasKey(20, $result);
        self::assertArrayNotHasKey(30, $result);
    }

    public function testFilterInvitationsFiltersByStatusScalar(): void
    {
        $reviewer = new Episciences_Reviewer();
        $pending  = $this->makeInvitation(Episciences_User_Invitation::STATUS_PENDING);
        $declined = $this->makeInvitation(Episciences_User_Invitation::STATUS_DECLINED);

        $method = new ReflectionMethod(Episciences_Reviewer::class, 'filterInvitations');
        $method->setAccessible(true);

        $result = $method->invoke($reviewer, [10 => $pending, 20 => $declined], [
            'status' => Episciences_User_Invitation::STATUS_PENDING
        ]);

        self::assertCount(1, $result);
        self::assertArrayHasKey(10, $result);
    }

    public function testFilterInvitationsEmptyInputReturnsEmpty(): void
    {
        $reviewer = new Episciences_Reviewer();

        $method = new ReflectionMethod(Episciences_Reviewer::class, 'filterInvitations');
        $method->setAccessible(true);

        $result = $method->invoke($reviewer, [], ['status' => Episciences_User_Invitation::STATUS_PENDING]);

        self::assertSame([], $result);
    }

    // =========================================================================
    // getReviewingsByRvid()
    // =========================================================================

    public function testGetReviewingsByRvidReturnsEmptyWhenNoReviewings(): void
    {
        $reviewer = new Episciences_Reviewer();
        // _reviewings = [] by default

        self::assertSame([], $reviewer->getReviewingsByRvid(1));
    }

    // =========================================================================
    // Ratings
    // =========================================================================

    public function testSetRatingsReturnsFluentInterface(): void
    {
        $reviewer = new Episciences_Reviewer();

        self::assertSame($reviewer, $reviewer->setRatings([]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeInvitation(string $status): Episciences_User_Invitation
    {
        $inv = $this->createMock(Episciences_User_Invitation::class);
        $inv->method('getStatus')->willReturn($status);
        $inv->method('hasExpired')->willReturn(false);

        return $inv;
    }
}
