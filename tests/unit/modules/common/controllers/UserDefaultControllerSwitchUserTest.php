<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\User\SuLogger;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_Csrf_Helper;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Front;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_View;

/**
 * Switch user (su / unsu) and profile edition, exercised through the controller.
 *
 * The database is a partial mock: any audit row written by SuLogger is captured.
 */
final class UserDefaultControllerSwitchUserTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<array<string, mixed>> rows inserted in user_su_log */
    private array $auditRows = [];

    private bool $previousUnitTestEnabled;

    /** @var array<string, mixed> */
    private array $previousServer;

    /** @var array<string, mixed> */
    private array $previousPost;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['insert', 'fetchRow', 'fetchAll', 'fetchOne', 'query'])
            ->getMock();
        $this->adapter->method('insert')->willReturnCallback(function (string $table, array $data): int {
            if ($table === SuLogger::TABLE_NAME) {
                $this->auditRows[] = $data;
            }
            return 1;
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        // Regenerating the session id is a no-op in unit test mode
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        // Redirects are recorded on the response instead of ending the process
        $redirector = new Zend_Controller_Action_Helper_Redirector();
        $redirector->setExit(false);
        Zend_Controller_Action_HelperBroker::addHelper($redirector);

        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        $this->previousServer = $_SERVER;
        // Zend_Controller_Request_HttpTestCase::setPost() writes the $_POST superglobal
        $this->previousPost = $_POST;
        $_POST = [];

        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::clearImpersonation();
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::clearImpersonation();
        Zend_Controller_Action_HelperBroker::removeHelper('redirector');
        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        $_SERVER = $this->previousServer;
        $_POST = $this->previousPost;
    }

    /**
     * @param list<string> $roles
     */
    private function user(int $uid, array $roles): Episciences_User
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([RVID => $roles]);
        $user->method('getScreenName')->willReturn('User ' . $uid);

        return $user;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $params
     */
    private function request(string $method, array $post = [], array $params = []): Zend_Controller_Request_HttpTestCase
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method);
        if ($post !== []) {
            $request->setPost($post);
        }
        $request->setParams($params);

        return $request;
    }

    private function controller(Zend_Controller_Request_HttpTestCase $request): \UserDefaultController
    {
        $controller = new \UserDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->view = new Zend_View();

        return $controller;
    }

    private function impersonate(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_SECRETARY]));
        Episciences_Auth::startImpersonation($this->user(20, [Episciences_Acl::ROLE_AUTHOR]));
    }

    // ------------------------------------------------------------------ su

    public function testSuIgnoresAGetRequest(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_SECRETARY]));
        // Refused before the target account is even looked up
        $this->adapter->expects(self::never())->method('query');

        $this->controller($this->request('GET', [], ['uid' => 20]))->suAction();

        self::assertSame(10, Episciences_Auth::getUid());
        self::assertFalse(Episciences_Auth::isImpersonating());
        self::assertSame([], $this->auditRows);
    }

    public function testSuIgnoresAPostWithoutTheRequestToken(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_SECRETARY]));
        // Refused before the target account is even looked up
        $this->adapter->expects(self::never())->method('query');

        $this->controller($this->request('POST', ['uid' => 20, 'csrf_token' => 'forged']))->suAction();

        self::assertSame(10, Episciences_Auth::getUid());
        self::assertFalse(Episciences_Auth::isImpersonating());
        self::assertSame([], $this->auditRows);
    }

    public function testSuByAnEditorIsDeniedAndLogged(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_EDITOR]));
        $token = Episciences_Csrf_Helper::getSessionToken();

        $this->controller($this->request('POST', ['uid' => 20, 'csrf_token' => $token]))->suAction();

        self::assertSame(10, Episciences_Auth::getUid());
        self::assertCount(1, $this->auditRows);
        self::assertSame(SuLogger::ACTION_DENIED, $this->auditRows[0]['action']);
        self::assertSame(10, $this->auditRows[0]['from_uid']);
    }

    // ------------------------------------------------------------------ unsu

    public function testUnsuIgnoresAGetRequest(): void
    {
        $this->impersonate();

        $this->controller($this->request('GET'))->unsuAction();

        self::assertSame(20, Episciences_Auth::getUid());
        self::assertTrue(Episciences_Auth::isImpersonating());
        self::assertSame([], $this->auditRows);
    }

    public function testUnsuIgnoresAPostWithoutTheRequestToken(): void
    {
        $this->impersonate();

        $this->controller($this->request('POST', ['csrf_token' => 'forged']))->unsuAction();

        self::assertSame(20, Episciences_Auth::getUid());
        self::assertTrue(Episciences_Auth::isImpersonating());
    }

    public function testUnsuRestoresTheRealIdentityAndLogsIt(): void
    {
        $this->impersonate();
        $token = Episciences_Csrf_Helper::getSessionToken();
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        $this->controller($this->request('POST', ['csrf_token' => $token]))->unsuAction();

        self::assertSame(10, Episciences_Auth::getUid());
        self::assertFalse(Episciences_Auth::isImpersonating());
        self::assertCount(1, $this->auditRows);
        self::assertSame(SuLogger::ACTION_UNSU, $this->auditRows[0]['action']);
        self::assertSame(10, $this->auditRows[0]['from_uid']);
        self::assertSame(20, $this->auditRows[0]['to_uid']);
        self::assertSame('198.51.100.7', $this->auditRows[0]['ip_address']);
    }

    public function testUnsuWithAStackLeftByAnotherLoginKeepsTheCurrentUser(): void
    {
        $this->impersonate();
        // Logout without unsu, then another user logs in within the same session
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->getStorage()->write($this->user(30, [Episciences_Acl::ROLE_MEMBER]));
        $token = Episciences_Csrf_Helper::getSessionToken();

        $this->controller($this->request('POST', ['csrf_token' => $token]))->unsuAction();

        self::assertSame(30, Episciences_Auth::getUid());
        self::assertSame([], $this->auditRows);
    }

    // ------------------------------------------------------------------ profile edition

    public function testProfileValuesAreBoundToTheEditedAccountNotToThePostedUid(): void
    {
        $method = new ReflectionMethod(\UserDefaultController::class, 'bindProfileValuesToAccount');
        $method->setAccessible(true);

        $values = $method->invoke(
            null,
            ['UID' => 10, 'API_PASSWORD' => 'x'],
            ['ccsd' => ['UID' => 1, 'EMAIL' => 'attacker@example.org'], 'episciences' => ['SCREEN_NAME' => 'Me']],
            10
        );

        self::assertSame(10, $values['UID']);
        self::assertSame('Me', $values['SCREEN_NAME']);
    }

    public function testEditingAnotherAccountIsRefusedToANonRootMember(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_MEMBER]));
        $this->adapter->expects(self::never())->method('fetchRow');

        $controller = $this->controller($this->request('POST', ['ccsd' => ['UID' => 1]], ['userid' => 1]));
        $controller->editAction();

        self::assertTrue($controller->getResponse()->isRedirect());
        self::assertNull($controller->view->form, 'no form is built for a refused target');
    }

    // ------------------------------------------------------------------ photo deletion

    /**
     * @param array<string, mixed> $post
     */
    private function deletePhoto(array $post, ?string $token): \UserDefaultController
    {
        $request = $this->request('POST', $post);
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        if ($token !== null) {
            $request->setHeader('X-CSRF-Token', $token);
        }
        $controller = $this->controller($request);
        $controller->ajaxdeletephotoAction();

        return $controller;
    }

    public function testPhotoDeletionWithoutTheRequestTokenIsRefused(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_MEMBER]));
        // The photo owner is never even looked up
        $this->adapter->expects(self::never())->method('query');
        $this->adapter->expects(self::never())->method('fetchOne');

        $controller = $this->deletePhoto(['uid' => 10], null);

        self::assertSame(403, $controller->getResponse()->getHttpResponseCode());
        self::assertSame('', $controller->getResponse()->getBody());
    }

    public function testPhotoDeletionWithAForgedTokenIsRefused(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_MEMBER]));
        Episciences_Csrf_Helper::getSessionToken();

        $controller = $this->deletePhoto(['uid' => 10], 'forged');

        self::assertSame(403, $controller->getResponse()->getHttpResponseCode());
    }

    public function testPhotoDeletionOfAnotherAccountIsRefusedToAMember(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_MEMBER]));
        $this->adapter->expects(self::never())->method('fetchOne');

        $controller = $this->deletePhoto(['uid' => 11], Episciences_Csrf_Helper::getSessionToken());

        self::assertSame(403, $controller->getResponse()->getHttpResponseCode());
    }

    public function testPhotoDeletionOutsideAnAjaxCallIsNotFound(): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->user(10, [Episciences_Acl::ROLE_MEMBER]));

        $controller = $this->controller($this->request('POST', ['uid' => 10]));
        $controller->ajaxdeletephotoAction();

        self::assertSame(404, $controller->getResponse()->getHttpResponseCode());
    }
}
