<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences\Reviewer\InvitationToken;
use Episciences\Reviewer\InvitedAccountState;
use Episciences\User\ActivationLinkOutcome;
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

            /** account already using the invited address, instead of the accounts table */
            public InvitedAccountState $invitedAccount = InvitedAccountState::None;

            /** @var list<string> addresses an activation link was sent to */
            public array $activationSentTo = [];

            /** what sending the activation link gives */
            public ActivationLinkOutcome $activationOutcome = ActivationLinkOutcome::Sent;

            protected function invitedAccountState(string $email): InvitedAccountState
            {
                return $this->invitedAccount;
            }

            protected function sendActivationEmailTo(string $email): ActivationLinkOutcome
            {
                if ($this->activationOutcome === ActivationLinkOutcome::Sent) {
                    $this->activationSentTo[] = $email;
                }

                return $this->activationOutcome;
            }
        };
        $this->controller->view = new Zend_View();

        // the page builds URLs (login link, account creation): a test must not depend on another one adding the routes
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        // messages left by an earlier test of the suite would be read as this test's own
        $this->clearFlashMessages();
    }

    protected function tearDown(): void
    {
        $this->clearFlashMessages();
    }

    /**
     * The messenger keeps its messages in a static property, outside the session.
     */
    private function clearFlashMessages(): void
    {
        foreach ([Ccsd_View_Helper_Message::MSG_ERROR, Ccsd_View_Helper_Message::MSG_SUCCESS, Ccsd_View_Helper_Message::MSG_INFO] as $namespace) {
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

    public function testAccountIsCreatedWithTheInvitedAddressOnly(): void
    {
        // the request token is refused: the form is displayed again with what was sent
        $this->answer($this->postRequest(['submitaccept' => '', 'EMAIL' => 'other@example.org', 'FIRSTNAME' => 'Jane']), $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        self::assertSame('invited@example.org', $form->getElement('EMAIL')?->getValue(), 'receiving the invitation proves this address only');
    }

    public function testInvitedAddressIsReadOnlyWithTheWayToUseAnotherOne(): void
    {
        $this->answer(null, $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        $email = $form->getElement('EMAIL');
        self::assertNotNull($email);
        self::assertSame('readonly', $email->getAttrib('readonly'));
        self::assertSame('EMAIL-description', $email->getAttrib('aria-describedby'));

        $html = $email->render($this->controller->view);
        self::assertStringContainsString('id="EMAIL-description"', $html);
        self::assertStringContainsString('<a href="/user/create" target="_blank" rel="noopener">', $html);
    }

    public function testMultiValueFieldsOfTheAccountFormGetTheirScript(): void
    {
        $this->answer(null, $this->tmpUser());

        /** @var \Zend_Form $form */
        $form = $this->controller->view->user_form;
        $view = $this->controller->view;
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $html = $form->render($view);

        // the "+" buttons of the affiliations and web sites call a function the form script defines
        self::assertSame(1, preg_match("#onclick='([A-Za-z0-9_]+)\(this, \"AFFILIATIONS#", $html, $add), 'the affiliations have their "+" button');
        $scripts = implode('', preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $blocks) ? $blocks[1] : []);
        self::assertStringContainsString($add[1], $scripts, 'the function of the "+" button is defined');
        self::assertStringContainsString('id="accept_form"', $html, 'laid out by the view script');
    }

    public function testStateOfTheAccountUsingTheInvitedAddressIsGivenToTheView(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;

        $this->answer(null, $this->tmpUser());

        self::assertSame(InvitedAccountState::Inactive, $this->controller->view->invitedAccountState);
    }

    /**
     * Asks for a new activation link: the page is displayed again with a GET
     *
     * @param array<string, mixed> $post
     * @return string the URL the request was redirected to
     */
    private function askForActivationLink(array $post, bool $withRequestToken = true): string
    {
        $tmpUser = $this->tmpUser();
        $stopped = false;
        try {
            $this->answerWithRoutes($this->postRequest($post, $withRequestToken, [InvitationToken::URL_PARAM => 'link-token']), $tmpUser);
        } catch (ResponseSent) {
            $stopped = true;
        }

        self::assertTrue($stopped, 'the request ends with a redirection');
        self::assertSame([], $this->controller->saved, 'nothing is answered');

        return (string)(new DispatchResult($this->response, '', $stopped))->redirectUrl();
    }

    /**
     * @return list<string>
     */
    private function messages(string $namespace): array
    {
        return $this->flashMessenger()->setNamespace($namespace)->getCurrentMessages();
    }

    public function testNewActivationLinkIsSentToTheInvitedAddress(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;

        $this->askForActivationLink(['sendactivation' => '', 'EMAIL' => 'other@example.org']);

        self::assertSame(['invited@example.org'], $this->controller->activationSentTo);
        self::assertCount(1, $this->messages(Ccsd_View_Helper_Message::MSG_SUCCESS));
    }

    public function testActivationRequestIsFollowedByTheInvitationPage(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;

        $url = $this->askForActivationLink(['sendactivation' => '']);

        // refreshing the page displayed after it does not send the request again
        self::assertStringContainsString('/reviewer/invitation/id/' . self::INVITATION_ID, $url);
        self::assertStringContainsString('/' . InvitationToken::URL_PARAM . '/link-token', $url, 'the invitation stays open without signing in');
    }

    public function testActivationLinkSentAMomentAgoIsNotSentAgain(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;
        $this->controller->activationOutcome = ActivationLinkOutcome::AlreadySent;

        $this->askForActivationLink(['sendactivation' => '']);

        self::assertSame([], $this->controller->activationSentTo);
        self::assertSame([], $this->messages(Ccsd_View_Helper_Message::MSG_SUCCESS));
        self::assertCount(1, $this->messages(Ccsd_View_Helper_Message::MSG_INFO), 'the invitee is told to check their mailbox');
    }

    public function testActivationLinkThatCouldNotBeSentIsNotReportedAsSent(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;
        $this->controller->activationOutcome = ActivationLinkOutcome::Failed;

        $this->askForActivationLink(['sendactivation' => '']);

        self::assertSame([], $this->messages(Ccsd_View_Helper_Message::MSG_SUCCESS));
        self::assertSame(["Le lien d'activation n'a pas pu être envoyé. Merci de réessayer dans quelques minutes."], $this->errorMessages());
    }

    public function testNoActivationLinkWithoutTheRequestToken(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Inactive;

        $this->askForActivationLink(['sendactivation' => ''], false);

        self::assertSame([], $this->controller->activationSentTo);
        self::assertSame([self::SESSION_EXPIRED], $this->errorMessages());
    }

    /**
     * @return array<string, array{InvitedAccountState}>
     */
    public static function accountWithoutActivationLinkProvider(): array
    {
        return [
            'no account' => [InvitedAccountState::None],
            'active account' => [InvitedAccountState::Active],
        ];
    }

    /**
     * @dataProvider accountWithoutActivationLinkProvider
     */
    public function testActivationLinkIsOnlySentForAnAccountNotActivated(InvitedAccountState $state): void
    {
        $this->controller->invitedAccount = $state;

        $this->askForActivationLink(['sendactivation' => '']);

        self::assertSame([], $this->controller->activationSentTo);
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

    // -------------------------------------------------------------------------
    // account notice
    // -------------------------------------------------------------------------

    /**
     * Runs the answer step with the default route assembled from scratch, as the login URL needs it.
     */
    private function answerWithRoutes(Zend_Controller_Request_HttpTestCase $request, ?Episciences_User_Tmp $tmpUser = null): void
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
        $this->controller->view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        $currentRoute = new ReflectionProperty($router, '_currentRoute');
        $currentRoute->setAccessible(true);
        $previousRoute = $currentRoute->getValue($router);
        $currentRoute->setValue($router, null);
        try {
            $this->answer($request, $tmpUser);
        } finally {
            $currentRoute->setValue($router, $previousRoute);
        }
    }

    public function testLoggedOutInviteeGetsALoginLinkBackToTheInvitation(): void
    {
        $this->answerWithRoutes($this->getRequest([], [InvitationToken::URL_PARAM => 'link-token']));

        $url = (string) $this->controller->view->loginUrl;
        self::assertStringContainsString('/user/login/', $url);
        self::assertStringContainsString('/forward-controller/reviewer/forward-action/invitation/id/' . self::INVITATION_ID, $url);
        self::assertStringContainsString('/' . InvitationToken::URL_PARAM . '/link-token', $url, 'the link secret is kept: the account may not be the invited one');
        self::assertStringNotContainsString('confirm-acceptance', $url, 'signing in does not prepare an acceptance');
    }

    public function testLoginLinkDoesNotCarryParametersOfTheCurrentPage(): void
    {
        // the page was reached on the way back from a former login, then the session expired
        $this->answerWithRoutes($this->getRequest([], [InvitationToken::URL_PARAM => 'link-token', 'confirm-acceptance' => '1']));

        $url = (string) $this->controller->view->loginUrl;
        self::assertStringNotContainsString('confirm-acceptance', $url, 'signing in does not prepare an acceptance');
        self::assertStringContainsString('/' . InvitationToken::URL_PARAM . '/link-token', $url);
    }

    public function testAcceptingWithTheActiveAccountOfTheInvitedAddressIsConfirmedOnceBack(): void
    {
        $this->controller->invitedAccount = InvitedAccountState::Active;

        $this->answerWithRoutes($this->getRequest([], [InvitationToken::URL_PARAM => 'link-token']), $this->tmpUser());

        $url = (string) $this->controller->view->acceptLoginUrl;
        self::assertStringContainsString('/forward-controller/reviewer/forward-action/invitation/id/' . self::INVITATION_ID, $url);
        self::assertStringContainsString('/confirm-acceptance/1', $url);
        self::assertStringNotContainsString('confirm-acceptance', (string) $this->controller->view->loginUrl);
    }

    /**
     * @return array<string, array{InvitedAccountState}>
     */
    public static function accountNotSignedInWithProvider(): array
    {
        return [
            'no account' => [InvitedAccountState::None],
            'account not activated' => [InvitedAccountState::Inactive],
        ];
    }

    /**
     * @dataProvider accountNotSignedInWithProvider
     */
    public function testNoAcceptanceThroughTheLoginPageWithoutAnActiveAccount(InvitedAccountState $state): void
    {
        $this->controller->invitedAccount = $state;

        $this->answerWithRoutes($this->getRequest([], [InvitationToken::URL_PARAM => 'link-token']), $this->tmpUser());

        self::assertNull($this->controller->view->acceptLoginUrl);
    }

    public function testLoggedInInviteeGetsNoLoginLink(): void
    {
        $this->logInAsInvitee();

        $this->answerWithRoutes($this->getRequest());

        self::assertNull($this->controller->view->loginUrl);
    }
}
