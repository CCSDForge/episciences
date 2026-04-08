<?php

namespace unit\library\Ccsd\Auth\Adapter;

use Ccsd_Auth_Adapter_Orcid;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Auth_Adapter_Orcid
 *
 * Tests pure static logic: adaptCreateValueFromOrcid(), toHtml(), constants.
 * getOrcidWithToken() requires network access (curl to ORCID endpoint)
 * and is not tested here.
 *
 * @covers Ccsd_Auth_Adapter_Orcid
 */
class Ccsd_Auth_Adapter_OrcidTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testGrantTypeConstant(): void
    {
        $this->assertSame('authorization_code', Ccsd_Auth_Adapter_Orcid::GRANT_TYPE);
    }

    public function testAdapterNameConstant(): void
    {
        $this->assertSame('ORCID', Ccsd_Auth_Adapter_Orcid::AdapterName);
    }

    // -------------------------------------------------------------------------
    // adaptCreateValueFromOrcid — field mapping
    // -------------------------------------------------------------------------

    private function makeOrcidAttributes(
        string $orcid = '0000-0001-2345-6789',
        string $name  = 'Jane Doe'
    ): array {
        return [
            'orcid' => $orcid,
            'name'  => $name,
        ];
    }

    public function testAdaptCreateValueFromOrcidMapsOrcidField(): void
    {
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes(orcid: '0000-0002-9999-0001')
        );
        $this->assertSame('0000-0002-9999-0001', $result['ORCID']);
    }

    public function testAdaptCreateValueFromOrcidUsesOrcidAsUsername(): void
    {
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes(orcid: '0000-0001-1111-2222')
        );
        $this->assertSame('0000-0001-1111-2222', $result['USERNAME']);
    }

    public function testAdaptCreateValueFromOrcidMapsNameToLastname(): void
    {
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes(name: 'Pierre Curie')
        );
        $this->assertSame('Pierre Curie', $result['LASTNAME']);
    }

    public function testAdaptCreateValueFromOrcidEmailIsNull(): void
    {
        // ORCID does not provide email — hardcoded null
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes()
        );
        $this->assertNull($result['EMAIL']);
    }

    public function testAdaptCreateValueFromOrcidFirstnameIsNull(): void
    {
        // ORCID only provides full name — FIRSTNAME is hardcoded null
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes()
        );
        $this->assertNull($result['FIRSTNAME']);
    }

    public function testAdaptCreateValueFromOrcidReturnsAllExpectedKeys(): void
    {
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes()
        );
        $this->assertArrayHasKey('ORCID',     $result);
        $this->assertArrayHasKey('USERNAME',  $result);
        $this->assertArrayHasKey('EMAIL',     $result);
        $this->assertArrayHasKey('LASTNAME',  $result);
        $this->assertArrayHasKey('FIRSTNAME', $result);
    }

    public function testAdaptCreateValueFromOrcidOrcidEqualsUsername(): void
    {
        $result = Ccsd_Auth_Adapter_Orcid::adaptCreateValueFromOrcid(
            $this->makeOrcidAttributes(orcid: '0000-0003-9999-7777')
        );
        $this->assertSame($result['ORCID'], $result['USERNAME']);
    }

    // -------------------------------------------------------------------------
    // toHtml
    // -------------------------------------------------------------------------

    public function testToHtmlReturnsAdapterName(): void
    {
        $adapter = new Ccsd_Auth_Adapter_Orcid();
        $this->assertSame('ORCID', $adapter->toHtml([]));
    }

    public function testToHtmlIgnoresAttributeParam(): void
    {
        $adapter = new Ccsd_Auth_Adapter_Orcid();
        // toHtml() ignores $attr entirely, always returns the constant
        $this->assertSame('ORCID', $adapter->toHtml(['orcid' => '0000-0001-0000-0000']));
    }
}
