<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_View_Helper_DisplayFlashMessages;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_Csrf_Helper;
use Episciences_User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
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
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Behavioural tests for the role required by the site settings of WebsiteDefaultController:
 * languages (common), style, public resources and news.
 *
 * The actions are called for real on a partially mocked database adapter, with a valid form
 * token: only the role decides.
 */
final class WebsiteDefaultControllerSettingsAccessTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    private Zend_Controller_Response_HttpTestCase $response;

    private string $fileName;

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

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll', 'fetchPairs', 'fetchRow', 'fetchCol', 'fetchOne', 'insert', 'update', 'delete'])
            ->getMock();
        // Nothing saved yet for the journal
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchPairs')->willReturn([]);
        $this->adapter->method('fetchRow')->willReturn(false);
        $this->adapter->method('fetchCol')->willReturn([]);
        $this->adapter->method('fetchOne')->willReturn(false);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }

        // Form elements are rendered with the view of the static view renderer: keep the bootstrap ones
        $this->previousHelpers = iterator_to_array(Zend_Controller_Action_HelperBroker::getStack(), false);
        Zend_Controller_Action_HelperBroker::resetHelpers();
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer(new Zend_View());
        $viewRenderer->setNeverRender(true);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        $redirector = new Zend_Controller_Action_Helper_Redirector();
        $redirector->setExit(false);
        Zend_Controller_Action_HelperBroker::addHelper($redirector);

        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();

        // A file of the journal public resources, that a refused removal must leave in place
        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->fileName = uniqid('access-test-', true) . '.txt';
        file_put_contents($dir . $this->fileName, 'test');

        $this->response = new Zend_Controller_Response_HttpTestCase();
    }

    protected function tearDown(): void
    {
        $file = REVIEW_PATH . 'public/' . $this->fileName;
        if (is_file($file)) {
            unlink($file);
        }

        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = [];

        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
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
     * A change of each setting, as sent by its form, with a valid form token
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function settingsActionsProvider(): array
    {
        return [
            'languages' => ['commonAction', ['languages' => ['en']]],
            'style' => ['styleAction', ['navigation' => 'horizontal']],
            'public resources' => ['publicAction', ['method' => 'remove']],
            'news' => ['newsAction', ['action' => 'delete', 'newsid' => '7']],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatch(string $action, array $post): WebsiteDefaultController
    {
        if (($post['method'] ?? null) === 'remove') {
            $post['name'] = $this->fileName;
        }
        $post['csrf_token'] = Episciences_Csrf_Helper::getSessionToken();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $controller = new WebsiteDefaultController($request, $this->response);
        $controller->$action();

        return $controller;
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unauthorizedRolesProvider(): array
    {
        return [
            'guest' => [null],
            'member' => [Episciences_Acl::ROLE_MEMBER],
            'author' => [Episciences_Acl::ROLE_AUTHOR],
            'reviewer' => [Episciences_Acl::ROLE_REVIEWER],
            'editor' => [Episciences_Acl::ROLE_EDITOR],
            'guest editor' => [Episciences_Acl::ROLE_GUEST_EDITOR],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsActionsProvider')]
    public function testUsersWithoutWebsiteRightsAreRefused(string $action, array $post): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        foreach (self::unauthorizedRolesProvider() as $label => [$role]) {
            Episciences_Auth::getInstance()->clearIdentity();
            if ($role !== null) {
                $this->logInAs($role);
            }
            $this->response = new Zend_Controller_Response_HttpTestCase();

            $controller = $this->dispatch($action, $post);

            self::assertSame(403, $this->response->getHttpResponseCode(), $label);
            self::assertFalse($this->response->isRedirect(), $label);
            self::assertNull($controller->view->form, $label . ': no form may be shown');
            self::assertNull($controller->view->files, $label . ': no public resource may be listed');
        }

        self::assertFileExists(REVIEW_PATH . 'public/' . $this->fileName);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authorizedRolesProvider(): array
    {
        return [
            'root' => [Episciences_Acl::ROLE_ROOT],
            'chief editor' => [Episciences_Acl::ROLE_CHIEF_EDITOR],
            'administrator' => [Episciences_Acl::ROLE_ADMIN],
            'secretary' => [Episciences_Acl::ROLE_SECRETARY],
            'webmaster' => [Episciences_Acl::ROLE_WEBMASTER],
        ];
    }

    #[DataProvider('authorizedRolesProvider')]
    public function testUsersWithWebsiteRightsCanRemoveAPublicResource(string $role): void
    {
        $this->logInAs($role);

        $this->dispatch('publicAction', ['method' => 'remove']);

        self::assertNotSame(403, $this->response->getHttpResponseCode());
        self::assertFileDoesNotExist(REVIEW_PATH . 'public/' . $this->fileName);
    }

    #[DataProvider('authorizedRolesProvider')]
    public function testUsersWithWebsiteRightsReachTheLanguageSettings(string $role): void
    {
        $this->logInAs($role);

        // Opening the page only: a submission would save the languages of the journal
        $controller = new WebsiteDefaultController(new Zend_Controller_Request_HttpTestCase(), $this->response);
        $controller->commonAction();

        self::assertNotSame(403, $this->response->getHttpResponseCode());
        self::assertInstanceOf(\Zend_Form::class, $controller->view->form);
    }
}
