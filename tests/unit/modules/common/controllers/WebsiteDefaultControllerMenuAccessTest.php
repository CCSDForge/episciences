<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_View_Helper_DisplayFlashMessages;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_User;
use Episciences_Website_Navigation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteDefaultController;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Behavioural tests for the role required by WebsiteDefaultController::menuAction(),
 * which edits the pages of the site and rebuilds its navigation.
 *
 * The navigation being edited is kept in session: a stub is put there, so the requests
 * need neither the database nor the journal directories.
 */
final class WebsiteDefaultControllerMenuAccessTest extends TestCase
{
    private Zend_Controller_Response_HttpTestCase $response;

    private WebsiteDefaultController $controller;

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

        $this->previousHelpers = iterator_to_array(Zend_Controller_Action_HelperBroker::getStack(), false);
        Zend_Controller_Action_HelperBroker::resetHelpers();
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer(new Zend_View());
        $viewRenderer->setNeverRender(true);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    protected function tearDown(): void
    {
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken, $session->website);
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
     * Puts in session a navigation that must be neither changed nor saved
     */
    private function givenNavigationInSession(): void
    {
        $navigation = $this->createMock(Episciences_Website_Navigation::class);
        $navigation->expects(self::never())->method('setPage');
        $navigation->expects(self::never())->method('save');
        $navigation->expects(self::never())->method('createNavigation');
        $navigation->method('getPages')->willReturn([]);
        $navigation->method('getOrder')->willReturn([]);
        $navigation->method('getPageTypes')->willReturn([]);

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->website = $navigation;
    }

    /**
     * A page change as sent by the menu form, without form token
     */
    private function dispatchMenuPost(): void
    {
        $post = ['pages_0' => ['labels' => ['en' => 'Injected page']]];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $this->controller = new WebsiteDefaultController($request, $this->response);
        $this->controller->menuAction();
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        $flash = new Zend_Controller_Action_Helper_FlashMessenger();

        return array_values($flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages());
    }

    public function testGuestIsRefused(): void
    {
        $this->givenNavigationInSession();

        $this->dispatchMenuPost();

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertNull($this->controller->view->pages, 'the pages of the site must not be shown');
    }

    #[DataProvider('unauthorizedRoles')]
    public function testRoleWithoutWebsiteRightsIsRefused(string $role): void
    {
        $this->logInAs($role);
        $this->givenNavigationInSession();

        $this->dispatchMenuPost();

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertNull($this->controller->view->pages);
        self::assertSame([], $this->errorMessages(), 'a refused role must not reach the form token check');
    }

    /**
     * @return array<string, array{string}>
     */
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

    /**
     * An authorized role reaches the page; the change is still refused without form token.
     */
    #[DataProvider('authorizedRoles')]
    public function testRoleWithWebsiteRightsReachesThePage(string $role): void
    {
        $this->logInAs($role);
        $this->givenNavigationInSession();

        $this->dispatchMenuPost();

        self::assertSame(200, $this->response->getHttpResponseCode());
        self::assertSame([], $this->controller->view->pages);
        self::assertSame(['Votre session a expiré, veuillez réessayer.'], $this->errorMessages());
    }

    /**
     * @return array<string, array{string}>
     */
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
}
