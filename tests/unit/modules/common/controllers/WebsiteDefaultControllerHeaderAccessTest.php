<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_View_Helper_DisplayFlashMessages;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteDefaultController;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Behavioural tests for the access checks of WebsiteDefaultController::headerAction():
 * the role required to open the page, and the CSRF token required to save it.
 *
 * Only the refused requests are run: they stop before the header is loaded or saved,
 * so they need neither the database nor the journal directories.
 */
final class WebsiteDefaultControllerHeaderAccessTest extends TestCase
{
    private Zend_Controller_Response_HttpTestCase $response;

    /** State shared with the other tests, restored after each test */
    private bool $previousUnitTestEnabled;
    private Zend_Auth_Storage_Interface $previousAuthStorage;
    /** @var Zend_Controller_Action_Helper_Abstract[] */
    private array $previousHelpers = [];

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $this->previousAuthStorage = Episciences_Auth::getInstance()->getStorage();
        Episciences_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());

        // Form elements are rendered with the view of the static view renderer: keep the bootstrap ones
        $this->previousHelpers = iterator_to_array(Zend_Controller_Action_HelperBroker::getStack(), false);
        Zend_Controller_Action_HelperBroker::resetHelpers();
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer(new Zend_View());
        $viewRenderer->setNeverRender(true);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        $redirector = new Zend_Controller_Action_Helper_Redirector();
        $redirector->setExit(false);
        Zend_Controller_Action_HelperBroker::addHelper($redirector);

        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    protected function tearDown(): void
    {
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = [];

        Zend_Controller_Action_HelperBroker::resetHelpers();
        foreach ($this->previousHelpers as $helper) {
            Zend_Controller_Action_HelperBroker::addHelper($helper);
        }
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->setStorage($this->previousAuthStorage);
        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    private function logInAs(string $role): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getAllRoles')->willReturn([RVID => [$role]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchHeader(?array $post = null): WebsiteDefaultController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        if ($post !== null) {
            $request->setMethod('POST')->setPost($post);
            $_POST = $post;
        }

        $controller = new WebsiteDefaultController($request, $this->response);
        $controller->headerAction();

        return $controller;
    }

    private function errorMessages(): array
    {
        $flash = new Zend_Controller_Action_Helper_FlashMessenger();

        return $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages();
    }

    // -----------------------------------------------------------------------
    // Role
    // -----------------------------------------------------------------------

    public function testGuestIsRefused(): void
    {
        $this->dispatchHeader();

        self::assertSame(403, $this->response->getHttpResponseCode());
    }

    #[DataProvider('unauthorizedRoles')]
    public function testRoleWithoutWebsiteRightsIsRefused(string $role): void
    {
        $this->logInAs($role);

        $this->dispatchHeader(['header' => '']);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertFalse($this->response->isRedirect());
    }

    public static function unauthorizedRoles(): array
    {
        return [
            'member' => [Episciences_Acl::ROLE_MEMBER],
            'author' => [Episciences_Acl::ROLE_AUTHOR],
            'reviewer' => [Episciences_Acl::ROLE_REVIEWER],
            'editor' => [Episciences_Acl::ROLE_EDITOR],
            'guest editor' => [Episciences_Acl::ROLE_GUEST_EDITOR],
        ];
    }

    // -----------------------------------------------------------------------
    // CSRF token
    // -----------------------------------------------------------------------

    #[DataProvider('authorizedRoles')]
    public function testSubmissionWithoutTokenIsNotSaved(string $role): void
    {
        $this->logInAs($role);

        $this->dispatchHeader(['header' => '', 'logo_0' => ['type' => 'text', 'text' => ['en' => 'x']]]);

        $this->assertRedirectedWithExpiredFormMessage();
    }

    public static function authorizedRoles(): array
    {
        return [
            'root' => [Episciences_Acl::ROLE_ROOT],
            'chief editor' => [Episciences_Acl::ROLE_CHIEF_EDITOR],
            'administrator' => [Episciences_Acl::ROLE_ADMIN],
            'secretary' => [Episciences_Acl::ROLE_SECRETARY],
            'webmaster' => [Episciences_Acl::ROLE_WEBMASTER],
        ];
    }

    public function testSubmissionWithWrongTokenIsNotSaved(): void
    {
        $this->logInAs(Episciences_Acl::ROLE_SECRETARY);

        $this->dispatchHeader(['header' => '', 'csrf_token' => 'forged']);

        $this->assertRedirectedWithExpiredFormMessage();
    }

    private function assertRedirectedWithExpiredFormMessage(): void
    {
        self::assertTrue($this->response->isRedirect(), 'A refused submission must redirect to the form');
        $location = array_column($this->response->getHeaders(), 'value', 'name')['Location'] ?? '';
        self::assertStringEndsWith('/website/header', $location);
        self::assertSame(
            ["Les modifications n'ont pas été enregistrées car le formulaire a expiré. Veuillez les saisir à nouveau."],
            $this->errorMessages()
        );
    }
}
