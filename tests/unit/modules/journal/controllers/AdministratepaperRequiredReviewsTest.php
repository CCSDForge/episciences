<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Ccsd_View_Helper_Message;
use Episciences_Acl;
use Episciences_Review;
use PHPUnit\Framework\Attributes\DataProvider;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Form_Element_Hash;

/**
 * Accepting, publishing a paper or asking for a revision: as in the status menu, an editor may not do it
 * before the minimum number of reviews of the version is reached (git #239).
 */
final class AdministratepaperRequiredReviewsTest extends ControllerTestCase
{
    private const DOCID = 12;
    private const AUTHOR_UID = 99;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    protected function setUp(): void
    {
        $this->givenJournal([
            Episciences_Review::SETTING_REQUIRED_REVIEWERS => '2',
            Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS => '1',
            Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS => '1',
            Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS => '1',
        ]);
        // no completed review yet
        $this->db->respondTo('/FROM `REVIEWER_REPORT`/', []);
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => 0,
            'SID' => 0,
            'UID' => self::AUTHOR_UID,
            'STATUS' => 0,
            'VERSION' => 1,
            'IDENTIFIER' => '1234',
            'REPOID' => 1,
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
        ]]);

        $this->flashMessenger()->clearCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR);
    }

    /**
     * @return array<string, array{string, string, array<string, string>, string}>
     */
    public static function decisionProvider(): array
    {
        return [
            'accept' => ['accept', 'csrf_accept_' . self::DOCID, [], 'accepter cet article'],
            'publish' => ['publish', 'csrf_publish_' . self::DOCID, [], 'publier cet article'],
            'minor revision' => ['revision', 'csrf_revision_minor_' . self::DOCID, ['type' => 'minor'], 'demande de modifications'],
            'major revision' => ['revision', 'csrf_revision_major_' . self::DOCID, ['type' => 'major'], 'demande de modifications'],
        ];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('decisionProvider')]
    public function testEditorIsSentBackToThePaperBeforeTheMinimum(string $action, string $token, array $params, string $message): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $request = $this->postRequest($this->uniqueFormToken($token), false, ['id' => self::DOCID] + $params);

        $result = $this->dispatch(AdministratepaperController::class, $action, $request);

        self::assertSame('/administratepaper/view/id/' . self::DOCID, $result->redirectUrl());
        self::assertSame([], $this->db->updates(), 'the paper must not change');
        self::assertSame([], $this->db->inserts(), 'nothing must be recorded');

        $errors = array_values($this->flashMessenger()->getCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR));
        self::assertCount(1, $errors);
        self::assertStringContainsString($message, $errors[0]);
        self::assertStringContainsString('(2)', $errors[0]);
    }

    /**
     * Token of the decision forms, which are checked with the "unique" salt.
     *
     * @return array<string, string>
     */
    private function uniqueFormToken(string $name): array
    {
        $hash = new Zend_Form_Element_Hash($name, ['salt' => 'unique']);
        $hash->initCsrfToken();

        return [$name => $hash->getHash()];
    }

    private function flashMessenger(): Zend_Controller_Action_Helper_FlashMessenger
    {
        /** @var Zend_Controller_Action_Helper_FlashMessenger */
        return Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
    }
}
