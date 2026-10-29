<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\User\UserNotFoundException;
use Episciences_Paper;
use Episciences_Paper_Logger;
use Episciences_User;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session_Namespace;

/**
 * Linking of a reviewer invitation to the account of the logged-in user.
 *
 * An invitation sent to an address can only be linked automatically to an account holding that
 * very address; any other account has to confirm, and nothing is written before it does.
 * An author of the paper can never link the invitation. Every link is logged in the paper history.
 * checkAndProcessLinkedInvitation() is run for real with mocked invitation, assignment and paper.
 */
final class ReviewerControllerLinkedInvitationTest extends TestCase
{
    private const LOGGED_UID = 100;
    private const INVITED_UID = 200;
    private const AUTHOR_UID = 300;
    private const SESSION = 'linked_invitation_test';
    private const CSRF_TOKEN = 'linked-invitation-test-token';

    private mixed $previousAuthStorage;
    private \ReviewerController $controller;

    /** @var MockObject&Episciences_Paper */
    private $paper;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php';

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        (new Zend_Session_Namespace(self::SESSION))->unsetAll();

        $this->controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \ReviewerController {
            protected function getSession(): Zend_Session_Namespace
            {
                return new Zend_Session_Namespace('linked_invitation_test');
            }
        };

        $this->paper = $this->paper();
    }

    /**
     * @return MockObject&Episciences_Paper
     */
    private function paper(bool $loggedIsCoAuthor = false)
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::AUTHOR_UID);
        $paper->method('isCoAuthorByUid')->willReturnCallback(
            static fn(int $uid): bool => $loggedIsCoAuthor && $uid === self::LOGGED_UID
        );

        return $paper;
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
        $_POST = []; // setPost() writes the global
        $appSession = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($appSession->csrfToken);
        $this->controller->getHelper('FlashMessenger')->setNamespace(\Ccsd_View_Helper_Message::MSG_ERROR)->clearCurrentMessages();
    }

    private function logIn(string $email = 'logged@example.org', int $uid = self::LOGGED_UID): void
    {
        Zend_Auth::getInstance()->getStorage()->write(
            new Episciences_User(['UID' => $uid, 'EMAIL' => $email, 'FIRSTNAME' => 'Logged', 'LASTNAME' => 'Account'])
        );
    }

    /**
     * @return MockObject&Episciences_User_Invitation
     */
    private function invitation(bool $expired = false, bool $answered = false, bool $cancelled = false)
    {
        $invitation = $this->createMock(Episciences_User_Invitation::class);
        $invitation->method('hasExpired')->willReturn($expired);
        $invitation->method('isAnswered')->willReturn($answered);
        $invitation->method('isCancelled')->willReturn($cancelled);
        $invitation->method('getId')->willReturn(7);

        return $invitation;
    }

    /**
     * @return MockObject&Episciences_User_Assignment
     */
    private function assignment(?Episciences_User $recipient = null, ?\Throwable $resolveError = null, int $uid = self::INVITED_UID)
    {
        $assignment = $this->createMock(Episciences_User_Assignment::class);
        $assignment->method('getUid')->willReturn($uid);
        $assignment->method('getFrom_uid')->willReturn(null);
        if ($resolveError !== null) {
            $assignment->method('resolveFromUser')->willThrowException($resolveError);
        } elseif ($recipient !== null) {
            $assignment->method('resolveFromUser')->willReturn($recipient);
        }

        return $assignment;
    }

    /**
     * @return array<string, mixed>
     */
    private function check(Episciences_User_Invitation $invitation, Episciences_User_Assignment $assignment): array
    {
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);

        return $method->invoke($this->controller, new Zend_Controller_Request_HttpTestCase(), $invitation, $assignment, $this->paper);
    }

    public function testNothingHappensForAnonymousVisitors(): void
    {
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'logged@example.org']));
        $assignment->expects(self::never())->method('save');

        self::assertSame([], $this->check($this->invitation(), $assignment));
    }

    /**
     * @return array<string, array{bool, bool, bool}>
     */
    public static function closedInvitationProvider(): array
    {
        return [
            'expired' => [true, false, false],
            'answered' => [false, true, false],
            'cancelled' => [false, false, true],
        ];
    }

    /**
     * @dataProvider closedInvitationProvider
     */
    public function testExpiredAnsweredOrCancelledInvitationIsNotLinked(bool $expired, bool $answered, bool $cancelled): void
    {
        $this->logIn();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'logged@example.org']));
        $assignment->expects(self::never())->method('save');

        self::assertSame([], $this->check($this->invitation($expired, $answered, $cancelled), $assignment));
    }

    public function testInvitationOfTheLoggedAccountItselfIsLeftAlone(): void
    {
        $this->logIn();
        $assignment = $this->assignment(null, null, self::LOGGED_UID);
        $assignment->expects(self::never())->method('save');

        self::assertSame([], $this->check($this->invitation(), $assignment));
    }

    public function testRecipientThatCannotBeResolvedIsNeverLinked(): void
    {
        $this->logIn();
        $assignment = $this->assignment(null, new UserNotFoundException(self::INVITED_UID));
        $assignment->expects(self::never())->method('save');

        set_error_handler(static fn(): bool => true, E_USER_WARNING); // the failure is reported with a warning
        try {
            $result = $this->check($this->invitation(), $assignment);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['isPreLinked' => false], $result);
    }

    public function testExactEmailMatchLinksTheInvitationToTheLoggedAccount(): void
    {
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->expects(self::once())->method('setFrom_uid')->with(self::INVITED_UID);
        $assignment->expects(self::once())->method('setUid')->with(self::LOGGED_UID);
        $assignment->expects(self::once())->method('save')->willReturn(true);

        self::assertSame(['isAlreadyLinked' => true], $this->check($this->invitation(), $assignment));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nearlyMatchingEmailProvider(): array
    {
        return [
            'different case' => ['Same@Example.org', 'same@example.org'],
            'other address' => ['other@example.org', 'same@example.org'],
            'empty invited address' => ['', 'same@example.org'],
        ];
    }

    /**
     * @dataProvider nearlyMatchingEmailProvider
     */
    public function testAnotherAddressNeverLinksAutomaticallyAndNeedsConfirmation(string $invitedEmail, string $loggedEmail): void
    {
        $this->logIn($loggedEmail);
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => $invitedEmail]));
        $assignment->expects(self::never())->method('save');
        $assignment->expects(self::never())->method('setUid');

        $result = $this->check($this->invitation(), $assignment);

        self::assertSame(['isPreLinked' => true, 'decision' => null], $result);
        self::assertTrue((new Zend_Session_Namespace(self::SESSION))->linkedInvitationIds[7]['isPreLinked']);
    }

    private function confirmationRequest(): void
    {
        $this->logIn('logged@example.org');
        $session = new Zend_Session_Namespace(self::SESSION);
        $session->linkedInvitationIds = [7 => ['isPreLinked' => true]];
        $appSession = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $appSession->csrfToken = self::CSRF_TOKEN;
    }

    public function testAcceptedConfirmationLinksTheInvitationAndClearsTheMarker(): void
    {
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->expects(self::once())->method('setUid')->with(self::LOGGED_UID);
        $assignment->expects(self::once())->method('save')->willReturn(true);

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost(['linkInvitation' => 'acceptToLink', 'csrf_token' => self::CSRF_TOKEN]);
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, $request, $this->invitation(), $assignment, $this->paper);

        self::assertSame('acceptToLink', $result['decision']);
        self::assertArrayNotHasKey(7, (array)(new Zend_Session_Namespace(self::SESSION))->linkedInvitationIds);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidCsrfTokenProvider(): array
    {
        return [
            'no token' => [[]],
            'wrong token' => [['csrf_token' => 'forged']],
        ];
    }

    /**
     * @param array<string, string> $token
     * @dataProvider invalidCsrfTokenProvider
     */
    public function testConfirmationWithoutAValidTokenLinksNothingAndKeepsTheMarker(array $token): void
    {
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->expects(self::never())->method('setUid');
        $assignment->expects(self::never())->method('save');

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost(['linkInvitation' => 'acceptToLink'] + $token);
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, $request, $this->invitation(), $assignment, $this->paper);

        self::assertNull($result['decision'], 'the confirmation form is displayed again');
        self::assertArrayHasKey(7, (array)(new Zend_Session_Namespace(self::SESSION))->linkedInvitationIds);
        self::assertCount(1, $this->controller->getHelper('FlashMessenger')->setNamespace(\Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages());
    }

    public function testNoDecisionKeepsTheInvitationUnlinkedAndTheMarker(): void
    {
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->expects(self::never())->method('save');

        $result = $this->check($this->invitation(), $assignment);

        self::assertNull($result['decision']);
        self::assertArrayHasKey(7, (array)(new Zend_Session_Namespace(self::SESSION))->linkedInvitationIds);
    }

    public function testDatabaseFailureWhileLinkingIsReportedAsNotLinked(): void
    {
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->method('save')->willThrowException(new \Zend_Db_Adapter_Exception('boom'));

        set_error_handler(static fn(): bool => true);
        try {
            $result = $this->check($this->invitation(), $assignment);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['isAlreadyLinked' => false], $result);
    }

    // -------------------------------------------------------------------------
    // author guard
    // -------------------------------------------------------------------------

    public function testSubmitterCanNeverLinkTheInvitation(): void
    {
        $this->logIn('same@example.org', self::AUTHOR_UID);
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->expects(self::never())->method('save');
        $assignment->expects(self::never())->method('setUid');
        $this->paper->expects(self::never())->method('log');

        self::assertSame(['isAuthor' => true], $this->check($this->invitation(), $assignment));
    }

    public function testCoAuthorCanNeverLinkTheInvitation(): void
    {
        $this->paper = $this->paper(true);
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->expects(self::never())->method('save');
        $this->paper->expects(self::never())->method('log');

        self::assertSame(['isAuthor' => true], $this->check($this->invitation(), $assignment));
    }

    public function testAuthorIsRefusedBeforeTheConfirmationStep(): void
    {
        $this->paper = $this->paper(true);
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->expects(self::never())->method('save');

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost(['linkInvitation' => 'acceptToLink', 'csrf_token' => self::CSRF_TOKEN]);
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);

        self::assertSame(['isAuthor' => true], $method->invoke($this->controller, $request, $this->invitation(), $assignment, $this->paper));
    }

    // -------------------------------------------------------------------------
    // traceability
    // -------------------------------------------------------------------------

    /**
     * Captures the paper log entry (action, uid, detail)
     * @return \ArrayObject<string, mixed>
     */
    private function expectLinkLog(): \ArrayObject
    {
        $logged = new \ArrayObject();
        $this->paper->expects(self::once())->method('log')->willReturnCallback(
            static function (string $action, int $uid, array $detail) use ($logged): bool {
                $logged['action'] = $action;
                $logged['uid'] = $uid;
                $logged['detail'] = $detail;
                return true;
            }
        );

        return $logged;
    }

    public function testAutomaticLinkIsLogged(): void
    {
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['UID' => self::INVITED_UID, 'EMAIL' => 'same@example.org', 'FIRSTNAME' => 'Invited', 'LASTNAME' => 'Reviewer']));
        $assignment->method('getId')->willReturn(42);
        $assignment->method('save')->willReturn(true);
        $logged = $this->expectLinkLog();

        $this->check($this->invitation(), $assignment);

        self::assertSame(Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_LINKED, $logged['action']);
        self::assertSame(self::LOGGED_UID, $logged['uid']);
        $detail = $logged['detail'];
        self::assertSame(7, $detail['invitation_id']);
        self::assertSame(42, $detail['assignment_id']);
        self::assertTrue($detail['automatic']);
        self::assertSame(['uid' => self::INVITED_UID, 'fullname' => 'Invited Reviewer', 'email' => 'same@example.org', 'tmp' => false], $detail['invited']);
        self::assertSame(['uid' => self::LOGGED_UID, 'fullname' => 'Logged Account', 'email' => 'same@example.org'], $detail['linked_to']);
        self::assertSame('Logged Account', $detail['user']['fullname']);
    }

    public function testConfirmedLinkIsLoggedAsNotAutomatic(): void
    {
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->method('save')->willReturn(true);
        $logged = $this->expectLinkLog();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost(['linkInvitation' => 'acceptToLink', 'csrf_token' => self::CSRF_TOKEN]);
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);
        $method->invoke($this->controller, $request, $this->invitation(), $assignment, $this->paper);

        self::assertFalse($logged['detail']['automatic']);
        self::assertSame('other@example.org', $logged['detail']['invited']['email']);
        self::assertSame('logged@example.org', $logged['detail']['linked_to']['email']);
    }

    public function testLinkOfATemporaryUserInvitationIsLoggedAsSuch(): void
    {
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->method('isTmp_user')->willReturn(true);
        $assignment->method('save')->willReturn(true);
        $logged = $this->expectLinkLog();

        $this->check($this->invitation(), $assignment);

        self::assertTrue($logged['detail']['invited']['tmp']);
    }

    public function testFailedLinkIsNotLogged(): void
    {
        $this->logIn('same@example.org');
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'same@example.org']));
        $assignment->method('save')->willReturn(false);
        $this->paper->expects(self::never())->method('log');

        self::assertSame(['isAlreadyLinked' => false], $this->check($this->invitation(), $assignment));
    }
}
