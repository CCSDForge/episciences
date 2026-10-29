<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper_Logger;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rendering of the paper log modal (every user-controlled field must be escaped) and of the
 * invitation response form (an invalid refusal reopens the refusal form).
 */
final class ReviewerControllerRefusalFormTest extends TestCase
{
    private const SCRIPTS = '/modules/journal/views/scripts/';

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private mixed $previousLocale = null;

    protected function setUp(): void
    {
        $this->previousLocale = \Zend_Registry::isRegistered('Zend_Locale') ? \Zend_Registry::get('Zend_Locale') : null;
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set(
            'Zend_Translate',
            new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr'])
        );
    }

    protected function tearDown(): void
    {
        if ($this->previousLocale !== null) {
            \Zend_Registry::set('Zend_Locale', $this->previousLocale);
        }
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPTS);
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        return $view;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function loggedActionProvider(): array
    {
        return [
            'invitation' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION],
            'invitation accepted' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED],
            'invitation declined' => [Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED],
            'abandon' => [Episciences_Paper_Logger::CODE_ABANDON_PUBLICATION_PROCESS],
            'continue' => [Episciences_Paper_Logger::CODE_CONTINUE_PUBLICATION_PROCESS],
        ];
    }

    /**
     * @dataProvider loggedActionProvider
     */
    public function testLogModalEscapesEveryUserControlledField(string $action): void
    {
        $view = $this->view();
        $view->user = ['fullname' => self::PAYLOAD];
        $view->log = [
            'action' => $action,
            'uid' => 1,
            'date' => '2026-01-01 10:00:00',
            'detail' => [
                'user' => ['fullname' => self::PAYLOAD, 'username' => self::PAYLOAD, 'email' => self::PAYLOAD],
                'reviewer_suggestion' => self::PAYLOAD,
                'refusal_reason' => self::PAYLOAD,
                'lastStatus' => 0,
            ],
        ];

        $html = $view->render('administratepaper/log.phtml');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, 'the values are displayed, escaped');
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function linkedInvitationLog(array $detail): string
    {
        $view = $this->view();
        $view->user = ['fullname' => 'Linked Account'];
        $view->log = [
            'action' => Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_LINKED,
            'uid' => 1,
            'date' => '2026-01-01 10:00:00',
            'detail' => $detail,
        ];

        return $view->render('administratepaper/log.phtml');
    }

    public function testLinkedInvitationLogEscapesTheInvitedAndLinkedAccounts(): void
    {
        $html = $this->linkedInvitationLog([
            'invited' => ['uid' => 200, 'fullname' => self::PAYLOAD, 'email' => self::PAYLOAD, 'tmp' => false],
            'linked_to' => ['uid' => 100, 'fullname' => self::PAYLOAD, 'email' => self::PAYLOAD],
            'automatic' => false,
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertSame(4, substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'), 'both names and both addresses are displayed, escaped');
    }

    public function testLinkedInvitationLogShowsBothAccountsAndAConfirmedLink(): void
    {
        $html = $this->linkedInvitationLog([
            'invited' => ['uid' => 200, 'fullname' => 'Invited Reviewer', 'email' => 'invited@example.org', 'tmp' => false],
            'linked_to' => ['uid' => 100, 'fullname' => 'Other Account', 'email' => 'other@example.org'],
            'automatic' => false,
        ]);

        self::assertStringContainsString('Invited Reviewer', $html);
        self::assertStringContainsString('&lt;invited@example.org&gt;', $html);
        self::assertStringContainsString('Other Account', $html);
        self::assertStringContainsString('&lt;other@example.org&gt;', $html);
        self::assertStringNotContainsString('relecteur sans compte', $html);
        self::assertMatchesRegularExpression('#adresses e-mail identiques\)</td>\s*<td class="small">Non</td>#', $html);
    }

    public function testLinkedInvitationLogFlagsATemporaryReviewerAndAnAutomaticLink(): void
    {
        $html = $this->linkedInvitationLog([
            'invited' => ['uid' => 300, 'fullname' => 'Tmp Reviewer', 'email' => 'tmp@example.org', 'tmp' => true],
            'linked_to' => ['uid' => 100, 'fullname' => 'Other Account', 'email' => 'tmp@example.org'],
            'automatic' => true,
        ]);

        self::assertStringContainsString('(relecteur sans compte)', $html);
        self::assertMatchesRegularExpression('#adresses e-mail identiques\)</td>\s*<td class="small">Oui</td>#', $html);
    }

    public function testLinkedInvitationLogWithAnIncompleteDetailRendersWithoutNotice(): void
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            $html = $this->linkedInvitationLog([]);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('Invitation envoyée à', $html);
        self::assertStringNotContainsString('&lt;', $html, 'no address bracket without an address');
    }

    private function responseForm(bool $invalidRefusal, bool $confirmAcceptance = false): string
    {
        $invitation = $this->createMock(\Episciences_User_Invitation::class);
        $invitation->method('hasExpired')->willReturn(false);
        $invitation->method('isAnswered')->willReturn(false);
        $invitation->method('isCancelled')->willReturn(false);

        $view = $this->view();
        $view->invitation = $invitation;
        $view->user_form = null;
        $view->refuse_form = '<form id="refuse"></form>';
        $view->invalid_refuse_form = $invalidRefusal;
        $view->csrfToken = 'a"token<';
        $view->confirmAcceptance = $confirmAcceptance;

        return $view->render('reviewer/invitation_response_form.phtml');
    }

    public function testInvalidRefusalReopensTheRefusalForm(): void
    {
        self::assertStringContainsString('refuseInvitation();', $this->responseForm(true));
    }

    public function testRefusalFormStaysClosedByDefault(): void
    {
        $html = $this->responseForm(false);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('id="refuse_form" style="display: none"', $html);
    }

    public function testAcceptFormPostsTheEscapedSessionToken(): void
    {
        $html = $this->responseForm(false);

        self::assertStringContainsString('<input type="hidden" name="csrf_token" value="a&quot;token&lt;">', $html);
        self::assertStringContainsString('name="submitaccept"', $html);
    }

    public function testReturnFromTheLoginPageAsksToConfirm(): void
    {
        self::assertStringContainsString('Vous êtes connecté : confirmez votre acceptation ci-dessous.', $this->responseForm(false, true));
        self::assertStringNotContainsString('confirmez votre acceptation', $this->responseForm(false));
    }

    public function testLinkFormPostsTheSessionTokenToTheCurrentUrl(): void
    {
        $previousStorage = \Zend_Auth::getInstance()->getStorage();
        \Zend_Auth::getInstance()->setStorage(new \Zend_Auth_Storage_NonPersistent());
        \Zend_Auth::getInstance()->getStorage()->write(new \Episciences_User(['UID' => 100, 'EMAIL' => 'logged@example.org', 'SCREEN_NAME' => 'Logged']));
        try {
            $view = $this->view();
            $view->isAlreadyInvited = false;
            $view->csrfToken = 'link-token';
            $html = $view->render('reviewer/linkInvitationForm.phtml');
        } finally {
            \Zend_Auth::getInstance()->setStorage($previousStorage);
        }

        self::assertStringContainsString('<input type="hidden" name="csrf_token" value="link-token">', $html);
        self::assertDoesNotMatchRegularExpression('#<form[^>]*\saction=#', $html, 'posted to the current URL, which holds the link token');
    }
}
