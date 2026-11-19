<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_User_Assignment as Assignment;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;

/**
 * Review request type of a reviewer invitation (savereviewerinvitation, #319): the type must be an enabled type of
 * the journal, that the reviewer does not already hold on the paper. A refused invitation writes nothing.
 */
final class AdministratepaperSaveReviewerInvitationRequestTypeTest extends ControllerTestCase
{
    private const DOCID = 31;
    private const REVIEWER_UID = 4321;
    private const FULL_REPORT = 10;
    private const QUICK_OPINION = 11;
    private const OTHER_JOURNAL_TYPE = 12;
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
            'UID' => 99,
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
            ['id' => self::QUICK_OPINION, 'rvid' => RVID, 'default_type' => 2, 'labels' => '{"en":"Quick opinion"}', 'position' => 2, 'enabled' => 1],
        ]);
    }

    /**
     * The reviewer already holds a quick opinion in progress on the paper
     */
    private function givenQuickOpinionInProgress(): void
    {
        $this->db->respondTo(
            '/SELECT `a`.`UID`, `a`.`REQUEST_TYPE_ID` FROM `USER_ASSIGNMENT`/',
            [['UID' => self::REVIEWER_UID, 'REQUEST_TYPE_ID' => self::QUICK_OPINION]]
        );
    }

    private function invite(string $requestTypeId): DispatchResult
    {
        // the form hash element (salt 'unique') stored in session, as when the invitation form was displayed
        $hash = bin2hex(random_bytes(16));
        $session = new \Zend_Session_Namespace('Zend_Form_Element_Hash_unique_no_csrf_foo');
        $session->hash = $hash;

        $post = ['no_csrf_foo' => $hash] + [
            'referer' => self::REFERER,
            'reviewer' => json_encode([
                'id' => self::REVIEWER_UID,
                'invitation_type' => 1,
                'email' => 'reviewer@example.org',
                'full_name' => 'Reviewer',
                'locale' => 'en',
            ], JSON_THROW_ON_ERROR),
            'request_type_id' => $requestTypeId,
            'subject' => 'Invitation',
            'body' => 'Body',
        ];

        return $this->dispatch(\AdministratepaperController::class, 'savereviewerinvitation', $this->postRequest($post, false, ['docid' => (string)self::DOCID]));
    }

    private function assertRefusedWithoutWriting(DispatchResult $result, string $message): void
    {
        self::assertSame(self::REFERER, $result->redirectUrl());
        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());

        /** @var \Zend_Controller_Action_Helper_FlashMessenger $flash */
        $flash = \Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        self::assertContains($message, $flash->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages());
        $flash->clearCurrentMessages();
    }

    public function testATypeThatIsNotAnEnabledTypeOfTheJournalIsRefused(): void
    {
        $this->assertRefusedWithoutWriting($this->invite((string)self::OTHER_JOURNAL_TYPE), "Ce type de demande n'est pas disponible.");
    }

    public function testATypeTheReviewerAlreadyHoldsOnThePaperIsRefused(): void
    {
        $this->givenQuickOpinionInProgress();

        $this->assertRefusedWithoutWriting(
            $this->invite((string)self::QUICK_OPINION),
            'Ce relecteur a déjà une demande de ce type en cours pour cet article.'
        );
    }

    public function testTheDueDateOfATypeStaysWithinTheRangeOfTheJournal(): void
    {
        // a quick opinion due in two weeks, for a journal that asks for one to nine months
        self::assertSame('2026-12-19', \AdministratepaperController::dateWithinRange('2026-12-03', '2026-12-19', '2027-08-19'));
        self::assertSame('2027-08-19', \AdministratepaperController::dateWithinRange('2028-01-01', '2026-12-19', '2027-08-19'));
        self::assertSame('2027-01-15', \AdministratepaperController::dateWithinRange('2027-01-15', '2026-12-19', '2027-08-19'));
        // no range set by the journal
        self::assertSame('2026-12-03', \AdministratepaperController::dateWithinRange('2026-12-03', null, ''));
    }
}
