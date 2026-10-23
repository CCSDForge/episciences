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
 * Form token check of the website settings (common, style and menu actions of
 * WebsiteDefaultController).
 *
 * A submission without the per-session token must save nothing and tell the user the session
 * has expired. The actions are called for real on a partially mocked database adapter.
 */
final class WebsiteDefaultControllerFormTokenTest extends TestCase
{
    private const EXPIRED = 'Votre session a expiré, veuillez réessayer.';

    /** Calls on the menu made only when the submitted pages are processed */
    private const MENU_WRITES = ['setPage', 'save', 'createNavigation'];

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

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
            ->onlyMethods(['fetchAll', 'fetchPairs', 'fetchRow', 'fetchCol', 'insert', 'update', 'delete'])
            ->getMock();
        // Nothing saved yet for the journal
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchPairs')->willReturn([]);
        $this->adapter->method('fetchRow')->willReturn(false);
        $this->adapter->method('fetchCol')->willReturn([]);
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

        // Messages left by the other tests of the run
        (new \Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();

        // The session holds a token the requests below do not send
        Episciences_Csrf_Helper::getSessionToken();
    }

    protected function tearDown(): void
    {
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

    /**
     * @param array<string, mixed> $post
     */
    private function dispatch(string $action, array $post): WebsiteDefaultController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $controller = new WebsiteDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->$action();

        return $controller;
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        $flash = new Zend_Controller_Action_Helper_FlashMessenger();

        return $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages();
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function settingsActionsProvider(): array
    {
        return [
            'languages' => ['commonAction', ['languages' => ['en']]],
            'style' => ['styleAction', ['navigation' => 'horizontal']],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsActionsProvider')]
    public function testSettingsAreNotSavedWithoutToken(string $action, array $post): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        $this->dispatch($action, $post);

        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('settingsActionsProvider')]
    public function testSettingsAreNotSavedWithAWrongToken(string $action, array $post): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        $this->dispatch($action, $post + ['csrf_token' => 'forged']);

        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    /**
     * Runs menuAction() on a POST and returns the methods called on the navigation kept in
     * session, the same double standing for its pages and their forms.
     *
     * @param array<string, mixed> $post
     * @return list<string>
     */
    private function postMenu(array $post): array
    {
        // The pages of the site are managed by the secretaries and the webmasters
        $user = $this->createMock(Episciences_User::class);
        $user->method('getAllRoles')->willReturn([RVID => [Episciences_Acl::ROLE_SECRETARY]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);

        $website = new class {
            /** @var list<string> */
            public array $calls = [];

            /**
             * @param array<int, mixed> $arguments
             */
            public function __call(string $name, array $arguments): mixed
            {
                $this->calls[] = $name;
                return $this;
            }
        };

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;
        $controller = new WebsiteDefaultController($request, new Zend_Controller_Response_HttpTestCase());

        // A plain holder: a real session namespace would try to serialize the anonymous double
        $sessionProperty = new \ReflectionProperty(WebsiteDefaultController::class, '_session');
        $sessionProperty->setAccessible(true);
        $sessionProperty->setValue($controller, (object)['website' => $website]);

        $controller->menuAction();

        return $website->calls;
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function refusedTokenProvider(): array
    {
        return [
            'without token' => [null],
            'with a wrong token' => ['forged'],
        ];
    }

    /**
     * The menu is shown again from the session, with the changes not saved yet.
     */
    #[DataProvider('refusedTokenProvider')]
    public function testMenuIsNotProcessedWhenTheTokenIsRefused(?string $token): void
    {
        $post = ['pages_1' => ['visibility' => '0']];
        if ($token !== null) {
            $post['csrf_token'] = $token;
        }

        $calls = $this->postMenu($post);

        self::assertSame([], array_values(array_intersect(self::MENU_WRITES, $calls)));
        self::assertSame([self::EXPIRED], $this->errorMessages());
    }

    public function testMenuIsProcessedWithAValidToken(): void
    {
        $calls = $this->postMenu(['pages_1' => ['visibility' => '0'], 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()]);

        self::assertSame(self::MENU_WRITES, array_values(array_intersect(self::MENU_WRITES, $calls)));
        self::assertSame([], $this->errorMessages());
    }
}
