<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences\Reviewer\InvitationToken;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use Episciences_User_Tmp;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use ReflectionProperty;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use unit\modules\support\ResponseSent;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Front;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Answer to a reviewer invitation (accept, decline): only a POST carrying the session CSRF token
 * stores an answer; a GET, whatever its parameters, changes nothing.
 *
 * answerProcess(), the answer step of invitationAction(), is run for real with mocked invitation,
 * assignment and paper: the steps before it (invitation, assignment and paper lookups, link token)
 * are covered by ReviewerControllerInvitationAccessTest. saveanswer() is replaced by a recorder,
 * so that nothing reaches the database.
 */
final class ReviewerControllerInvitationAnswerTest extends ControllerTestCase
{
    private const INVITED_UID = 200;
    private const INVITATION_ID = 7;
    private const SESSION_EXPIRED = "Votre session a expiré ou la page est restée ouverte trop longtemps : par sécurité, votre réponse n'a pas été enregistrée. Merci de recommencer.";

    /** @var \ReviewerController&object{saved: list<array<string, mixed>>} */
    private \ReviewerController $controller;

    private Zend_Controller_Response_HttpTestCase $response;

    protected function setUp(): void
    {
        self::loadController('journal', 'ReviewerController');

        // the registry is restored by ControllerTestCase after each test
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']));

        $this->response = new Zend_Controller_Response_HttpTestCase();
        $this->controller = new class (
            new Zend_Controller_Request_HttpTestCase(),
            $this->response
        ) extends \ReviewerController {
            /** @var list<array<string, mixed>> */
            public array $saved = [];

            /**
             * @param array<string, mixed> $data
             */
            protected function saveanswer(Episciences_User_Invitation $oInvitation, Episciences_User_Assignment $assignment, Episciences_Paper $paper, array $data): void
            {
                $this->saved[] = $data;
            }
        };
        $this->controller->view = new Zend_View();
    }

    protected function tearDown(): void
    {
        // the messenger keeps its messages in a static property, outside the session
        foreach ([Ccsd_View_Helper_Message::MSG_ERROR, Ccsd_View_Helper_Message::MSG_SUCCESS] as $namespace) {
            $this->flashMessenger()->setNamespace($namespace)->clearCurrentMessages();
        }
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
        $this->logInAs([Episciences_Acl::ROLE_MEMBER], self::INVITED_UID);
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
     * Runs the answer step of the invitation page on $request (a GET when none is given).
     */
    private function answer(?Zend_Controller_Request_HttpTestCase $request = null, ?Episciences_User_Tmp $tmpUser = null): void
    {
        $request ??= $this->getRequest();
        $this->controller->setRequest($request);

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

        $this->answer($this->postRequest(['submitaccept' => ''], true));

        self::assertCount(1, $this->controller->saved);
        self::assertArrayHasKey('submitaccept', $this->controller->saved[0]);
        self::assertSame([], $this->errorMessages());
    }

    public function testTheTokenIsNotConsumedByAnAnswer(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitaccept' => ''], true));
        $this->answer($this->postRequest(['submitaccept' => ''], true));

        self::assertCount(2, $this->controller->saved, 'a reload or a double click keeps a valid token');
    }

    public function testAcceptanceWithoutTheTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitaccept' => '']));

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
        self::assertNull($this->controller->view->invalid_form, 'the page is displayed again, not as an invalid form');
        self::assertNotNull($this->controller->view->refuse_form, 'the answer forms are displayed again');
    }

    public function testAcceptanceWithAWrongTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitaccept' => '', 'csrf_token' => 'forged']));

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
    }

    public function testAcceptanceWithoutASessionTokenIsNotStored(): void
    {
        $this->logInAsInvitee();
        $request = $this->postRequest(['submitaccept' => ''], true);
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);

        $this->answer($request);

        self::assertSame([], $this->controller->saved);
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function getAcceptanceProvider(): array
    {
        return [
            'former is-accepted parameter' => [['is-accepted' => '1'], false],
            'accept button name' => [['submitaccept' => '1'], false],
            'accept button name with the token' => [['submitaccept' => '1'], true],
        ];
    }

    /**
     * Covers both the query string and the URL path form (/reviewer/invitation/id/N/is-accepted/1):
     * ZF1 exposes both through the same request parameters.
     * @param array<string, string> $params
     * @dataProvider getAcceptanceProvider
     */
    public function testGetRequestNeverAccepts(array $params, bool $withToken): void
    {
        $this->logInAsInvitee();
        if ($withToken) {
            $params['csrf_token'] = $this->requestToken();
        }

        $this->answer($this->getRequest([], $params));

        self::assertSame([], $this->controller->saved);
        self::assertSame([], $this->errorMessages(), 'a plain display, not a rejected answer');
        self::assertNotNull($this->controller->view->refuse_form, 'the page and its answer forms are displayed');
    }

    public function testFormerIsAcceptedFieldInAPostDoesNotAccept(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['is-accepted' => '1'], true));

        self::assertSame([], $this->controller->saved);
    }

    // -------------------------------------------------------------------------
    // both answers at once
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{bool}>
     */
    public static function getInviteeProvider(): array
    {
        return [
            'reviewer with an account' => [false],
            'reviewer without an account' => [true],
        ];
    }

    /**
     * The refusal fields are all optional: a valid refusal form must not let an acceptance through,
     * with an account form that was never validated.
     * @dataProvider getInviteeProvider
     */
    public function testAcceptanceAndRefusalTogetherStoreNothing(bool $withoutAccount): void
    {
        $tmpUser = $withoutAccount ? $this->tmpUser() : null;
        if (!$withoutAccount) {
            $this->logInAsInvitee();
        }

        $this->answer($this->postRequest([
            'submitaccept' => '',
            'submitrefuse' => '',
            'USERNAME' => 'not-validated',
            'EMAIL' => 'not-an-email',
            'PASSWORD' => '',
        ], true), $tmpUser);

        self::assertSame([], $this->controller->saved);
        self::assertNotNull($this->controller->view->refuse_form, 'the answer forms are displayed again');
    }

    // -------------------------------------------------------------------------
    // refusal
    // -------------------------------------------------------------------------

    public function testRefusalWithoutTheTokenIsNotStored(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitrefuse' => '', 'comment' => 'Too busy']));

        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
    }

    public function testRefusalWithAWrongTokenKeepsTheTypedAnswer(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitrefuse' => '', 'suggestreviewer' => 'Jane Doe', 'comment' => 'Too busy', 'csrf_token' => 'expired']));

        /** @var \Zend_Form $form */
        $form = $this->controller->view->refuse_form;
        self::assertTrue($this->controller->view->invalid_refuse_form, 'the refusal form is open again');
        self::assertSame('Too busy', $form->getElement('comment')?->getValue());
        self::assertSame('Jane Doe', $form->getElement('suggestreviewer')?->getValue());
        self::assertSame([], $form->getMessages(), 'the form is not validated: no field error is displayed');
        self::assertSame($this->requestToken(), $form->getElement('csrf_token')?->getValue(), 'the form posts the current token, not the refused one');
    }

    public function testRefusalWithTheTokenIsStored(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->postRequest(['submitrefuse' => '', 'comment' => ' <b>Too busy</b> '], true));

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
        self::assertSame($this->requestToken(), $form->getElement('csrf_token')?->getValue());
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
        $this->answer(null, $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        self::assertSame($this->requestToken(), $form->getElement('csrf_token')?->getValue());
        self::assertArrayNotHasKey('csrf_token', $form->getValues());
    }

    public function testAccountCreationWithoutTheTokenIsNotStored(): void
    {
        $this->answer($this->postRequest(['submitaccept' => '', 'EMAIL' => 'invited@example.org', 'FIRSTNAME' => 'Jane', 'LASTNAME' => 'Doe']), $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        self::assertSame([], $this->controller->saved);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
        self::assertTrue($this->controller->view->invalid_form, 'the account form is open again');
        self::assertSame('Jane', $form->getElement('FIRSTNAME')?->getValue(), 'what was typed is kept');
        self::assertSame([], $form->getMessages(), 'the form is not validated: no field error is displayed');
    }

    // -------------------------------------------------------------------------
    // return from the login page
    // -------------------------------------------------------------------------

    public function testReturnFromTheLoginPageAsksToConfirmWithoutAccepting(): void
    {
        $this->logInAsInvitee();

        $this->answer($this->getRequest([], ['confirm-acceptance' => '1']));

        self::assertSame([], $this->controller->saved);
        self::assertTrue($this->controller->view->confirmAcceptance);
    }

    public function testConfirmationMessageNeedsALoggedInVisitor(): void
    {
        $this->answer($this->getRequest([], ['confirm-acceptance' => '1']));

        self::assertNull($this->controller->view->confirmAcceptance);
    }

    public function testConfirmationMessageIsNotShownWithARefusal(): void
    {
        $this->logInAsInvitee();

        // the invitee changed their mind: a refusal too long to be valid reopens the refusal form
        $this->answer($this->postRequest(['submitrefuse' => '', 'comment' => str_repeat('a', 5001)], true, ['confirm-acceptance' => '1']));

        self::assertSame([], $this->controller->saved);
        self::assertTrue($this->controller->view->invalid_refuse_form);
        self::assertNull($this->controller->view->confirmAcceptance);
    }

    public function testLoginRedirectNoLongerCarriesAnAcceptance(): void
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
        $this->controller->view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $this->controller->setRequest($this->postRequest(['submitaccept' => ''], true, [InvitationToken::URL_PARAM => 'link-token']));

        $currentRoute = new ReflectionProperty($router, '_currentRoute');
        $currentRoute->setAccessible(true);
        $previousRoute = $currentRoute->getValue($router);
        $currentRoute->setValue($router, null);

        // the real saveanswer() (the test double only records), down to accept()
        $method = new ReflectionMethod(\ReviewerController::class, 'saveanswer');
        $method->setAccessible(true);
        $stopped = false;
        try {
            $method->invoke($this->controller, $this->invitation(), $this->assignment(), $this->createMock(Episciences_Paper::class), ['submitaccept' => '']);
        } catch (ResponseSent) {
            $stopped = true;
        } finally {
            $currentRoute->setValue($router, $previousRoute);
        }

        self::assertSame(
            [],
            $this->flashMessenger()->setNamespace(Ccsd_View_Helper_Message::MSG_SUCCESS)->getCurrentMessages(),
            'nothing is stored before the invitee logs in: no "answer saved" message'
        );

        $url = (string)(new DispatchResult($this->response, '', $stopped))->redirectUrl();
        self::assertTrue($stopped, 'the redirection ends the request');
        self::assertStringContainsString('/user/login/', $url);
        self::assertStringNotContainsString('is-accepted', $url);
        self::assertStringContainsString('/confirm-acceptance/1', $url);
        self::assertStringContainsString('/' . InvitationToken::URL_PARAM . '/link-token', $url);
    }
}
