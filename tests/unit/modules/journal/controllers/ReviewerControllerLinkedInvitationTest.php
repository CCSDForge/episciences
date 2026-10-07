<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\User\UserNotFoundException;
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
 * checkAndProcessLinkedInvitation() is run for real with mocked invitation and assignment.
 */
final class ReviewerControllerLinkedInvitationTest extends TestCase
{
    private const LOGGED_UID = 100;
    private const INVITED_UID = 200;
    private const SESSION = 'linked_invitation_test';

    private mixed $previousAuthStorage;
    private \ReviewerController $controller;

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
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
        $_POST = []; // setPost() writes the global
    }

    private function logIn(string $email = 'logged@example.org'): void
    {
        Zend_Auth::getInstance()->getStorage()->write(
            new Episciences_User(['UID' => self::LOGGED_UID, 'EMAIL' => $email])
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

        return $method->invoke($this->controller, new Zend_Controller_Request_HttpTestCase(), $invitation, $assignment);
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
    }

    public function testAcceptedConfirmationLinksTheInvitationAndClearsTheMarker(): void
    {
        $this->confirmationRequest();
        $assignment = $this->assignment(new Episciences_User(['EMAIL' => 'other@example.org']));
        $assignment->expects(self::once())->method('setUid')->with(self::LOGGED_UID);
        $assignment->expects(self::once())->method('save')->willReturn(true);

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost(['linkInvitation' => 'acceptToLink']);
        $method = new ReflectionMethod(\ReviewerController::class, 'checkAndProcessLinkedInvitation');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, $request, $this->invitation(), $assignment);

        self::assertSame('acceptToLink', $result['decision']);
        self::assertArrayNotHasKey(7, (array)(new Zend_Session_Namespace(self::SESSION))->linkedInvitationIds);
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
}
