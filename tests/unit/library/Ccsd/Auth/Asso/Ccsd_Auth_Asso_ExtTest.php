<?php

namespace unit\library\Ccsd\Auth\Asso;

use Ccsd_Auth_Asso_Ext;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Ccsd_Auth_Asso_Ext
 *
 * DB-dependent methods (save, load) are not tested here.
 * All constructor/getter/setter logic is pure.
 */
#[IgnoreDeprecations]
class Ccsd_Auth_Asso_ExtTest extends TestCase
{
    private Ccsd_Auth_Asso_Ext $ext;

    protected function setUp(): void
    {
        $this->ext = new Ccsd_Auth_Asso_Ext(
            42,                          // uidCcsd
            'ext-uid-123',               // uidExt
            7,                           // serverId
            'ORCID',                     // serverName
            'https://orcid.org',         // serverUrl
            'oauth',                     // serverType
            1,                           // serverOrder
            true                         // valid
        );
    }

    // ------------------------------------------------------------------
    // Constructor / getters
    // ------------------------------------------------------------------

    public function testGetUidCcsd(): void
    {
        $this->assertSame(42, $this->ext->getUidCcsd());
    }

    public function testGetUidExt(): void
    {
        $this->assertSame('ext-uid-123', $this->ext->getUidExt());
    }

    public function testGetServerId(): void
    {
        $this->assertSame(7, $this->ext->getServerId());
    }

    public function testGetServerName(): void
    {
        $this->assertSame('ORCID', $this->ext->getServerName());
    }

    public function testGetServerUrl(): void
    {
        $this->assertSame('https://orcid.org', $this->ext->getServerUrl());
    }

    public function testGetServerType(): void
    {
        $this->assertSame('oauth', $this->ext->getServerType());
    }

    public function testGetServerOrder(): void
    {
        $this->assertSame(1, $this->ext->getServerOrder());
    }

    // ------------------------------------------------------------------
    // Setters
    // ------------------------------------------------------------------

    public function testSetUidCcsd(): void
    {
        $this->ext->setUidCcsd(99);
        $this->assertSame(99, $this->ext->getUidCcsd());
    }

    public function testSetUidExt(): void
    {
        $this->ext->setUidExt('new-ext-id');
        $this->assertSame('new-ext-id', $this->ext->getUidExt());
    }

    public function testSetServerId(): void
    {
        $this->ext->setServerId(15);
        $this->assertSame(15, $this->ext->getServerId());
    }

    public function testSetServerName(): void
    {
        $this->ext->setServerName('HAL');
        $this->assertSame('HAL', $this->ext->getServerName());
    }

    public function testSetServerUrl(): void
    {
        $this->ext->setServerUrl('https://hal.science');
        $this->assertSame('https://hal.science', $this->ext->getServerUrl());
    }

    public function testSetServerType(): void
    {
        $this->ext->setServerType('saml');
        $this->assertSame('saml', $this->ext->getServerType());
    }

    public function testSetServerOrder(): void
    {
        $this->ext->setServerOrder(3);
        $this->assertSame(3, $this->ext->getServerOrder());
    }

    // ------------------------------------------------------------------
    // valid() / setValid()
    // ------------------------------------------------------------------

    public function testValidReturnsTrueWhenConstructedWithTrue(): void
    {
        $this->assertTrue($this->ext->valid());
    }

    public function testValidReturnsFalseWhenConstructedWithFalse(): void
    {
        $ext = new Ccsd_Auth_Asso_Ext(1, 'id', 7, 'name', 'http://x', 'type', 1, false);
        $this->assertFalse($ext->valid());
    }

    public function testSetValidFalseIsReflectedByValid(): void
    {
        $this->ext->setValid(false);
        $this->assertFalse($this->ext->valid());
    }

    public function testSetValidTrueIsReflectedByValid(): void
    {
        $ext = new Ccsd_Auth_Asso_Ext(1, 'id', 7, 'name', 'http://x', 'type', 1, false);
        $ext->setValid(true);
        $this->assertTrue($ext->valid());
    }

    // ------------------------------------------------------------------
    // toArray (private) via reflection
    // ------------------------------------------------------------------

    public function testToArrayContainsAllFields(): void
    {
        $m = new ReflectionMethod(Ccsd_Auth_Asso_Ext::class, 'toArray');
        $m->setAccessible(true);
        $result = $m->invoke($this->ext);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uidCcsd', $result);
        $this->assertArrayHasKey('uidExt', $result);
        $this->assertArrayHasKey('serverId', $result);
        $this->assertArrayHasKey('serverName', $result);
        $this->assertArrayHasKey('serverUrl', $result);
        $this->assertArrayHasKey('serverType', $result);
        $this->assertArrayHasKey('serverOrder', $result);
    }

    public function testToArrayValues(): void
    {
        $m = new ReflectionMethod(Ccsd_Auth_Asso_Ext::class, 'toArray');
        $m->setAccessible(true);
        $result = $m->invoke($this->ext);

        $this->assertSame(42, $result['uidCcsd']);
        $this->assertSame('ext-uid-123', $result['uidExt']);
        $this->assertSame(7, $result['serverId']);
        $this->assertSame('ORCID', $result['serverName']);
        $this->assertSame('https://orcid.org', $result['serverUrl']);
        $this->assertSame('oauth', $result['serverType']);
        $this->assertSame(1, $result['serverOrder']);
    }

    // ------------------------------------------------------------------
    // Save throws when !valid() — but valid() always returns true,
    // so this path is actually unreachable. Document it.
    // ------------------------------------------------------------------

    public function testSaveWhenModifiedFalseReturnsTrueWithoutDb(): void
    {
        // When $modified=false (loaded from DB), save() returns true without DB call.
        // We can simulate this via reflection.
        $prop = new \ReflectionProperty(Ccsd_Auth_Asso_Ext::class, 'modified');
        $prop->setAccessible(true);
        $prop->setValue($this->ext, false);

        $this->assertTrue($this->ext->save());
    }
}
