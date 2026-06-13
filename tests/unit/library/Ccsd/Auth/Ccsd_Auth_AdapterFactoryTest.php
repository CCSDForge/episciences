<?php

namespace unit\library\Ccsd\Auth;

use Ccsd\Auth\AdapterFactory;
use Ccsd\Auth\Adapter\Mysql;
use Ccsd_Auth_Adapter_Cas;
use PHPUnit\Framework\TestCase;
use Ccsd\Db\Adapter\DbTable; // @phpstan-ignore-line

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

    public function testGetTypedAdapterIdpFallsBackToCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('IDP');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
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
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterEmptyStringDefaultsToCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter('');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    public function testGetTypedAdapterNullCoercedToEmptyStringDefaultsToCas(): void
    {
        $adapter = AdapterFactory::getTypedAdapter(null);
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // ORCID no longer supported — falls back to CAS
    // -------------------------------------------------------------------------

    public function testGetTypedAdapterOrcidFallsBackToCas(): void
    {
        // ORCID authentication was removed; unknown type defaults to CAS
        $adapter = AdapterFactory::getTypedAdapter('ORCID');
        $this->assertInstanceOf(Ccsd_Auth_Adapter_Cas::class, $adapter);
    }

    // -------------------------------------------------------------------------
    // Return type: active adapters implement AdapterInterface
    // -------------------------------------------------------------------------

    public function testActiveAdaptersImplementAdapterInterface(): void
    {
        $types = ['CAS', 'MYSQL'];
        foreach ($types as $type) {
            $adapter = AdapterFactory::getTypedAdapter($type);
            $this->assertInstanceOf(
                \Ccsd\Auth\Adapter\AdapterInterface::class,
                $adapter,
                "Adapter for type '$type' must implement AdapterInterface"
            );
        }
    }
}
