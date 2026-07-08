<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper_Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression tests for ActivityController::buildReviewTableBlock() — one row per
 * reviewer, one column per review-event type. Exercised directly via reflection on
 * fabricated PAPER_LOG rows (same shape as Episciences_Paper::getHistory()); no
 * paper/version needs to exist in the DB, since a non-existent DOCID simply makes
 * resolveVersionPdfUrl() degrade to a null PDF link, which these tests don't assert on.
 *
 * @covers ActivityController::buildReviewTableBlock
 */
final class ActivityControllerReviewTableTest extends TestCase
{
    /** Deliberately outside any real paper id range. */
    private const DOC_ID = 999999999;

    private object $controller;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ActivityController.php';

        $this->controller = (new ReflectionClass(\ActivityController::class))->newInstanceWithoutConstructor();

        $this->method = new ReflectionMethod(\ActivityController::class, 'buildReviewTableBlock');
        $this->method->setAccessible(true);
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<string, mixed>
     */
    private function build(array $logs): array
    {
        return $this->method->invoke($this->controller, '1', $logs);
    }

    /**
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function log(string $action, string $date, int $logId, array $detail): array
    {
        return [
            'ACTION' => $action,
            'DATE' => $date,
            'LOGID' => $logId,
            'DOCID' => self::DOC_ID,
            'DETAIL' => json_encode($detail),
        ];
    }

    /**
     * CODE_REVIEWER_INVITATION logs store 'uid' as a sibling of 'detail.user' (see
     * AdministratepaperController::invitereviewerAction()), not nested under
     * detail.user.uid like other review-action logs (written via
     * Episciences_User::toArray()). Both must resolve to the same reviewer row.
     */
    public function testInvitationAndCompletionLogsForSameUidShareOneRow(): void
    {
        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, '2026-01-01 10:00:00', 1, [
                'uid' => 42,
                'user' => ['fullname' => 'Marie Dupont'],
            ]),
            $this->log(Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED, '2026-01-05 10:00:00', 2, [
                'user' => ['fullname' => 'Marie Dupont', 'uid' => 42],
            ]),
        ];

        $block = $this->build($logs);

        self::assertCount(1, $block['REVIEWERS'], 'invitation and completion events for the same uid must merge into a single row');

        $reviewer = reset($block['REVIEWERS']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, $reviewer['events']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED, $reviewer['events']);
        // most recent event (by date) drives the row's highlighted "current status" column
        self::assertSame(Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED, $reviewer['statusAction']);
    }

    /**
     * A reviewer invited without an existing account is represented by a provisional
     * USER_TMP row (Episciences_User_Tmp), whose toArray() uses an 'id' key instead of
     * 'uid' (see ReviewerController::decline()). The invitation log resolves its uid via
     * the sibling 'detail.uid' key, but the decline log only carries it under
     * 'detail.user.id' — both events must still land on the same row.
     */
    public function testInvitationAndDeclineOfUnregisteredReviewerShareOneRow(): void
    {
        $tmpUserId = 7;

        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, '2026-01-01 10:00:00', 1, [
                'tmp_user' => true,
                'uid' => $tmpUserId,
                'user' => ['fullname' => 'Jakub Gajarsky'],
            ]),
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED, '2026-01-05 10:00:00', 2, [
                'user' => ['id' => $tmpUserId, 'fullname' => 'Jakub Gajarsky'],
            ]),
        ];

        $block = $this->build($logs);

        self::assertCount(1, $block['REVIEWERS'], 'invitation and decline of the same unregistered reviewer must merge into a single row');

        $reviewer = reset($block['REVIEWERS']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, $reviewer['events']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED, $reviewer['events']);
    }

    /**
     * Regression for a real production case (paper docid 18291, PAPERID 14979):
     * accepting an invitation sent to a not-yet-registered reviewer mints a brand new
     * real account whose uid has no relationship to the provisional USER_TMP id used at
     * invitation time — confirmed against actual PAPER_LOG rows:
     *   reviewer_invitation:          uid "6371"    (tmp_user, fullname " Cinzia di Giusto")
     *   reviewer_invitation_accepted: uid 1614081   (real account, fullname "Cinzia Di Giusto")
     * Grouping by uid alone (as a first fix attempt did) keeps these on two separate
     * rows forever; only name-based grouping reunites them.
     */
    public function testInvitationAndAcceptanceSurviveTmpToRealAccountUidChange(): void
    {
        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, '2024-12-23 11:28:57', 441837, [
                'tmp_user' => 1,
                'uid' => '6371',
                'user' => ['fullname' => ' Cinzia di Giusto'],
            ]),
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED, '2024-12-25 21:13:28', 442297, [
                'user' => ['fullname' => 'Cinzia Di Giusto', 'uid' => 1614081],
            ]),
        ];

        $block = $this->build($logs);

        self::assertCount(1, $block['REVIEWERS'], 'invitation (tmp uid) and acceptance (new real uid) of the same reviewer must merge into a single row');

        $reviewer = reset($block['REVIEWERS']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, $reviewer['events']);
        self::assertArrayHasKey(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED, $reviewer['events']);
        // the later, better-formed name (from the real account) wins over the raw
        // invitation-time text (stray leading space, inconsistent capitalization)
        self::assertSame('Cinzia Di Giusto', $reviewer['name']);
    }

    public function testTwoDifferentReviewersWithTheSameNameShareOneRow(): void
    {
        // Documents the accepted trade-off (see reviewerMatchKey()'s docblock): since a
        // reviewer's uid is not stable across their whole history, grouping is name-based,
        // so two distinct reviewers who happen to share the exact same displayed name are
        // not distinguishable and will merge.
        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT, '2026-01-01 10:00:00', 1, [
                'user' => ['fullname' => 'Marie Dupont', 'uid' => 1],
            ]),
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT, '2026-01-02 10:00:00', 2, [
                'user' => ['fullname' => 'Marie Dupont', 'uid' => 2],
            ]),
        ];

        $block = $this->build($logs);

        self::assertCount(1, $block['REVIEWERS']);
    }

    public function testTwoReviewersWithoutUidAreKeptSeparateByDistinctNames(): void
    {
        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, '2026-01-01 10:00:00', 1, [
                'user' => ['fullname' => 'Marie Dupont'],
            ]),
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION, '2026-01-02 10:00:00', 2, [
                'user' => ['fullname' => 'Jean Martin'],
            ]),
        ];

        $block = $this->build($logs);

        self::assertCount(2, $block['REVIEWERS']);
    }

    public function testColumnsOnlyIncludeActionsPresentInThisVersion(): void
    {
        $logs = [
            $this->log(Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT, '2026-01-01 10:00:00', 1, [
                'user' => ['fullname' => 'Marie Dupont', 'uid' => 1],
            ]),
        ];

        $block = $this->build($logs);

        self::assertSame([Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT], $block['COLUMNS']);
    }
}
