<?php

namespace unit\library\Ccsd\Auth\Adapter;

use Ccsd\Auth\Adapter\Mysql;
use Ccsd_User_Models_User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth_Exception;

/**
 * Unit tests for Ccsd\Auth\Adapter\Mysql
 *
 * authenticate() and logout() require DB/session — not tested here.
 * All pure-logic paths are covered.
 *
 * Bug fixes covered:
 * A2 - setUsername/setCredential: UTF-8 NBSP (\xC2\xA0) trimmed before empty check.
 */
class Ccsd_Auth_Adapter_MysqlTest extends TestCase
{
    private Mysql $adapter;

    protected function setUp(): void
    {
        $this->adapter = new Mysql();
    }

    // ------------------------------------------------------------------
    // toHtml
    // ------------------------------------------------------------------

    public function testToHtmlReturnsAdapterName(): void
    {
        $this->assertSame('MYSQL', $this->adapter->toHtml());
    }

    // ------------------------------------------------------------------
    // Identity / IdentityStructure
    // ------------------------------------------------------------------

    public function testGetIdentityNullByDefault(): void
    {
        $this->assertNull($this->adapter->getIdentity());
    }

    public function testSetIdentityAndGet(): void
    {
        $user = $this->createMock(Ccsd_User_Models_User::class);
        $this->adapter->setIdentity($user);
        $this->assertSame($user, $this->adapter->getIdentity());
    }

    public function testSetIdentityReturnsSelf(): void
    {
        $user = $this->createMock(Ccsd_User_Models_User::class);
        $result = $this->adapter->setIdentity($user);
        $this->assertSame($this->adapter, $result);
    }

    public function testGetIdentityStructureNullByDefault(): void
    {
        $this->assertNull($this->adapter->getIdentityStructure());
    }

    public function testSetIdentityStructureSetsBoth(): void
    {
        $user = $this->createMock(Ccsd_User_Models_User::class);
        $this->adapter->setIdentityStructure($user);
        $this->assertSame($user, $this->adapter->getIdentity());
        $this->assertSame($user, $this->adapter->getIdentityStructure());
    }

    public function testSetIdentityStructureReturnsSelf(): void
    {
        $user = $this->createMock(Ccsd_User_Models_User::class);
        $result = $this->adapter->setIdentityStructure($user);
        $this->assertSame($this->adapter, $result);
    }

    // ------------------------------------------------------------------
    // setServiceURL (no-op)
    // ------------------------------------------------------------------

    public function testSetServiceUrlReturnsSelf(): void
    {
        $result = $this->adapter->setServiceURL(['foo' => 'bar']);
        $this->assertSame($this->adapter, $result);
    }

    // ------------------------------------------------------------------
    // createUserFromAdapter
    // ------------------------------------------------------------------

    public function testCreateUserFromAdapterReturnsFalse(): void
    {
        $this->assertFalse($this->adapter->createUserFromAdapter([], true));
        $this->assertFalse($this->adapter->createUserFromAdapter([], false));
    }

    // ------------------------------------------------------------------
    // alt_login
    // ------------------------------------------------------------------

    public function testAltLoginReturnsTrue(): void
    {
        $user = $this->createMock(Ccsd_User_Models_User::class);
        $this->assertTrue($this->adapter->alt_login($user, []));
    }

    // ------------------------------------------------------------------
    // setUsername — A2 fix: NBSP trimming, AL1: validation
    // ------------------------------------------------------------------

    private function callSetUsername(string $value): void
    {
        $m = new ReflectionMethod(Mysql::class, 'setUsername');
        $m->setAccessible(true);
        $m->invoke($this->adapter, $value);
    }

    private function callSetCredential(string $value): void
    {
        $m = new ReflectionMethod(Mysql::class, 'setCredential');
        $m->setAccessible(true);
        $m->invoke($this->adapter, $value);
    }

    public function testSetUsernameEmptyStringThrows(): void
    {
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetUsername('');
    }

    public function testSetUsernameWhitespaceOnlyThrows(): void
    {
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetUsername('   ');
    }

    public function testSetUsernameNbspOnlyThrows(): void
    {
        // A2 fix: UTF-8 non-breaking space must be treated as whitespace
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetUsername("\xC2\xA0");
    }

    public function testSetUsernameNbspMixedWithSpacesThrows(): void
    {
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetUsername(" \xC2\xA0 ");
    }

    public function testSetUsernameValidDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callSetUsername('john.doe@example.com');
    }

    public function testSetUsernameNbspMixedWithValidCharsDoesNotThrow(): void
    {
        // Leading NBSP trimmed, remaining content is valid
        $this->expectNotToPerformAssertions();
        $this->callSetUsername("\xC2\xA0john");
    }

    public function testSetCredentialEmptyStringThrows(): void
    {
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetCredential('');
    }

    public function testSetCredentialWhitespaceOnlyThrows(): void
    {
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetCredential('   ');
    }

    public function testSetCredentialNbspOnlyThrows(): void
    {
        // A2 fix
        $this->expectException(Zend_Auth_Exception::class);
        $this->callSetCredential("\xC2\xA0");
    }

    public function testSetCredentialValidDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->callSetCredential('S3cur3P@ssw0rd!');
    }
}
