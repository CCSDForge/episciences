<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_Auth_Adapter_CasAbstract;
use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * URL the CAS server sends the user back to after logging in: only the parameters of the login URL are
 * carried over, never the fields of a posted form nor the CAS ticket.
 */
final class UserDefaultControllerLoginServiceUrlTest extends ControllerTestCase
{
    /** @var array<string, mixed> */
    private array $previousServer;

    protected function setUp(): void
    {
        self::loadController('common', 'UserDefaultController');
        // the hostname of the service URL is read from $_SERVER
        $this->previousServer = $_SERVER;
        $_SERVER['SERVER_NAME'] = 'journal.example.org';
        $_SERVER['SERVER_PORT'] = '443';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
    }

    /**
     * Service URL given to the CAS adapter when loginAction() runs on $request
     */
    private function serviceUrl(Zend_Controller_Request_HttpTestCase $request): string
    {
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

        $controller = new class ($request, new Zend_Controller_Response_HttpTestCase()) extends \UserDefaultController {
            public static ?Ccsd_Auth_Adapter_CasAbstract $adapter = null;

            protected function loginAdapter(): \Ccsd\Auth\Adapter\Mysql|Ccsd_Auth_Adapter_CasAbstract
            {
                return self::$adapter;
            }
        };
        $controller::$adapter = $adapter;

        $this->dispatch($controller::class, 'login', $request);

        return $adapter->getServiceURL();
    }

    public function testServiceUrlOnlyCarriesTheUrlParameters(): void
    {
        $request = $this->postRequest(
            ['username' => 'jdoe', 'password' => 'MyS3cret-password'],
            false,
            ['controller' => 'user', 'action' => 'login', 'forward-controller' => 'paper', 'forward-action' => 'view', 'id' => '1']
        )->setQuery(['ticket' => 'ST-1-abc', 'tmp' => 'legacy']);

        self::assertSame(
            SERVER_PROTOCOL . '://journal.example.org/user/login/forward-controller/paper/forward-action/view/tmp/legacy/id/1',
            $this->serviceUrl($request)
        );
    }

    public function testPageToReturnToSentInTheQueryIsCarriedOver(): void
    {
        // as sent by the login form of the navbar
        $request = $this->getRequest(
            ['forward-controller' => 'paper', 'forward-action' => 'view', 'id' => '1'],
            ['controller' => 'user', 'action' => 'login']
        );

        self::assertSame(
            SERVER_PROTOCOL . '://journal.example.org/user/login/forward-controller/paper/forward-action/view/id/1',
            $this->serviceUrl($request)
        );
    }

    public function testCasTicketGivenAsRouteParameterIsNotCarriedOver(): void
    {
        $request = $this->getRequest([], ['forward-controller' => 'paper', 'forward-action' => 'view', 'ticket' => 'ST-1-abc', 'id' => '1']);

        self::assertSame(
            SERVER_PROTOCOL . '://journal.example.org/user/login/forward-controller/paper/forward-action/view/id/1',
            $this->serviceUrl($request)
        );
    }

    public function testNonScalarParametersAreNotCarriedOver(): void
    {
        $request = $this->getRequest(['forward-controller' => ['paper'], 'id' => ['1']], ['forward-action' => 'view']);

        // without a return controller, the default destination
        self::assertSame(
            SERVER_PROTOCOL . '://journal.example.org/user/login/forward-controller/user',
            $this->serviceUrl($request)
        );
    }
}
