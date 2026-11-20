<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\Workload;
use Episciences_Paper;
use unit\modules\ControllerTestCase;

/**
 * How much a reviewer is already asked in the journal (git #320).
 *
 * Extends ControllerTestCase for its database stub (ofJournal() reads the assignments and invitations).
 */
final class WorkloadTest extends ControllerTestCase
{
    private const CURRENT_DOCID = 100;
    private const NOW = '2026-11-19 12:00:00';
    private const REVIEWER_UID = 7;

    /**
     * @return array<string, mixed>
     */
    private static function assignment(int $docId, string $status, bool $reportDone = false, int $paperStatus = Episciences_Paper::STATUS_BEING_REVIEWED): array
    {
        return ['UID' => self::REVIEWER_UID, 'ITEMID' => $docId, 'PAPER_STATUS' => $paperStatus, 'STATUS' => $status, 'REPORT_DONE' => $reportDone ? 1 : 0];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invitation(int $docId, string $status, string $sent, string $expires = '2026-12-31 00:00:00', int $paperStatus = Episciences_Paper::STATUS_BEING_REVIEWED): array
    {
        return ['UID' => self::REVIEWER_UID, 'ITEMID' => $docId, 'PAPER_STATUS' => $paperStatus, 'STATUS' => $status, 'SENDING_DATE' => $sent, 'EXPIRATION_DATE' => $expires];
    }

    private static function workload(array $assignments, array $invitations): Workload
    {
        $workloads = Workload::fromRows($assignments, $invitations, self::CURRENT_DOCID, self::NOW);
        self::assertArrayHasKey(self::REVIEWER_UID, $workloads);

        return $workloads[self::REVIEWER_UID];
    }

    public function testAnAcceptedReviewNotYetCompletedIsInProgress(): void
    {
        $workload = self::workload([
            self::assignment(1, 'active'),
            self::assignment(2, 'active', true),
            self::assignment(3, 'declined'),
            self::assignment(4, 'inactive'),
        ], []);

        self::assertSame(1, $workload->getReviewsInProgress());
    }

    public function testOnlyPapersStillBeingReviewedCount(): void
    {
        $workload = self::workload([
            self::assignment(1, 'active', false, Episciences_Paper::STATUS_PUBLISHED),
            self::assignment(2, 'active', false, Episciences_Paper::STATUS_REFUSED),
            self::assignment(3, 'active', false, Episciences_Paper::STATUS_OBSOLETE),
        ], [
            self::invitation(4, 'pending', '2026-11-10 10:00:00', '2026-12-31 00:00:00', Episciences_Paper::STATUS_PUBLISHED),
        ]);

        self::assertSame(0, $workload->getReviewsInProgress());
        self::assertSame(0, $workload->getPendingInvitations());
        // the latest invitation is still the latest, whatever became of the paper
        self::assertSame('2026-11-10 10:00:00', $workload->getLastInvitationDate());
    }

    public function testThePapersOfTheReviewsAndInvitationsInProgressAreKept(): void
    {
        $review = self::assignment(1, 'active') + ['DEADLINE' => '2026-12-15 00:00:00'];
        $workload = self::workload([$review, self::assignment(2, 'active', true)], [
            self::invitation(3, 'pending', '2026-11-15 09:00:00'),
            self::invitation(4, 'declined', '2026-11-18 09:00:00'),
        ]);

        self::assertSame([
            ['docid' => 1, 'kind' => Workload::PAPER_REVIEW, 'date' => '2026-12-15 00:00:00'],
            ['docid' => 3, 'kind' => Workload::PAPER_INVITATION, 'date' => '2026-11-15 09:00:00'],
        ], $workload->getPapers());
        // the latest invitation, declined, is about paper 4
        self::assertSame(4, $workload->getLastInvitationDocId());
        self::assertSame([1, 3], Workload::paperDocIds([self::REVIEWER_UID => $workload, 'email:x@example.org' => new Workload(0, 1, null, null, [['docid' => 3, 'kind' => Workload::PAPER_INVITATION, 'date' => null]])]));
    }

    public function testThePaperBeingHandledIsLeftOut(): void
    {
        $workload = self::workload(
            [self::assignment(self::CURRENT_DOCID, 'active')],
            [self::invitation(self::CURRENT_DOCID, 'pending', '2026-11-18 09:00:00')]
        );

        self::assertSame(0, $workload->getReviewsInProgress());
        self::assertSame(0, $workload->getPendingInvitations());
    }

    public function testAPendingInvitationIsCountedUntilItExpires(): void
    {
        $workload = self::workload([], [
            self::invitation(1, 'pending', '2026-11-15 09:00:00'),
            self::invitation(2, 'pending', '2026-10-01 09:00:00', '2026-10-15 09:00:00'),
            self::invitation(3, 'accepted', '2026-09-01 09:00:00'),
        ]);

        self::assertSame(1, $workload->getPendingInvitations());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function answerProvider(): array
    {
        return [
            'accepted' => ['accepted', '2026-12-31 00:00:00', Workload::ANSWER_ACCEPTED],
            'declined' => ['declined', '2026-12-31 00:00:00', Workload::ANSWER_DECLINED],
            'cancelled' => ['cancelled', '2026-12-31 00:00:00', Workload::ANSWER_CANCELLED],
            'not answered yet' => ['pending', '2026-12-31 00:00:00', Workload::ANSWER_PENDING],
            'expired without answer' => ['pending', '2026-11-01 00:00:00', Workload::ANSWER_EXPIRED],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('answerProvider')]
    public function testTheLatestInvitationAndItsAnswer(string $status, string $expires, string $answer): void
    {
        $workload = self::workload([], [
            self::invitation(1, 'declined', '2026-05-01 09:00:00'),
            self::invitation(2, $status, '2026-10-20 09:00:00', $expires),
            self::invitation(3, 'accepted', '2026-01-01 09:00:00'),
        ]);

        self::assertSame('2026-10-20 09:00:00', $workload->getLastInvitationDate());
        self::assertSame($answer, $workload->getLastInvitationAnswer());
    }

    public function testAReviewerNeverInvitedHasAnEmptyWorkload(): void
    {
        $workload = self::workload([self::assignment(1, 'inactive')], []);

        self::assertTrue($workload->isEmpty());
        self::assertSame(
            ['reviews_in_progress' => 0, 'pending_invitations' => 0, 'last_invitation_date' => null, 'last_invitation_answer' => null],
            $workload->toArray()
        );
        self::assertSame([], Workload::fromRows([], [], self::CURRENT_DOCID, self::NOW));
    }

    public function testPeopleInvitedWithoutAnAccountAreGroupedByAddress(): void
    {
        $tmp = static fn(int $tmpId, string $email, int $docId, string $status, string $sent): array => [
            'UID' => $tmpId, 'TMP_USER' => 1, 'TMP_EMAIL' => $email, 'ITEMID' => $docId,
            'PAPER_STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED, 'STATUS' => $status,
            'SENDING_DATE' => $sent, 'EXPIRATION_DATE' => '2026-12-31 00:00:00',
        ];

        $workloads = Workload::fromRows([], [
            // each invitation without an account creates a new temporary user
            $tmp(501, 'Jane.Doe@Example.org', 1, 'pending', '2026-11-01 09:00:00'),
            $tmp(502, ' jane.doe@example.org', 2, 'declined', '2026-11-10 09:00:00'),
            // same id as an account, but not the same person
            $tmp(self::REVIEWER_UID, 'other@example.org', 3, 'pending', '2026-11-02 09:00:00'),
            $tmp(503, '', 4, 'pending', '2026-11-03 09:00:00'),
        ], self::CURRENT_DOCID, self::NOW);

        self::assertSame(['email:jane.doe@example.org', 'email:other@example.org'], array_keys($workloads));
        $jane = $workloads['email:jane.doe@example.org'];
        self::assertSame(1, $jane->getPendingInvitations());
        self::assertSame('2026-11-10 09:00:00', $jane->getLastInvitationDate());
        self::assertSame(Workload::ANSWER_DECLINED, $jane->getLastInvitationAnswer());

        [$byUid, $byEmailHash] = Workload::splitByAccount($workloads + [self::REVIEWER_UID => new Workload(1)]);
        self::assertSame([self::REVIEWER_UID], array_keys($byUid));
        // the page never lists the addresses: only their hash, which the address typed for a new reviewer gives too
        self::assertSame($jane, $byEmailHash[hash('sha256', 'jane.doe@example.org')]);
        self::assertCount(2, $byEmailHash);
    }

    public function testTheWholeJournalIsReadWithTwoQueries(): void
    {
        $this->db
            ->respondTo('/FROM `USER_INVITATION`/', [self::invitation(1, 'pending', '2026-11-15 09:00:00')])
            ->respondTo('/FROM `USER_ASSIGNMENT`/', [self::assignment(2, 'active')]);

        $workloads = Workload::ofJournal(RVID, self::CURRENT_DOCID, null, self::NOW);

        self::assertCount(2, $this->db->selects());
        [$assignments, $invitations] = $this->db->selects();
        self::assertStringContainsString('RVID = ' . RVID, $assignments);
        self::assertStringContainsString('TMP_USER = 0', $assignments);
        self::assertMatchesRegularExpression('/GROUP BY `ITEMID`,\s*`UID`/', $assignments);
        self::assertStringContainsString('a.RVID = ' . RVID, $invitations);
        self::assertStringContainsString('GROUP BY `i`.`AID`', $invitations);
        self::assertStringNotContainsString('TMP_USER = 0', $invitations);
        self::assertStringContainsString('LEFT JOIN `USER_TMP` AS `t` ON a.TMP_USER = 1 AND t.ID = a.UID', $invitations);

        self::assertSame(1, $workloads[self::REVIEWER_UID]->getReviewsInProgress());
        self::assertSame(1, $workloads[self::REVIEWER_UID]->getPendingInvitations());
    }
}
