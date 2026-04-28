<?php

namespace unit\library\Ccsd\Auth;

use Ccsd\Auth\AdapterFactory;
use Ccsd\Auth\Adapter\DbTable;
use Ccsd\Auth\Adapter\Idp;
use Ccsd\Auth\Adapter\Mysql;
use Ccsd_Auth_Adapter_Cas;
use Ccsd_Auth_Adapter_Orcid;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd\Auth\AdapterFactory
 *
 * Tests the factory method getTypedAdapter() for all known types and edge cases.
 * Note: instantiation of adapters is tested, not authentication itself.
 *
 * @covers \Ccsd\Auth\AdapterFactory
 */
class Ccsd_Auth_AdapterFactoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function skipIfDbTableClassMissing(): void
    {
        if (!class_exists('Ccsd\Db\Adapter\DbTable')) {
            $this->markTestSkipped('Ccsd\Db\Adapter\DbTable class not found in test env.');
        }
    }

    // -------------------------------------------------------------------------
    // Known adapter types
    // -------------------------------------------------------------------------

    public function testGetTypedAdapterDbReturnsDbTableInstance(): void
    {
        $this->skipIfDbTableClassMissing();
        $adapter = AdapterFactory::getTypedAdapter('DB');
        $this->assertInstanceOf(DbTable::class, $adapter);
    }

    public function testGetTypedAdapterCasReturnsCasInstance(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('CAS');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterIdpReturnsIdpInstance(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('IDP');
        $this->assertInstanceOf(Idp::class, $adapter);
    }

    public function testGetTypedAdapterOrcidReturnsOrcidInstance(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('ORCID');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Orcid::class, $adapter);
    }

    public function testGetTypedAdapterMysqlReturnsMysqlInstance(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('MYSQL');
        $this->assertInstanceOf(Mysql::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // Case-insensitive matching
    // -------------------------------------------------------------------------

    public function testGetTypedAdapterIsCaseInsensitiveForDb(): void
    {
        $this->skipIfDbTableClassMissing();
        $adapter = AdapterFactory::getTypedAdapter('db');
        $this->assertInstanceOf(DbTable::class, $adapter);
    }

    public function testGetTypedAdapterIsCaseInsensitiveForCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('cas');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterIsCaseInsensitiveForIdp(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('idp');
        $this->assertInstanceOf(Idp::class, $adapter);
    }

    public function testGetTypedAdapterIsCaseInsensitiveForOrcid(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('orcid');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Orcid::class, $adapter);
    }

    public function testGetTypedAdapterMixedCaseOrCid(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('OrCiD');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Orcid::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // Default / unknown type
    // -------------------------------------------------------------------------

    /**
     * @note BUG/DESIGN: Unknown types silently fall back to CAS adapter.
     *       This could mask misconfiguration (e.g. typo in authType config).
     *       Expected: an exception or null. Actual: CAS adapter.
     */
    public function testGetTypedAdapterUnknownTypeDefaultsToCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('UNKNOWN');
        // Default case returns CAS — silent fallback may hide misconfiguration
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterEmptyStringDefaultsToCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterNullCoercedToEmptyStringDefaultsToCas(): void
    {
        // getTypedAdapter performs (string) cast: null → '' → default CAS
        $adapter = AdapterFactory::getTypedAdapter(null);
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // Return type: all adapters implement AdapterInterface
    // -------------------------------------------------------------------------

    public function testAllAdaptersImplementAdapterInterface(): void
    {
        $types = ['CAS', 'IDP', 'ORCID', 'MYSQL'];
        foreach ($types as $type) {
            $adapter = AdapterFactory::getTypedAdapter($type);
            $this->assertInstanceOf(
                \Ccsd\Auth\Adapter\AdapterInterface::class,
                $adapter,
                "Adapter for type '$type' must implement AdapterInterface"
            );
        }
        // DB requires Ccsd\Db\Adapter\DbTable which is absent from this test env
    }
}
