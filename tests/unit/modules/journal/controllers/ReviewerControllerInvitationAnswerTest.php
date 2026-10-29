<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
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
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Front;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Answer to a reviewer invitation (accept, decline): only a POST carrying the session CSRF token
 * stores an answer; a GET, whatever its parameters, changes nothing.
 * answerProcess() is run for real with mocked invitation, assignment and paper; saveanswer() is
 * replaced by a recorder, so that nothing reaches the database.
 */
final class ReviewerControllerInvitationAnswerTest extends TestCase
{
    private const TOKEN = 'invitation-answer-test-token';
    private const INVITED_UID = 200;
    private const INVITATION_ID = 7;
    private const SESSION_EXPIRED = "Votre session a expiré ou la page est restée ouverte trop longtemps : par sécurité, votre réponse n'a pas été enregistrée. Merci de recommencer.";

    private mixed $previousAuthStorage;
    private mixed $previousTranslate = null;
    private mixed $previousLocale = null;
    /** @var \ReviewerController&object{saved: list<array<string, mixed>>, redirectedTo: string|null} */
    private \ReviewerController $controller;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php';

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        $this->previousTranslate = \Zend_Registry::isRegistered('Zend_Translate') ? \Zend_Registry::get('Zend_Translate') : null;
        $this->previousLocale = \Zend_Registry::isRegistered('Zend_Locale') ? \Zend_Registry::get('Zend_Locale') : null;
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']));

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = self::TOKEN;

        $this->controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        ) extends \ReviewerController {
            /** @var list<array<string, mixed>> */
            public array $saved = [];
            public ?string $redirectedTo = null;

            /**
             * @param array<string, mixed> $data
             */
            protected function saveanswer(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, array $data): void
            {
                $this->saved[] = $data;
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
        if ($this->previousLocale !== null) {
            \Zend_Registry::set('Zend_Locale', $this->previousLocale);
        }
        if ($this->previousTranslate !== null) {
            \Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        }
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        foreach ([Ccsd_View_Helper_Message::MSG_ERROR, 'success'] as $namespace) {
            $this->flashMessenger()->setNamespace($namespace)->clearCurrentMessages();
        }
        $_POST = []; // setPost() writes the global
        $_GET = [];
    }

    private function flashMessenger(): Zend_Controller_Action_Helper_FlashMessenger
    {
        /** @var Zend_Controller_Action_Helper_FlashMessenger $helper */
        $helper = $this->controller->getHelper('FlashMessenger');

        return $helper;
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        return $this->flashMessenger()->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages();
    }

    private function logInAsInvitee(): void
    {
        Zend_Auth::getInstance()->getStorage()->write(new Episciences_User(['UID' => self::INVITED_UID, 'EMAIL' => 'invited@example.org']));
    }

    /**
     * @return MockObject&Episciences_User_Invitation
     */
    private function invitation()
    {
        $invitation = $this->createMock(Episciences_User_Invitation::class);
        $invitation->method('getId')->willReturn(self::INVITATION_ID);
        $invitation->method('hasExpired')->willReturn(false);
        $invitation->method('isAnswered')->willReturn(false);
        $invitation->method('isCancelled')->willReturn(false);

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
     * @param array<string, string> $post sent as a POST when not empty, otherwise the request is a GET
     * @param array<string, string> $params query string or URL path parameters
     */
    private function answer(array $post = [], array $params = [], ?Episciences_User_Tmp $tmpUser = null): void
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        if ($post !== []) {
            $request->setMethod('POST')->setPost($post);
        }
        foreach ($params as $name => $value) {
            $request->setParam($name, $value);
        }

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDatasetsFromEnrichment')->willReturn([]);

        $method = new ReflectionMethod(\ReviewerController::class, 'answerProcess');
        $method->setAccessible(true);
        $method->invoke($this->controller, $request, $this->invitation(), $this->assignment($tmpUser !== null), $paper, $tmpUser);
    }

    // -------------------------------------------------------------------------
    // acceptance
    // -------------------------------------------------------------------------

    public function testAcceptanceWithTheTokenIsStored(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitaccept' => '', 'csrf_token' => self::TOKEN]);

        self::assertCount(1, $this->controller->saved);
        self::assertArrayHasKey('submitaccept', $this->controller->saved[0]);
        self::assertSame([], $this->errorMessages());
    }

    public function testTheTokenIsNotConsumedByAnAnswer(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitaccept' => '', 'csrf_token' => self::TOKEN]);
        $this->answer(['submitaccept' => '', 'csrf_token' => self::TOKEN]);

        self::assertCount(2, $this->controller->saved, 'a reload or a double click keeps a valid token');
    }

    public function testAcceptanceWithoutTheTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitaccept' => '']);

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
        self::assertNull($this->controller->view->invalid_form, 'the page is displayed again, not as an invalid form');
        self::assertNotNull($this->controller->view->refuse_form, 'the answer forms are displayed again');
    }

    public function testAcceptanceWithAWrongTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitaccept' => '', 'csrf_token' => 'forged']);

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
    }

    public function testAcceptanceWithoutASessionTokenIsNotStored(): void
    {
        $this->logInAsInvitee();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);

        $this->answer(['submitaccept' => '', 'csrf_token' => self::TOKEN]);

        self::assertSame([], $this->controller->saved);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function getAcceptanceProvider(): array
    {
        return [
            'former is-accepted parameter' => [['is-accepted' => '1']],
            'accept button name' => [['submitaccept' => '1']],
            'accept button name with the token' => [['submitaccept' => '1', 'csrf_token' => self::TOKEN]],
        ];
    }

    /**
     * Covers both the query string and the URL path form (/reviewer/invitation/id/N/is-accepted/1):
     * ZF1 exposes both through the same request parameters.
     * @param array<string, string> $params
     * @dataProvider getAcceptanceProvider
     */
    public function testGetRequestNeverAccepts(array $params): void
    {
        $this->logInAsInvitee();

        $this->answer([], $params);

        self::assertSame([], $this->controller->saved);
        self::assertSame([], $this->errorMessages(), 'a plain display, not a rejected answer');
        self::assertNotNull($this->controller->view->refuse_form, 'the page and its answer forms are displayed');
    }

    public function testFormerIsAcceptedFieldInAPostDoesNotAccept(): void
    {
        $this->logInAsInvitee();

        $this->answer(['is-accepted' => '1', 'csrf_token' => self::TOKEN]);

        self::assertSame([], $this->controller->saved);
    }

    // -------------------------------------------------------------------------
    // refusal
    // -------------------------------------------------------------------------

    public function testRefusalWithoutTheTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitrefuse' => '', 'comment' => 'Too busy']);

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
        self::assertNull($this->controller->view->invalid_refuse_form);
    }

    public function testRefusalWithTheTokenIsStored(): void
    {
        $this->logInAsInvitee();

        $this->answer(['submitrefuse' => '', 'comment' => ' <b>Too busy</b> ', 'csrf_token' => self::TOKEN]);

        self::assertCount(1, $this->controller->saved);
        self::assertArrayHasKey('submitrefuse', $this->controller->saved[0]);
        self::assertSame('Too busy', $this->controller->saved[0]['comment'], 'the filtered value of the form is stored');
    }

    public function testRefusalFormCarriesTheSessionToken(): void
    {
        $this->logInAsInvitee();

        $this->answer();

        /** @var \Zend_Form $form */
        $form = $this->controller->view->refuse_form;
        self::assertSame(self::TOKEN, $form->getElement('csrf_token')?->getValue());
        self::assertArrayNotHasKey('csrf_token', $form->getValues(), 'the token is never part of the stored answer');
    }

    // -------------------------------------------------------------------------
    // reviewer without an account (account creation form)
    // -------------------------------------------------------------------------

    private function tmpUser(): Episciences_User_Tmp
    {
        // the account form page queues a script: a stand-in for the jQuery view helper
        $this->controller->view = new class extends Zend_View {
            public function jQuery(): object
            {
                return new class {
                    public function addJavascriptFile(string $path): self
                    {
                        return $this;
                    }
                };
            }
        };
        $tmpUser = new Episciences_User_Tmp();
        $tmpUser->setEmail('invited@example.org');

        return $tmpUser;
    }

    public function testAccountFormCarriesTheSessionToken(): void
    {
        $this->answer([], [], $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        self::assertSame(self::TOKEN, $form->getElement('csrf_token')?->getValue());
        self::assertArrayNotHasKey('csrf_token', $form->getValues());
    }

    public function testAccountCreationWithoutTheTokenIsNotStored(): void
    {
        $this->answer(['submitaccept' => '', 'EMAIL' => 'invited@example.org', 'FIRSTNAME' => 'Jane', 'LASTNAME' => 'Doe'], [], $this->tmpUser());

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
        self::assertNull($this->controller->view->invalid_form, 'the form is not validated: no field error is displayed');
    }

    // -------------------------------------------------------------------------
    // return from the login page
    // -------------------------------------------------------------------------

    public function testReturnFromTheLoginPageAsksToConfirmWithoutAccepting(): void
    {
        $this->logInAsInvitee();

        $this->answer([], ['confirm-acceptance' => '1']);

        self::assertSame([], $this->controller->saved);
        self::assertTrue($this->controller->view->confirmAcceptance);
    }

    public function testConfirmationMessageNeedsALoggedInVisitor(): void
    {
        $this->answer([], ['confirm-acceptance' => '1']);

        self::assertNull($this->controller->view->confirmAcceptance);
    }

    public function testLoginRedirectNoLongerCarriesAnAcceptance(): void
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
        $this->controller->view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $this->controller->getRequest()->setParam(InvitationToken::URL_PARAM, 'link-token');

        $currentRoute = new ReflectionProperty($router, '_currentRoute');
        $currentRoute->setAccessible(true);
        $previousRoute = $currentRoute->getValue($router);
        $currentRoute->setValue($router, null);

        $method = new ReflectionMethod(\ReviewerController::class, 'accept');
        $method->setAccessible(true);
        try {
            $method->invoke($this->controller, $this->invitation(), $this->assignment(), $this->createMock(Episciences_Paper::class), ['submitaccept' => '']);
        } finally {
            $currentRoute->setValue($router, $previousRoute);
        }

        $url = (string)$this->controller->redirectedTo;
        self::assertStringContainsString('/user/login/', $url);
        self::assertStringNotContainsString('is-accepted', $url);
        self::assertStringContainsString('/confirm-acceptance/1', $url);
        self::assertStringContainsString('/' . InvitationToken::URL_PARAM . '/link-token', $url);
    }
}
