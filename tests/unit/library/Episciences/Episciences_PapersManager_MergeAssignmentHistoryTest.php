<?php

namespace unit\library\Episciences;

use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the private static Episciences_PapersManager::mergeAssignmentHistory().
 *
 * That method is where an invitation's assignment history is collapsed into the single row the
 * rest of the application displays. It is a pure function, so no database is needed.
 *
 * Regression coverage for RT#293890 / PR #1121: getInvitations() used to append its aggregated
 * row once per history row instead of once per invitation, which listed a reviewer several times
 * on the paper page. Moving that append out of the loop -- and extracting the aggregation here --
 * is what fixed it.
 *
 * @covers Episciences_PapersManager
 */
final class Episciences_PapersManager_MergeAssignmentHistoryTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $historyRows
     * @return array<string, mixed>
     */
    private function mergeAssignmentHistory(array $historyRows, int $invitationAid): array
    {
        $method = new ReflectionMethod(Episciences_PapersManager::class, 'mergeAssignmentHistory');
        $method->setAccessible(true);

        return $method->invoke(null, $historyRows, $invitationAid);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRow(int $assignmentId, string $status, string $assignmentDate): array
    {
        return [
            'ASSIGNMENT_ID' => $assignmentId,
            'ASSIGNMENT_STATUS' => $status,
            'ASSIGNMENT_DATE' => $assignmentDate,
            'INVITATION_AID' => 500,
            'UID' => 42,
            'TMP_USER' => 0,
        ];
    }

    /**
     * The core of the fix: however many status transitions an invitation went through, it must
     * yield exactly one row.
     */
    public function testMergesAMultiRowHistoryIntoASingleRow(): void
    {
        // Ordered by ASSIGNMENT_DATE DESC, the way getLatestInvitationByDocIdQuery() returns them.
        $historyRows = [
            512 => $this->makeRow(512, Episciences_User_Assignment::STATUS_INACTIVE, '2026-03-10 10:00:00'),
            507 => $this->makeRow(507, Episciences_User_Assignment::STATUS_ACTIVE, '2026-02-05 09:00:00'),
            500 => $this->makeRow(500, Episciences_User_Assignment::STATUS_PENDING, '2026-01-01 08:00:00'),
        ];

        $merged = $this->mergeAssignmentHistory($historyRows, 500);

        self::assertSame(512, $merged['ASSIGNMENT_ID']);
        self::assertSame(
            Episciences_User_Assignment::STATUS_INACTIVE,
            $merged['ASSIGNMENT_STATUS'],
            'The most recent row carries the current state of the invitation.'
        );
    }

    /**
     * Once the invitation has been answered, the date to display is the one of the original
     * assignment -- the moment the reviewer was invited, not the moment they answered.
     */
    public function testTakesTheAssignmentDateFromTheOriginalRow(): void
    {
        $historyRows = [
            507 => $this->makeRow(507, Episciences_User_Assignment::STATUS_ACTIVE, '2026-02-05 09:00:00'),
            500 => $this->makeRow(500, Episciences_User_Assignment::STATUS_PENDING, '2026-01-01 08:00:00'),
        ];

        $merged = $this->mergeAssignmentHistory($historyRows, 500);

        self::assertSame('2026-01-01 08:00:00', $merged['ASSIGNMENT_DATE']);
        self::assertSame(Episciences_User_Assignment::STATUS_ACTIVE, $merged['ASSIGNMENT_STATUS']);
    }

    /**
     * When the original row is absent -- which is what the deduplication guard in getInvitations()
     * currently causes for any history longer than one row -- the date of the latest row is kept.
     */
    public function testKeepsTheLatestDateWhenTheOriginalRowIsMissing(): void
    {
        $historyRows = [
            512 => $this->makeRow(512, Episciences_User_Assignment::STATUS_INACTIVE, '2026-03-10 10:00:00'),
            507 => $this->makeRow(507, Episciences_User_Assignment::STATUS_ACTIVE, '2026-02-05 09:00:00'),
        ];

        $merged = $this->mergeAssignmentHistory($historyRows, 500);

        self::assertSame('2026-03-10 10:00:00', $merged['ASSIGNMENT_DATE']);
    }

    public function testASingleRowHistoryIsReturnedAsIs(): void
    {
        $row = $this->makeRow(500, Episciences_User_Assignment::STATUS_PENDING, '2026-01-01 08:00:00');

        self::assertSame($row, $this->mergeAssignmentHistory([500 => $row], 500));
    }

    public function testAnEmptyHistoryYieldsAnEmptyRow(): void
    {
        self::assertSame([], $this->mergeAssignmentHistory([], 500));
    }
}
