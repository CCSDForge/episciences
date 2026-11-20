<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Ccsd_View_Helper_Message;
use Episciences\Reviewer\InvitationEligibility;
use Episciences_Acl;
use Episciences_Paper;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_HelperBroker;
use Zend_Session_Namespace;

/**
 * Who may be invited, checked again when the invitation is saved (savereviewerinvitation): the author, the logged
 * user, a reviewer who declared a conflict of interest and a person without an account already invited are refused,
 * with the reason, and nothing is written.
 */
final class AdministratepaperSaveReviewerInvitationEligibilityTest extends ControllerTestCase
{
    private const DOCID = 31;
    private const AUTHOR_UID = 99;
    private const REVIEWER_UID = 4321;
    private const FULL_REPORT = 10;
    private const REFERER = '/administratepaper/view?id=31';

    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => 0,
            'SID' => 0,
            'UID' => self::AUTHOR_UID,
            'STATUS' => Episciences_Paper::STATUS_OK_FOR_REVIEWING,
            'VERSION' => 1,
            'IDENTIFIER' => '1234',
            'REPOID' => 1,
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
            'PUBLICATION_DATE' => null,
        ]]);

        $this->db->respondTo('/FROM `review_request_type`/', [
            ['id' => self::FULL_REPORT, 'rvid' => RVID, 'default_type' => 1, 'labels' => '{"en":"Review report"}', 'position' => 1, 'enabled' => 1],
        ]);
    }

    /**
     * @param array<string, mixed> $reviewer
     */
    private function invite(array $reviewer): DispatchResult
    {
        // the form hash element (salt 'unique') stored in session, as when the invitation form was displayed
        $hash = bin2hex(random_bytes(16));
        $session = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_no_csrf_foo');
        $session->hash = $hash;

        $post = [
            'no_csrf_foo' => $hash,
            'referer' => self::REFERER,
            'reviewer' => json_encode($reviewer + ['full_name' => 'Someone', 'locale' => 'en'], JSON_THROW_ON_ERROR),
            'request_type_id' => (string)self::FULL_REPORT,
            'subject' => 'Invitation',
            'body' => 'Body',
        ];

        return $this->dispatch(AdministratepaperController::class, 'savereviewerinvitation', $this->postRequest($post, false, ['docid' => (string)self::DOCID]));
    }

    private function assertRefusedWithoutWriting(DispatchResult $result, string $reason): void
    {
        self::assertSame(self::REFERER, $result->redirectUrl());
        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());

        /** @var Zend_Controller_Action_Helper_FlashMessenger $flash */
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        self::assertContains(
            AdministratepaperController::invitationRefusalMessage($reason),
            $flash->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages()
        );
        $flash->clearCurrentMessages();
    }

    public function testTheAuthorIsRefused(): void
    {
        $this->assertRefusedWithoutWriting(
            $this->invite(['id' => self::AUTHOR_UID, 'invitation_type' => 2, 'email' => 'author@example.org']),
            InvitationEligibility::REASON_AUTHOR
        );
    }

    public function testTheLoggedUserIsRefused(): void
    {
        $this->assertRefusedWithoutWriting(
            $this->invite(['id' => self::UID, 'invitation_type' => 1, 'email' => 'me@example.org']),
            InvitationEligibility::REASON_SELF
        );
    }

    public function testAReviewerWhoDeclaredAConflictOfInterestIsRefused(): void
    {
        $this->db->respondTo('/paper_conflicts/i', [['by' => self::REVIEWER_UID]]);

        $this->assertRefusedWithoutWriting(
            $this->invite(['id' => self::REVIEWER_UID, 'invitation_type' => 1, 'email' => 'reviewer@example.org']),
            InvitationEligibility::REASON_CONFLICT
        );
    }

    public function testAPersonWithoutAnAccountAlreadyInvitedIsRefused(): void
    {
        // an invitation in progress on the paper, for this address, without an account
        $this->db->respondTo('/COALESCE\(u\.EMAIL, t\.EMAIL/', [['UID' => 501, 'TMP_USER' => 1, 'EMAIL' => 'new@example.org']]);

        $this->assertRefusedWithoutWriting(
            $this->invite(['id' => null, 'invitation_type' => 3, 'email' => 'NEW@example.org', 'lastname' => 'New']),
            InvitationEligibility::REASON_ALREADY_INVITED
        );
    }

    public function testEachReasonHasItsMessage(): void
    {
        $messages = array_map(
            AdministratepaperController::invitationRefusalMessage(...),
            [
                InvitationEligibility::REASON_AUTHOR,
                InvitationEligibility::REASON_SELF,
                InvitationEligibility::REASON_CONFLICT,
                InvitationEligibility::REASON_ALREADY_INVITED,
            ]
        );

        self::assertCount(4, array_unique($messages));
    }
}
