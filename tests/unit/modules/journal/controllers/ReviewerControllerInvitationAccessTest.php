<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Reviewer\InvitationToken;
use Episciences_Paper;
use Episciences_User;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use Episciences_User_Tmp;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Front;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Table_Abstract;
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
    /** @var \ReviewerController&object{redirectedTo: string|null} */
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
            public ?string $redirectedTo = null;

            protected function getInvitationToken(): InvitationToken
            {
                return new InvitationToken('access-test-secret');
            }

            /**
             * @param array<string, mixed> $options
             */
            public function redirect($url, array $options = []): void
            {
                $this->redirectedTo = $url;
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

    // -------------------------------------------------------------------------
    // acceptance by a reviewer with an account who is not logged in
    // -------------------------------------------------------------------------

    /**
     * Runs accept() for an account invitation while nobody is logged in: the visitor is sent to the login page
     * @param array<string, mixed> $params parameters of the invitation request
     */
    private function acceptWhileLoggedOut(array $params): string
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        $this->controller->view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        foreach ($params as $name => $value) {
            $this->controller->getRequest()->setParam($name, $value);
        }

        // the URL is assembled on the current route: start from none, as on /reviewer/invitation (default route),
        // whatever request an earlier test routed
        $currentRoute = new ReflectionProperty($router, '_currentRoute');
        $currentRoute->setAccessible(true);
        $previousRoute = $currentRoute->getValue($router);
        $currentRoute->setValue($router, null);

        $paper = $this->createMock(Episciences_Paper::class);
        $method = new ReflectionMethod(\ReviewerController::class, 'accept');
        $method->setAccessible(true);
        try {
            $method->invoke($this->controller, $this->invitation(), $this->assignment(), $paper, ['submitaccept' => '1']);
        } finally {
            $currentRoute->setValue($router, $previousRoute);
        }

        self::assertNotNull($this->controller->redirectedTo, 'the visitor is sent to the login page');

        return (string)$this->controller->redirectedTo;
    }

    /**
     * Once logged in, the account may not be the invited one: it needs the link token again
     */
    public function testLoginRedirectKeepsTheLinkToken(): void
    {
        $url = $this->acceptWhileLoggedOut([InvitationToken::URL_PARAM => self::linkToken(), 'tmp' => md5(self::TMP_EMAIL)]);

        $params = self::loginRedirectParams($url);
        self::assertSame('invitation', $params['forward-action'] ?? null);
        self::assertSame((string)self::INVITATION_ID, $params['id'] ?? null);
        self::assertSame(self::linkToken(), $params[InvitationToken::URL_PARAM] ?? null);
        self::assertSame(md5(self::TMP_EMAIL), $params['tmp'] ?? null);
    }

    public function testLoginRedirectDropsEmptyAndNonStringLinkParameters(): void
    {
        $url = $this->acceptWhileLoggedOut([InvitationToken::URL_PARAM => ['x'], 'tmp' => '']);

        $params = self::loginRedirectParams($url);
        self::assertArrayNotHasKey(InvitationToken::URL_PARAM, $params);
        self::assertArrayNotHasKey('tmp', $params);
    }

    /**
     * Parameters of the login redirect URL, whether the URL helper writes them as path
     * segments (/user/login/id/7) or, with a path prefix, as a query string (/user/login?id=7)
     */
    private static function loginRedirectParams(string $url): array
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        self::assertStringContainsString('/user/login', $path);

        $segments = explode('/', trim(substr($path, strpos($path, '/user/login') + strlen('/user/login')), '/'));
        $params = [];
        for ($i = 0; $i + 1 < count($segments); $i += 2) {
            $params[urldecode($segments[$i])] = urldecode($segments[$i + 1]);
        }

        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return array_merge($params, $query);
    }

    // -------------------------------------------------------------------------
    // invitation whose assignment no longer exists
    // -------------------------------------------------------------------------

    public function testInvitationWithoutAssignmentIsReportedAsMissing(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = $this->getMockBuilder($previousAdapter::class)
            ->setConstructorArgs([$previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow'])
            ->getMock();
        // first the invitation row, then no assignment row (an assignment id no other test caches)
        $adapter->method('fetchRow')->willReturnOnConsecutiveCalls(
            ['ID' => self::INVITATION_ID, 'AID' => 987654321, 'STATUS' => Episciences_User_Invitation::STATUS_PENDING],
            false
        );

        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        try {
            $this->controller->getRequest()->setParam('id', (string)self::INVITATION_ID);
            $this->controller->invitationAction();
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }

        self::assertSame(["Cette invitation n'existe pas !"], $this->controller->view->errors);
        self::assertNull($this->controller->view->invitation);
    }
}
