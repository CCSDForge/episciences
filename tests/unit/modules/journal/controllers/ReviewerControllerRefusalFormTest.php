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

    private function responseForm(bool $invalidRefusal, bool $confirmAcceptance = false, ?string $userForm = null, bool $invalidUserForm = false): string
    {
        $invitation = $this->createMock(\Episciences_User_Invitation::class);
        $invitation->method('hasExpired')->willReturn(false);
        $invitation->method('isAnswered')->willReturn(false);
        $invitation->method('isCancelled')->willReturn(false);

        $view = $this->view();
        $view->invitation = $invitation;
        $view->user_form = $userForm;
        $view->refuse_form = '<form id="refuse"></form>';
        $view->invalid_refuse_form = $invalidRefusal;
        $view->invalid_form = $invalidUserForm;
        $view->csrfToken = 'a"token<';
        $view->confirmAcceptance = $confirmAcceptance;

        return $view->render('reviewer/invitation_response_form.phtml');
    }

    public function testInvalidRefusalReopensTheRefusalForm(): void
    {
        self::assertStringContainsString('data-open-on-load="refuse_form"', $this->responseForm(true));
    }

    public function testInvalidAccountFormReopensTheAccountForm(): void
    {
        $html = $this->responseForm(false, false, '<form id="accept_form"></form>', true);

        self::assertStringContainsString('data-open-on-load="accept_form"', $html);
        self::assertMatchesRegularExpression('#<button id="accept-button" type="button"[^>]*aria-expanded="false" aria-controls="accept_form">#', $html);
    }

    public function testRefusalFormStaysClosedByDefault(): void
    {
        $html = $this->responseForm(false);

        self::assertStringNotContainsString('data-open-on-load', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<div id="refuse_form" hidden>', $html);
    }

    public function testDeclineButtonControlsTheRefusalFormNamedByItsHeading(): void
    {
        $html = $this->responseForm(false);

        self::assertMatchesRegularExpression('#<button id="refuse-button" type="button" class="btn btn-default"\s+aria-expanded="false" aria-controls="refuse_form">#', $html);
        self::assertStringContainsString('<h2 class="panel-title" id="refuse-form-title" tabindex="-1">', $html);
        self::assertStringNotContainsString('onclick', $html);
    }

    private function invitationDetails(
        ?\Episciences_User_InvitationAnswer $answer,
        string $expirationDate,
        bool $expired = false,
        ?string $ratingDeadline = null
    ): string {
        $invitation = $this->createMock(\Episciences_User_Invitation::class);
        $sender = $this->createMock(\Episciences_User::class);
        $sender->method('getFullName')->willReturn(self::PAYLOAD);
        $invitation->method('getSender')->willReturn($sender);
        $invitation->method('getSending_date')->willReturn('2026-10-30 10:00:00');
        $invitation->method('getExpiration_date')->willReturn($expirationDate);
        $invitation->method('isAnswered')->willReturn($answer !== null);
        $invitation->method('getAnswer')->willReturn($answer);
        $invitation->method('hasExpired')->willReturn($expired);
        $invitation->method('isCancelled')->willReturn(false);

        $view = $this->view();
        $view->invitation = $invitation;
        $view->rating_deadline = $ratingDeadline;

        return $view->render('reviewer/invitation_details.phtml');
    }

    public function testPendingInvitationDetailsGiveTheDaysLeftAndTheReportDeadline(): void
    {
        $inTenDays = (new \DateTimeImmutable('today'))->modify('+10 days')->format('Y-m-d H:i:s');

        $html = $this->invitationDetails(null, $inTenDays, false, '2027-01-30 00:00:00');

        self::assertStringContainsString('Merci de répondre avant le <strong><time datetime="' . substr($inTenDays, 0, 10) . '">', $html);
        self::assertStringContainsString('<span class="invitation-details-hint">', $html);
        self::assertStringContainsString('(dans 10 jours)', $html);
        self::assertStringContainsString('Si vous acceptez, rapport de relecture attendu avant le <strong><time datetime="2027-01-30">', $html);
        self::assertStringNotContainsString('<img', $html, 'the sender name is escaped');
    }

    public function testPendingInvitationDetailsGiveTheDaysLeftToSendTheReport(): void
    {
        $today = new \DateTimeImmutable('today');
        $inTenDays = $today->modify('+10 days')->format('Y-m-d H:i:s');
        $inSixtyDays = $today->modify('+60 days')->format('Y-m-d');

        $html = $this->invitationDetails(null, $inTenDays, false, $inSixtyDays);

        self::assertMatchesRegularExpression(
            '#rapport de relecture attendu avant le <strong><time datetime="' . $inSixtyDays . '">[^<]+</time></strong>\s*<span class="invitation-details-hint">\(dans 60 jours\)</span>#',
            $html
        );
    }

    public function testAcceptedInvitationDetailsWarnWhenTheReportIsOverdue(): void
    {
        $answer = new \Episciences_User_InvitationAnswer();
        $answer->setAnswer(\Episciences_User_InvitationAnswer::ANSWER_YES);
        $answer->setAnswer_date('2026-01-02 09:00:00');
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        $html = $this->invitationDetails($answer, '2026-01-30 00:00:00', false, $yesterday);

        self::assertStringContainsString('Rapport de relecture attendu avant le', $html);
        self::assertStringContainsString('<span class="text-danger">(dépassée)</span>', $html);
    }

    public function testInvitationDetailsWarnWhenTheAnswerIsDueTomorrow(): void
    {
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');

        $html = $this->invitationDetails(null, $tomorrow);

        self::assertMatchesRegularExpression('#<span class="text-danger">\s*\(demain\)\s*</span>#', $html);
    }

    public function testDeclinedInvitationDetailsShowTheEscapedAnswer(): void
    {
        $answer = new \Episciences_User_InvitationAnswer();
        $answer->setAnswer(\Episciences_User_InvitationAnswer::ANSWER_NO);
        $answer->setAnswer_date('2026-11-02 09:00:00');
        $answer->setDetail(\Episciences_User_InvitationAnswer::DETAIL_SUGGEST, 'Tom & "Jerry"');
        $answer->setDetail(\Episciences_User_InvitationAnswer::DETAIL_COMMENT, "Not my field<script>alert(1)</script>\nSorry");

        $html = $this->invitationDetails($answer, '2026-11-30 00:00:00', false, '2027-01-30 00:00:00');

        self::assertStringContainsString('Invitation déclinée le <time datetime="2026-11-02">', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, 'the sender name is escaped');
        // the answer details are stored HTML-encoded: displayed escaped once, not with visible entities
        self::assertStringContainsString('Relecteur suggéré : Tom &amp; &quot;Jerry&quot;', $html);
        self::assertStringContainsString("Not my fieldalert(1)\nSorry</span>", $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('Motif du refus :', $html);
        self::assertStringNotContainsString('Merci de répondre', $html, 'nothing left to answer');
        self::assertStringNotContainsString('rapport de relecture', $html, 'no report expected after a refusal');
    }

    public function testStoredAnswerDetailsAreDisplayedAsTyped(): void
    {
        $answer = new \Episciences_User_InvitationAnswer();
        $answer->setAnswer(\Episciences_User_InvitationAnswer::ANSWER_NO);
        $answer->setAnswer_date('2026-11-02 09:00:00');
        // as loaded from USER_INVITATION_ANSWER_DETAIL, where the value is already encoded twice
        $answer->setDetails([\Episciences_User_InvitationAnswer::DETAIL_COMMENT => "I&amp;#039;m very busy &amp;lt;b&amp;gt;"]);

        $html = $this->invitationDetails($answer, '2026-11-30 00:00:00');

        self::assertStringContainsString('<span class="invitation-details-comment">I\'m very busy &lt;b&gt;</span>', $html);
    }

    public function testExpiredInvitationDetailsGiveTheExpirationDate(): void
    {
        $html = $this->invitationDetails(null, '2026-09-30 00:00:00', true, '2027-01-30 00:00:00');

        self::assertStringContainsString('Cette invitation a expiré le <time datetime="2026-09-30">', $html);
        self::assertStringNotContainsString('Merci de répondre', $html);
        self::assertStringNotContainsString('rapport de relecture', $html);
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
