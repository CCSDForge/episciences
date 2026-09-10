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
     * sortInvitations() is a pure dispatcher: it classifies whatever it is given and must not
     * deduplicate anything.
     *
     * Collapsing an invitation's assignment history into a single entry is getInvitations()'
     * job, and it is covered by
     * {@see Episciences_PapersManager_MergeAssignmentHistoryTest}. Asserting deduplication here
     * would be asserting it in the wrong layer, and would forbid the legitimate case covered by
     * the next test.
     */
    public function testSortInvitationsClassifiesEveryEntryItReceives(): void
    {
        $invitation = $this->makeInvitation(501, Episciences_User_Assignment::STATUS_ACTIVE);

        $invitations = [
            42 => [$invitation, $invitation, $invitation],
        ];

        $result = $this->sortInvitations(Episciences_User_Assignment::STATUS_ACTIVE, $invitations);

        self::assertCount(3, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
    }

    /**
     * A reviewer invited twice on the same paper legitimately owns two invitations, and both must
     * be reported. This is what PR #1121 set out to fix: the previous implementation used
     * array_shift() and only ever kept the first one.
     */
    public function testSortInvitationsReportsEveryInvitationOfARepeatedlyInvitedReviewer(): void
    {
        $firstInvitation = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_DECLINED, '2099-01-01 00:00:00', 7);
        $secondInvitation = $this->makeInvitation(9, Episciences_User_Assignment::STATUS_ACTIVE, '2099-01-01 00:00:00', 7);

        $invitations = [
            7 => [$firstInvitation, $secondInvitation],
        ];

        $result = $this->sortInvitations(
            [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_DECLINED],
            $invitations
        );

        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_DECLINED]);
        self::assertCount(1, $result[Episciences_User_Assignment::STATUS_ACTIVE]);
    }

    public function testSortInvitationsKeepsOneEntryPerDistinctReviewer(): void
    {
        $reviewerA = $this->makeInvitation(1, Episciences_User_Assignment::STATUS_ACTIVE, '2099-01-01 00:00:00', 1);
        $reviewerB = $this->makeInvitation(2, Episciences_User_Assignment::STATUS_ACTIVE, '2099-01-01 00:00:00', 2);

        $invitations = [
            1 => [$reviewerA],
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