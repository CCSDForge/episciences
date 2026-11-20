<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Episciences\Reviewer\InvitationEligibility;
use Episciences\Reviewer\PreviousVersionReviewers;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Rating_Report;
use ReflectionMethod;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Invitation page (git #1387): the reviewers of the previous versions, each with what became of their request, and
 * whether they may be invited again on this version, once per person.
 */
final class AdministratepaperPreviousReviewersTest extends ControllerTestCase
{
    private const PAPER_ID = 10;
    private const CURRENT_DOCID = 30;
    private const AUTHOR_UID = 99;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewer(int $docId, int $uid, string $status, ?int $report = null, int $tmp = 0, string $email = ''): array
    {
        return [
            'ITEMID' => $docId, 'UID' => $uid, 'TMP_USER' => $tmp, 'STATUS' => $status, 'WHEN' => '2026-02-01 10:00:00',
            'REPORT_STATUS' => $report, 'REPORT_DATE' => null, 'FIRSTNAME' => 'F' . $uid, 'LASTNAME' => 'L' . $uid,
            'EMAIL' => $email !== '' ? $email : "r$uid@example.org", 'SCREEN_NAME' => "R$uid", 'USERNAME' => "r$uid",
            'LANGUEID' => 'en',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->db
            // versions 20 then 10 before the current one
            ->respondTo('/FROM `PAPERS`/', [
                ['DOCID' => 20, 'VERSION' => 2, 'REPOID' => 1, 'WHEN' => '2026-04-01 10:00:00'],
                ['DOCID' => 10, 'VERSION' => 1, 'REPOID' => 1, 'WHEN' => '2026-01-01 10:00:00'],
            ])
            // the conflicts of interest declared with the paper
            ->respondTo('/paper_conflicts/i', [])
            // the invitations in progress on the current version: reviewer 2 already invited again
            ->respondTo('/ITEMID = ' . self::CURRENT_DOCID . '/', [['UID' => 2, 'TMP_USER' => 0, 'EMAIL' => 'r2@example.org']])
            ->respondTo('/ITEMID IN \(/', [
                self::reviewer(20, 1, 'active', Episciences_Rating_Report::STATUS_COMPLETED),
                self::reviewer(20, 2, 'active', Episciences_Rating_Report::STATUS_COMPLETED),
                self::reviewer(10, 1, 'active', Episciences_Rating_Report::STATUS_COMPLETED),
                self::reviewer(10, 3, 'declined'),
                self::reviewer(10, 501, 'pending', null, 1, 'tmp@example.org'),
                self::reviewer(10, self::AUTHOR_UID, 'declined'),
            ]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(self::PAPER_ID);
        $paper->method('getDocid')->willReturn(self::CURRENT_DOCID);
        $paper->method('getRvid')->willReturn(RVID);
        $paper->method('getUid')->willReturn(self::AUTHOR_UID);
        $paper->method('getWhen')->willReturn('2026-06-01 10:00:00');

        $method = new ReflectionMethod(AdministratepaperController::class, 'previousReviewersRows');
        $controller = new AdministratepaperController($this->getRequest(), new Zend_Controller_Response_HttpTestCase());

        /** @var list<array<string, mixed>> */
        return $method->invoke($controller, $paper);
    }

    public function testEachPersonMayBeInvitedAgainOnceWithTheReasonWhenTheyMayNot(): void
    {
        $rows = $this->rows();

        self::assertSame(
            [
                [20, 1, true, null],
                [20, 2, false, InvitationEligibility::REASON_ALREADY_INVITED],
                // reviewer 1 is already listed on version 2
                [10, 1, false, null],
                [10, 3, true, null],
                [10, 501, true, null],
                [10, self::AUTHOR_UID, false, InvitationEligibility::REASON_AUTHOR],
            ],
            array_map(static fn(array $row): array => [$row['docid'], $row['uid'], $row['can_reinvite'], $row['refusal']], $rows)
        );
        self::assertSame(PreviousVersionReviewers::STATUS_REPORT_COMPLETED, $rows[0]['status']);
    }

    public function testTheInvitationFormGetsThePersonAndThePreviousVersion(): void
    {
        $rows = $this->rows();

        self::assertSame([
            'id' => 1, 'email' => 'r1@example.org', 'full_name' => 'F1 L1', 'firstname' => 'F1', 'lastname' => 'L1',
            'screen_name' => 'R1', 'user_name' => 'r1', 'locale' => 'en', 'invitation_type' => 1, 'reinvitation_of' => 20,
        ], $rows[0]['user']);
        // a person without an account is invited again as such, without an account id
        self::assertNull($rows[4]['user']['id']);
        self::assertSame(3, $rows[4]['user']['invitation_type']);
        self::assertSame(10, $rows[4]['user']['reinvitation_of']);
    }
}
