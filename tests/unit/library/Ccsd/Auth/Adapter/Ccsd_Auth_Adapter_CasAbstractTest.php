<?php

namespace unit\library\Ccsd\Auth\Adapter;

use Ccsd_Auth_Adapter_CasAbstract;
use Ccsd_User_Models_User;
use PHPUnit\Framework\TestCase;

/**
 * Minimal concrete subclass to allow instantiation of the abstract class
 * without triggering CAS constant lookups in the constructor.
 */
class CasAdapterTestDouble extends Ccsd_Auth_Adapter_CasAbstract
{
    protected function setLogger(): void {}
    protected function setCasOptions(): self { return $this; }
}

/**
 * Unit tests for Ccsd_Auth_Adapter_CasAbstract
 *
 * Tests pure logic: getters/setters, setServiceURL with empty params,
 * setIdentityStructure, constants, and buildLoginDestinationUrl via setServiceURL.
 * authenticate() requires phpCAS and is not tested here.
 *
 * @covers Ccsd_Auth_Adapter_CasAbstract
 */
class Ccsd_Auth_Adapter_CasAbstractTest extends TestCase
{
    private CasAdapterTestDouble $adapter;

    protected function setUp(): void
    {
        $this->adapter = new CasAdapterTestDouble();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testDefaultLoginActionConstant(): void
    {
        $this->assertSame('login', Ccsd_Auth_Adapter_CasAbstract::DEFAULT_LOGIN_ACTION);
    }

    public function testDefaultLogoutActionConstant(): void
    {
        $this->assertSame('logout', Ccsd_Auth_Adapter_CasAbstract::DEFAULT_LOGOUT_ACTION);
    }

    public function testDefaultAuthControllerConstant(): void
    {
        $this->assertSame('user', Ccsd_Auth_Adapter_CasAbstract::DEFAULT_AUTH_CONTROLLER);
    }

    // -------------------------------------------------------------------------
    // setCasVersion / getCasVersion
    // -------------------------------------------------------------------------

    public function testSetAndGetCasVersion(): void
    {
        $this->adapter->setCasVersion('2.0');
        $this->assertSame('2.0', $this->adapter->getCasVersion());
    }

    public function testSetCasVersionReturnsFluent(): void
    {
        $result = $this->adapter->setCasVersion('3.0');
        $this->assertInstanceOf(CasAdapterTestDouble::class, $result);
    }

    // -------------------------------------------------------------------------
    // setCasHostname / getCasHostname
    // -------------------------------------------------------------------------

    public function testSetAndGetCasHostname(): void
    {
        $this->adapter->setCasHostname('cas.example.com');
        $this->assertSame('cas.example.com', $this->adapter->getCasHostname());
    }

    public function testSetCasHostnameReturnsFluent(): void
    {
        $result = $this->adapter->setCasHostname('host.example.com');
        $this->assertInstanceOf(CasAdapterTestDouble::class, $result);
    }

    // -------------------------------------------------------------------------
    // setCasPort / getCasPort
    // -------------------------------------------------------------------------

    public function testSetAndGetCasPort(): void
    {
        $this->adapter->setCasPort(8443);
        $this->assertSame(8443, $this->adapter->getCasPort());
    }

    // -------------------------------------------------------------------------
    // setCasUrl / getCasUrl
    // -------------------------------------------------------------------------

    public function testSetAndGetCasUrl(): void
    {
        $this->adapter->setCasUrl('/cas');
        $this->assertSame('/cas', $this->adapter->getCasUrl());
    }

    // -------------------------------------------------------------------------
    // setCasStartSessions / getCasStartSessions
    // -------------------------------------------------------------------------

    public function testDefaultCasStartSessionsIsFalse(): void
    {
        $this->assertFalse($this->adapter->getCasStartSessions());
    }

    public function testSetCasStartSessionsTrue(): void
    {
        $this->adapter->setCasStartSessions(true);
        $this->assertTrue($this->adapter->getCasStartSessions());
    }

    public function testSetCasStartSessionsFalse(): void
    {
        $this->adapter->setCasStartSessions(true);
        $this->adapter->setCasStartSessions(false);
        $this->assertFalse($this->adapter->getCasStartSessions());
    }

    // -------------------------------------------------------------------------
    // setCasSslValidation / getCasSslValidation
    // -------------------------------------------------------------------------

    public function testSetAndGetCasSslValidation(): void
    {
        $this->adapter->setCasSslValidation(true);
        $this->assertTrue($this->adapter->getCasSslValidation());
    }

    // -------------------------------------------------------------------------
    // setCasCACert / getCasCACert
    // -------------------------------------------------------------------------

    public function testSetAndGetCasCACert(): void
    {
        $this->adapter->setCasCACert('/etc/ssl/certs/ca.pem');
        $this->assertSame('/etc/ssl/certs/ca.pem', $this->adapter->getCasCACert());
    }

    // -------------------------------------------------------------------------
    // setServiceURL / getServiceURL — empty params
    // -------------------------------------------------------------------------

    public function testDefaultServiceUrlIsEmpty(): void
    {
        $this->assertSame('', $this->adapter->getServiceURL());
    }

    public function testSetServiceUrlWithEmptyParamsStoresEmptyString(): void
    {
        $this->adapter->setServiceURL([]);
        $this->assertSame('', $this->adapter->getServiceURL());
    }

    public function testSetServiceUrlReturnsFluent(): void
    {
        $result = $this->adapter->setServiceURL([]);
        $this->assertInstanceOf(CasAdapterTestDouble::class, $result);
    }

    // -------------------------------------------------------------------------
    // setServiceURL — delegates to buildLoginDestinationUrl (via $_SERVER)
    // -------------------------------------------------------------------------

    public function testSetServiceUrlBuildsUrlWithForwardControllerAndAction(): void
    {
        $_SERVER['SERVER_NAME'] = 'test.example.com';
        $_SERVER['SERVER_PORT'] = '443'; // standard HTTPS port — no port suffix expected

        $this->adapter->setServiceURL([
            'forward-controller' => 'paper',
            'forward-action'     => 'view',
        ]);

        $url = $this->adapter->getServiceURL();
        $this->assertStringContainsString('/user/login', $url);
        $this->assertStringContainsString('forward-controller/paper', $url);
        $this->assertStringContainsString('forward-action/view', $url);
    }

    public function testSetServiceUrlWithNullForwardControllerUsesDefault(): void
    {
        $_SERVER['SERVER_NAME'] = 'test.example.com';
        $_SERVER['SERVER_PORT'] = '443';

        $this->adapter->setServiceURL(['other-param' => 'value']);

        $url = $this->adapter->getServiceURL();
        // No forward-controller param → default path used
        $this->assertStringContainsString('/user/login', $url);
        $this->assertStringContainsString('forward-controller/user', $url);
    }

    public function testSetServiceUrlSkipsInternalParamsFromAppending(): void
    {
        $_SERVER['SERVER_NAME'] = 'test.example.com';
        $_SERVER['SERVER_PORT'] = '443';

        $this->adapter->setServiceURL([
            'forward-controller' => 'paper',
            'forward-action'     => 'view',
            'controller'         => 'user',   // should be skipped
            'action'             => 'login',  // should be skipped
            'module'             => 'journal', // should be skipped
            'ticket'             => 'ST-123',  // should be skipped
        ]);

        $url = $this->adapter->getServiceURL();
        $this->assertStringNotContainsString('controller/user', $url);
        $this->assertStringNotContainsString('action/login', $url);
        $this->assertStringNotContainsString('module', $url);
        $this->assertStringNotContainsString('ticket', $url);
    }

    public function testSetServiceUrlWithNonStandardPortAppendsPort(): void
    {
        $_SERVER['SERVER_NAME'] = 'test.example.com';
        $_SERVER['SERVER_PORT'] = '8080';

        $this->adapter->setServiceURL([
            'forward-controller' => 'index',
            'forward-action'     => 'index',
        ]);

        $url = $this->adapter->getServiceURL();
        $this->assertStringContainsString(':8080', $url);
    }

    public function testSetServiceUrlWithPort80DoesNotAppendPort(): void
    {
        $_SERVER['SERVER_NAME'] = 'test.example.com';
        $_SERVER['SERVER_PORT'] = '80';

        $this->adapter->setServiceURL([
            'forward-controller' => 'index',
            'forward-action'     => 'index',
        ]);

        $url = $this->adapter->getServiceURL();
        $this->assertStringNotContainsString(':80', $url);
    }

    // -------------------------------------------------------------------------
    // setIdentityStructure / getIdentityStructure
    // -------------------------------------------------------------------------

    public function testDefaultIdentityStructureIsNull(): void
    {
        $this->assertNull($this->adapter->getIdentityStructure());
    }

    public function testSetAndGetIdentityStructure(): void
    {
        $user = new Ccsd_User_Models_User();
        $user->setUid(42);
        $this->adapter->setIdentityStructure($user);

        $result = $this->adapter->getIdentityStructure();
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
        $this->assertSame(42, $result->getUid());
    }

    // -------------------------------------------------------------------------
    // alt_login default implementation
    // -------------------------------------------------------------------------

    public function testAltLoginReturnsTrueByDefault(): void
    {
        $user = new Ccsd_User_Models_User();
        $result = $this->adapter->alt_login($user, []);
        $this->assertTrue($result);
    }
}
