<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use ActivityController;
use AdministratepaperController;
use Episciences_Acl;
use unit\modules\ControllerTestCase;

/**
 * The paper pages of the editorial staff (paper management, paper timeline) refuse access through
 * the guards of Episciences_Paper_AccessControlControllerTrait.
 */
final class PaperManagementPagesAccessTest extends ControllerTestCase
{
    private const DOCID = 12;
    private const AUTHOR_UID = 99;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'ActivityController');
        self::loadController('journal', 'AdministratepaperController');
    }

    /**
     * @return iterable<string, array{class-string<\Zend_Controller_Action>}>
     */
    public static function paperManagementPages(): iterable
    {
        yield 'administratepaper/view' => [AdministratepaperController::class];
        yield 'activity/view' => [ActivityController::class];
    }

    protected function setUp(): void
    {
        $this->givenJournal();
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
    }

    /**
     * @dataProvider paperManagementPages
     * @param class-string<\Zend_Controller_Action> $controller
     */
    public function testAuthorIsSentBackToTheirSubmission(string $controller): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_AUTHOR], self::AUTHOR_UID);

        $result = $this->dispatch($controller, 'view', $this->getRequest([], ['id' => self::DOCID]));

        self::assertSame('/paper/view?id=' . self::DOCID, $result->redirectUrl());
    }

    /**
     * @dataProvider paperManagementPages
     * @param class-string<\Zend_Controller_Action> $controller
     */
    public function testGuestEditorOfAnotherPaperIsSentBackToTheirPapers(string $controller): void
    {
        $this->logInAs([Episciences_Acl::ROLE_GUEST_EDITOR]);

        $result = $this->dispatch($controller, 'view', $this->getRequest([], ['id' => self::DOCID]));

        self::assertStringContainsString('/administratepaper/assigned', (string)$result->redirectUrl());
    }
}
