<?php

namespace unit\library\Ccsd\Auth\Adapter;

use Ccsd\Auth\Adapter\Idp;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd\Auth\Adapter\Idp
 *
 * Tests pure static logic: adaptCreateValueFromIdp(), getIdpLogin(), filterEmail().
 * Authentication flows require SimpleSAML and are not tested here.
 *
 * @covers \Ccsd\Auth\Adapter\Idp
 */
class Ccsd_Auth_Adapter_IdpTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testAdapterNameConstant(): void
    {
        $this->assertSame('IDP', Idp::AdapterName);
    }

    // -------------------------------------------------------------------------
    // adaptCreateValueFromIdp — field mapping
    // -------------------------------------------------------------------------

    private function makeAttributes(
        string $mail       = 'user@example.com',
        string $displayName = 'Alice Smith',
        string $sn          = 'Smith',
        string $givenName   = 'Alice',
        string $uid         = 'asmith'
    ): array {
        return [
            'mail'        => [$mail],
            'displayName' => [$displayName],
            'sn'          => [$sn],
            'givenName'   => [$givenName],
            'uid'         => [$uid],
        ];
    }

    public function testAdaptCreateValueFromIdpMapsUsernameFromMail(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes());
        $this->assertSame('user@example.com', $result['USERNAME']);
    }

    public function testAdaptCreateValueFromIdpMapsEmailFromMail(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes());
        $this->assertSame('user@example.com', $result['EMAIL']);
    }

    public function testAdaptCreateValueFromIdpMapsFullnameFromDisplayName(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes(displayName: 'Marie Curie'));
        $this->assertSame('Marie Curie', $result['FULLNAME']);
    }

    public function testAdaptCreateValueFromIdpMapsLastnameFromSn(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes(sn: 'Curie'));
        $this->assertSame('Curie', $result['LASTNAME']);
    }

    public function testAdaptCreateValueFromIdpMapsFirstnameFromGivenName(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes(givenName: 'Marie'));
        $this->assertSame('Marie', $result['FIRSTNAME']);
    }

    public function testAdaptCreateValueFromIdpReturnsAllExpectedKeys(): void
    {
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes());
        $this->assertArrayHasKey('USERNAME', $result);
        $this->assertArrayHasKey('FULLNAME', $result);
        $this->assertArrayHasKey('EMAIL', $result);
        $this->assertArrayHasKey('LASTNAME', $result);
        $this->assertArrayHasKey('FIRSTNAME', $result);
    }

    public function testAdaptCreateValueFromIdpUsernameEqualsEmail(): void
    {
        // USERNAME and EMAIL both come from mail[0]
        $result = Idp::adaptCreateValueFromIdp($this->makeAttributes(mail: 'contact@inrae.fr'));
        $this->assertSame($result['EMAIL'], $result['USERNAME']);
    }

    // -------------------------------------------------------------------------
    // getIdpLogin
    // -------------------------------------------------------------------------

    public function testGetIdpLoginReturnsUidFirstElement(): void
    {
        $result = Idp::getIdpLogin($this->makeAttributes(uid: 'jdoe42'));
        $this->assertSame('jdoe42', $result);
    }

    public function testGetIdpLoginReturnsStringValue(): void
    {
        $result = Idp::getIdpLogin($this->makeAttributes(uid: '0001'));
        $this->assertSame('0001', $result);
    }

    // -------------------------------------------------------------------------
    // filterEmail — valid domains
    // -------------------------------------------------------------------------

    public function testFilterEmailAcceptsInraFr(): void
    {
        $idp = new Idp();
        $this->assertTrue($idp->filterEmail('user@inra.fr'));
    }

    public function testFilterEmailAcceptsIrsteaFr(): void
    {
        $idp = new Idp();
        $this->assertTrue($idp->filterEmail('agent@irstea.fr'));
    }

    public function testFilterEmailAcceptsInraeFr(): void
    {
        $idp = new Idp();
        $this->assertTrue($idp->filterEmail('researcher@inrae.fr'));
    }

    public function testFilterEmailRejectsGmailCom(): void
    {
        $idp = new Idp();
        $this->assertFalse($idp->filterEmail('user@gmail.com'));
    }

    public function testFilterEmailRejectsUnrelatedDomain(): void
    {
        $idp = new Idp();
        $this->assertFalse($idp->filterEmail('user@example.com'));
    }

    public function testFilterEmailRejectsEmptyString(): void
    {
        $idp = new Idp();
        $this->assertFalse($idp->filterEmail(''));
    }

    // -------------------------------------------------------------------------
    // filterEmail — dot escaped, only literal domain separator matches
    // -------------------------------------------------------------------------

    public function testFilterEmailRejectsInraWithNonDotSeparator(): void
    {
        $idp = new Idp();
        // '@inraXfr' must not match: the dot is now escaped with preg_quote()
        $this->assertFalse($idp->filterEmail('user@inraXfr'));
    }

    // -------------------------------------------------------------------------
    // filterEmail — trailing $ anchor prevents subdomain injection
    // -------------------------------------------------------------------------

    public function testFilterEmailRejectsDomainWithInraSuffix(): void
    {
        $idp = new Idp();
        // '@inra.fr.evil.com' must not match: the '$' anchor enforces end-of-string
        $this->assertFalse($idp->filterEmail('attacker@inra.fr.evil.com'));
    }

    public function testFilterEmailRejectsSubdomainSpoofing(): void
    {
        $idp = new Idp();
        // A domain that merely contains '@inra' must not pass
        $this->assertFalse($idp->filterEmail('user@notinra.fr'));
    }
}
