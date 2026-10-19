<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Reviewer\InvitationToken;
use Episciences_User;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use Episciences_User_Tmp;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

/**
 * Access to the reviewer invitation page: holding the link is the proof.
 *
 * An invitation with a row secret requires its link token, unless the invitee is logged in;
 * a legacy invitation (no row secret) keeps the former check. A status row never gives access.
 * isInvitationRequestAuthorized() is run for real with mocked invitation and assignment.
 */
final class ReviewerControllerInvitationAccessTest extends TestCase
{
    private const SECRET = 'access-test-secret';
    private const ROW_SECRET = '0f1e2d3c4b5a69788796a5b4c3d2e1f00f1e2d3c4b5a69788796a5b4c3d2e1f0';
    private const INVITATION_ID = 7;
    private const INVITED_UID = 200;
    private const OTHER_UID = 100;
    private const TMP_EMAIL = 'invited@example.org';
    private const INVALID_LINK = 'Lien non valide; le jeton transmis dans la requête est erroné.';

    private mixed $previousAuthStorage;
    private \ReviewerController $controller;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php';

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        $this->controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \ReviewerController {
            protected function getInvitationToken(): InvitationToken
            {
                return new InvitationToken('access-test-secret');
            }
        };
        $this->controller->view = new Zend_View();
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
        $_GET = [];
    }

    private function logIn(int $uid): void
    {
        Zend_Auth::getInstance()->getStorage()->write(new Episciences_User(['UID' => $uid, 'EMAIL' => 'logged@example.org']));
    }

    private static function linkToken(int $invitationId = self::INVITATION_ID): string
    {
        return (new InvitationToken(self::SECRET))->compute($invitationId, self::ROW_SECRET);
    }

    /**
     * @return MockObject&Episciences_User_Invitation
     */
    private function invitation(?string $rowSecret = self::ROW_SECRET, bool $pendingRow = true)
    {
        $invitation = $this->createMock(Episciences_User_Invitation::class);
        $invitation->method('getId')->willReturn(self::INVITATION_ID);
        $invitation->method('getToken')->willReturn($rowSecret);
        $invitation->method('isPendingRow')->willReturn($pendingRow);

        return $invitation;
    }

    /**
     * @return MockObject&Episciences_User_Assignment
     */
    private function assignment(bool $tmpUser = false)
    {
        $assignment = $this->createMock(Episciences_User_Assignment::class);
        $assignment->method('getUid')->willReturn(self::INVITED_UID);
        $assignment->method('isTmp_user')->willReturn($tmpUser);

        return $assignment;
    }

    /**
     * @param array<string, string> $params
     */
    private function isAuthorized(
        Episciences_User_Invitation $invitation,
        Episciences_User_Assignment $assignment,
        array                       $params = [],
        ?Episciences_User_Tmp       $tmpUser = null
    ): bool
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        foreach ($params as $name => $value) {
            $request->setParam($name, $value);
        }

        $method = new ReflectionMethod(\ReviewerController::class, 'isInvitationRequestAuthorized');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $request, $invitation, $assignment, $tmpUser);
    }

    private function tmpUser(): Episciences_User_Tmp
    {
        $tmpUser = new Episciences_User_Tmp();
        $tmpUser->setEmail(self::TMP_EMAIL);

        return $tmpUser;
    }

    // -------------------------------------------------------------------------
    // invitation with a link token
    // -------------------------------------------------------------------------

    public function testAnonymousVisitorWithoutTokenIsRefused(): void
    {
        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment()));
        self::assertSame(self::INVALID_LINK, $this->controller->view->errors);
    }

    public function testWrongTokenIsRefused(): void
    {
        $token = self::linkToken();
        $wrong = substr($token, 0, -1) . ($token[63] === '0' ? '1' : '0');

        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment(), ['token' => $wrong]));
        self::assertSame(self::INVALID_LINK, $this->controller->view->errors);
    }

    public function testTokenOfAnotherInvitationIsRefused(): void
    {
        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment(), ['token' => self::linkToken(8)]));
    }

    public function testValidTokenGivesAccessToAnAnonymousVisitor(): void
    {
        self::assertTrue($this->isAuthorized($this->invitation(), $this->assignment(), ['token' => self::linkToken()]));
        self::assertNull($this->controller->view->errors);
    }

    public function testValidTokenFromTheQueryStringGivesAccess(): void
    {
        // links sent by the inbox scripts use ?id=…&token=…
        $_GET = ['token' => self::linkToken()];

        self::assertTrue($this->isAuthorized($this->invitation(), $this->assignment()));
    }

    public function testValidTokenGivesAccessToAnotherAccount(): void
    {
        $this->logIn(self::OTHER_UID);

        self::assertTrue($this->isAuthorized($this->invitation(), $this->assignment(), ['token' => self::linkToken()]));
    }

    public function testInviteeLoggedInNeedsNoToken(): void
    {
        $this->logIn(self::INVITED_UID);

        self::assertTrue($this->isAuthorized($this->invitation(), $this->assignment()));
    }

    public function testAnotherAccountWithoutTokenIsRefused(): void
    {
        $this->logIn(self::OTHER_UID);

        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment()));
        self::assertSame(self::INVALID_LINK, $this->controller->view->errors);
    }

    public function testAccountWhoseUidEqualsATemporaryUserIdIsNotTheInvitee(): void
    {
        // temporary user ids and account uids are distinct id spaces
        $this->logIn(self::INVITED_UID);

        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment(true)));
    }

    public function testTemporaryUserWithValidTokenGetsAccess(): void
    {
        self::assertTrue($this->isAuthorized($this->invitation(), $this->assignment(true), ['token' => self::linkToken()], $this->tmpUser()));
    }

    public function testTemporaryUserNeedsNoLegacyMd5WhenTheTokenIsValid(): void
    {
        self::assertTrue($this->isAuthorized(
            $this->invitation(),
            $this->assignment(true),
            ['token' => self::linkToken(), 'tmp' => 'not-the-md5'],
            $this->tmpUser()
        ));
    }

    public function testLegacyMd5IsNotEnoughForAnInvitationWithALinkToken(): void
    {
        self::assertFalse($this->isAuthorized(
            $this->invitation(),
            $this->assignment(true),
            ['tmp' => md5(self::TMP_EMAIL)],
            $this->tmpUser()
        ));
    }

    public function testTemporaryUserThatNoLongerExistsIsRefused(): void
    {
        self::assertFalse($this->isAuthorized($this->invitation(), $this->assignment(true), ['token' => self::linkToken()]));
    }

    public function testMissingSecretFailsClosed(): void
    {
        $controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \ReviewerController {
            protected function getInvitationToken(): InvitationToken
            {
                return new InvitationToken('');
            }
        };
        $controller->view = new Zend_View();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParam('token', self::linkToken());
        $method = new ReflectionMethod(\ReviewerController::class, 'isInvitationRequestAuthorized');
        $method->setAccessible(true);

        set_error_handler(static fn(): bool => true, E_USER_WARNING); // the configuration error is reported with a warning
        try {
            $result = $method->invoke($controller, $request, $this->invitation(), $this->assignment(), null);
        } finally {
            restore_error_handler();
        }

        self::assertFalse($result);
        self::assertSame(self::INVALID_LINK, $controller->view->errors);
    }

    // -------------------------------------------------------------------------
    // legacy invitation (no row secret)
    // -------------------------------------------------------------------------

    public function testLegacyInvitationOfAnAccountStillOpensWithItsId(): void
    {
        self::assertTrue($this->isAuthorized($this->invitation(null), $this->assignment()));
    }

    public function testLegacyInvitationOfATemporaryUserStillAcceptsTheMd5(): void
    {
        self::assertTrue($this->isAuthorized($this->invitation(null), $this->assignment(true), ['tmp' => md5(self::TMP_EMAIL)], $this->tmpUser()));
    }

    public function testLegacyInvitationOfATemporaryUserRefusesAWrongMd5(): void
    {
        self::assertFalse($this->isAuthorized($this->invitation(null), $this->assignment(true), ['tmp' => md5('other@example.org')], $this->tmpUser()));
        self::assertSame(self::INVALID_LINK, $this->controller->view->errors);
    }

    // -------------------------------------------------------------------------
    // status row
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{?string, array<string, string>}>
     */
    public static function statusRowProvider(): array
    {
        return [
            'no row secret' => [null, []],
            'with a valid token' => [self::ROW_SECRET, ['token' => (new InvitationToken(self::SECRET))->compute(self::INVITATION_ID, self::ROW_SECRET)]],
        ];
    }

    /**
     * @dataProvider statusRowProvider
     * @param array<string, string> $params
     */
    public function testStatusRowIsRefusedWithoutAnyDetail(?string $rowSecret, array $params): void
    {
        self::assertFalse($this->isAuthorized($this->invitation($rowSecret, false), $this->assignment(), $params));
        self::assertSame("Il apparaît qu'une réponse a déjà été transmise.", $this->controller->view->errors);
        self::assertNull($this->controller->view->displayInvitationDetails);
        self::assertNull($this->controller->view->invitation);
    }

    public function testStatusRowTellsTheInviteeTheyAlreadyAnswered(): void
    {
        $this->logIn(self::INVITED_UID);

        self::assertFalse($this->isAuthorized($this->invitation(null, false), $this->assignment()));
        self::assertSame('Vous avez déjà répondu à cette invitation.', $this->controller->view->errors);
    }
}
