<?php

namespace unit\library\Episciences;

use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the private static Episciences_PapersManager::sortInvitations().
 *
 * These do not require a database: sortInvitations() is a pure function of its
 * $status and $invitations arguments (aside from comparing EXPIRATION_DATE to the
 * current time), so it is exercised directly via reflection.
 *
 * @covers Episciences_PapersManager
 */
final class Episciences_PapersManager_SortInvitationsTest extends TestCase
{
    /**
     * @param string|string[]|null $status
     * @param array<int, array<int, array<string, mixed>>> $invitations
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function sortInvitations($status, array $invitations): array
    {
        $method = new ReflectionMethod(Episciences_PapersManager::class, 'sortInvitations');
        $method->setAccessible(true);

        return $method->invoke(null, $status, $invitations);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeInvitation(int $assignmentId, string $assignmentStatus, string $expirationDate = '2099-01-01 00:00:00', int $uid = 1): array
    {
        return [
            'ASSIGNMENT_ID' => $assignmentId,
            'ASSIGNMENT_STATUS' => $assignmentStatus,
            'EXPIRATION_DATE' => $expirationDate,
            'UID' => $uid,
        ];
    }

    /**
     * Regression test for RT#293890 / PR #1121.
     *
     * Episciences_PapersManager::getInvitations() groups USER_ASSIGNMENT history rows
     * per reviewer key, and for a given group it captures the latest row once into
     * $tmp but then pushes that *same* $tmp array once per row in the group (see
     * getInvitations(), the `$invitations[$key][] = $tmp;` line inside the inner
     * foreach). Since USER_ASSIGNMENT is insert-only, a reviewer whose assignment
     * went through several status transitions commonly has more than one row
     * sharing the same INVITATION_AID, so the array passed to sortInvitations()
     * legitimately contains repeated copies of the same invitation.
     *
     * sortInvitations() must collapse these repeated copies back down to a single
     * entry per reviewer/invitation, exactly like the pre-PR #1121 `array_shift()`
     * did. Iterating over every element of the group (as introduced by PR #1121)
     * re-surfaces the duplicates instead.
     */
    public function testSortInvitationsDoesNotDuplicateRepeatedAssignmentHistoryRows(): void
    {
        $sameInvitationRepeatedThreeTimes = $this->makeInvitation(501, Episciences_User_Assignment::STATUS_ACTIVE);

        $invitations = [
            42 => [
                $sameInvitationRepeatedThreeTimes,
                $sameInvitationRepeatedThreeTimes,
                $sameInvitationRepeatedThreeTimes,
            ],
        ];

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_ACTIVE, $invitations);

        self::assertCount(
            1,
            $result[Episciences_User_Assignment::STATUS_ACTIVE],
            'A reviewer with a multi-row assignment history must appear only once in the sorted result, not once per history row.'
        );
    }

    public function testSortInvitationsKeepsOneEntryPerDistinctReviewer(): void
    {
        $reviewerA = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_ACTIVE, '2099-01-01 00:00:00', 1);
        $reviewerB = $this->makeInvitation(2, Episciences_User_Assignment::STATUS_ACTIVE, '2099-01-01 00:00:00', 2);

        $invitations = [
            1 => [$reviewerA, $reviewerA],
            2 => [$reviewerB],
        ];

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_ACTIVE, $invitations);

        self::assertCount(2, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
    }

    public function testSortInvitationsReclassifiesExpiredPendingInvitation(): void
    {
        $expired = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_PENDING, '2000-01-01 00:00:00');

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_EXPIRED, [1 => [$expired]]);

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_EXPIRED]);
        self::assertCount(0, $result[Episciences_User_Assignment::STATUS_PENDING]);
    }

    public function testSortInvitationsKeepsUnexpiredPendingInvitationAsPending(): void
    {
        $pending = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_PENDING, '2099-01-01 00:00:00');

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_PENDING, [1 => [$pending]]);

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_PENDING]);
        self::assertCount(0, $result[Episciences_User_Assignment::STATUS_EXPIRED]);
    }

    public function testSortInvitationsFiltersOutStatusesNotRequested(): void
    {
        $active = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_ACTIVE);
        $declined = $this->makeInvitation(2, Episciences_User_Assignment::STATUS_DECLINED, '2099-01-01 00:00:00', 2);

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_ACTIVE, [1 => [$active], 2 => [$declined]]);

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
        self::assertArrayNotHasKey(Episciences_User_Assignment::STATUS_DECLINED, $result);
    }

    public function testSortInvitationsAcceptsArrayOfStatuses(): void
    {
        $active = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_ACTIVE);
        $inactive = $this->makeInvitation(2, Episciences_User_Assignment::STATUS_INACTIVE, '2099-01-01 00:00:00', 2);

        $result = $this->sortInvitations(
            [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE],
            [1 => [$active], 2 => [$inactive]]
        );

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_INACTIVE]);
    }

    public function testSortInvitationsReturnsEverythingWhenStatusIsNull(): void
    {
        $active = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_ACTIVE);
        $expired = $this->makeInvitation(2, Episciences_User_Assignment::STATUS_PENDING, '2000-01-01 00:00:00', 2);

        $result = $this->sortInvitations(null, [1 => [$active], 2 => [$expired]]);

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_EXPIRED]);
    }
}