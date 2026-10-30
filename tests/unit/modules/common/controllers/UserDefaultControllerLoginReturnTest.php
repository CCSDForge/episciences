<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_Auth_Adapter_CasAbstract;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UserDefaultController;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * Page displayed after logging in, and URL the CAS server sends the user back to: only the
 * parameters of the URL are carried over, never the fields of the posted login form nor the CAS ticket.
 */
final class UserDefaultControllerLoginReturnTest extends TestCase
{
    // the test requests write the query string and the posted form to $_GET and $_POST
    /** @var array<string, mixed> */
    private array $previousServer;

    protected function setUp(): void
    {
        $_GET = $_POST = [];
        $this->previousServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_GET = $_POST = [];
        $_SERVER = $this->previousServer;
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function returnParams(array $route, array $query = [], array $post = []): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setQuery($query)->setPost($post)->setParams($route);

        $controller = new UserDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $method = new ReflectionMethod(UserDefaultController::class, 'loginReturnParams');

        return $method->invoke($controller, $request);
    }

    public function testPostedLoginFormIsNotCarriedOver(): void
    {
        $params = self::returnParams(
            ['controller' => 'user', 'action' => 'login', 'forward-controller' => 'reviewer', 'forward-action' => 'invitation', 'id' => '1'],
            [],
            ['username' => 'jdoe', 'password' => 'secret', 'csrf_token' => 'abc', 'Connexion' => 'Sign in']
        );

        self::assertSame(
            ['controller' => 'user', 'action' => 'login', 'forward-controller' => 'reviewer', 'forward-action' => 'invitation', 'id' => '1'],
            $params
        );
    }

    public function testQueryStringIsCarriedOver(): void
    {
        $params = self::returnParams(
            ['controller' => 'user', 'action' => 'login'],
            ['forward-controller' => 'user', 'forward-action' => 'edit']
        );

        self::assertSame('user', $params['forward-controller']);
        self::assertSame('edit', $params['forward-action']);
    }

    /**
     * Same precedence as getParam(): a route parameter wins over the query string
     */
    public function testRouteParameterWinsOverQueryString(): void
    {
        $params = self::returnParams(['id' => '1'], ['id' => '2']);

        self::assertSame('1', $params['id']);
    }

    /**
     * Back from the CAS server, the ticket has been used: it is not carried over
     */
    public function testCasTicketIsNotCarriedOver(): void
    {
        $params = self::returnParams(['forward-controller' => 'paper', 'forward-action' => 'view'], ['ticket' => 'ST-1']);

        self::assertArrayNotHasKey('ticket', $params);
        self::assertSame('paper', $params['forward-controller']);
    }

    public function testCasServiceUrlOnlyCarriesTheUrlParameters(): void
    {
        $_SERVER['SERVER_NAME'] = 'journal.example.org';
        $_SERVER['SERVER_PORT'] = '443';

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')
            ->setQuery(['ticket' => 'ST-1'])
            ->setPost(['username' => 'jdoe', 'password' => 'secret'])
            ->setParams(['controller' => 'user', 'action' => 'login', 'forward-controller' => 'paper', 'forward-action' => 'view', 'id' => '1']);

        // a CAS adapter that stops before contacting the CAS server
        $adapter = new class extends Ccsd_Auth_Adapter_CasAbstract {
            protected function setLogger(): void
            {
            }

            protected function setCasOptions(): self
            {
                return $this;
            }

            public function pre_auth($controller): bool
            {
                return false;
            }
        };

        $controller = new class ($request, new Zend_Controller_Response_HttpTestCase()) extends UserDefaultController {
            public Ccsd_Auth_Adapter_CasAbstract $adapter;

            protected function loginAdapter(): \Ccsd\Auth\Adapter\Mysql|Ccsd_Auth_Adapter_CasAbstract
            {
                return $this->adapter;
            }
        };
        $controller->adapter = $adapter;

        $controller->loginAction();

        self::assertSame(
            SERVER_PROTOCOL . '://journal.example.org/user/login/forward-controller/paper/forward-action/view/id/1',
            $adapter->getServiceURL()
        );
    }
}
