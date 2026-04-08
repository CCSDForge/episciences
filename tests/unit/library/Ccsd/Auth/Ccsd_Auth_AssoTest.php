<?php

namespace unit\library\Ccsd\Auth;

use Ccsd\Auth\Asso;
use Ccsd\Auth\Asso\Orcid as AssoOrcid;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd\Auth\Asso and Ccsd\Auth\Asso\Orcid
 *
 * Tests constructor, getters, and logic bugs.
 * save(), load(), exists() require DB and are not tested here.
 *
 * @covers \Ccsd\Auth\Asso
 * @covers \Ccsd\Auth\Asso\Orcid
 */
class Ccsd_Auth_AssoTest extends TestCase
{
    private Asso $asso;

    protected function setUp(): void
    {
        $this->asso = new Asso(
            'login42',       // uid
            'renater',       // federationName
            'https://idp.example.com', // federationId
            99,              // uidCcsd
            'Doe',           // lastName
            'John',          // firstName
            'john@example.com', // email
            true             // valid
        );
    }

    // -------------------------------------------------------------------------
    // Constructor + getters
    // -------------------------------------------------------------------------

    public function testGetUidReturnsConstructorValue(): void
    {
        $this->assertSame('login42', $this->asso->getUid());
    }

    public function testGetUidCcsdReturnsConstructorValue(): void
    {
        $this->assertSame(99, $this->asso->getUidCcsd());
    }

    public function testGetFederationNameReturnsConstructorValue(): void
    {
        $this->assertSame('renater', $this->asso->getFederationName());
    }

    public function testGetFederationIdReturnsConstructorValue(): void
    {
        $this->assertSame('https://idp.example.com', $this->asso->getFederationId());
    }

    public function testGetLastNameReturnsConstructorValue(): void
    {
        $this->assertSame('Doe', $this->asso->getLastName());
    }

    public function testGetFirstNameReturnsConstructorValue(): void
    {
        $this->assertSame('John', $this->asso->getFirstName());
    }

    public function testGetEmailReturnsConstructorValue(): void
    {
        $this->assertSame('john@example.com', $this->asso->getEmail());
    }

    // -------------------------------------------------------------------------
    // valid() — BUG: always returns true
    // -------------------------------------------------------------------------

    public function testValidAlwaysReturnsTrueRegardlessOfSetValid(): void
    {
        // Valid=true in constructor → valid() returns true (expected)
        $this->assertTrue($this->asso->valid());
    }

    /**
     * @note BUG: valid() always returns `true` (hardcoded), ignoring $this->valid.
     *       Calling setValid(false) stores the value in $this->valid, but
     *       valid() never reads it — it always returns `return true`.
     *       This means it's impossible to mark an Asso as invalid; the guard
     *       in save() can never be triggered by application code.
     */
    public function testValidReturnsTrueEvenAfterSetValidFalse(): void
    {
        $asso = new Asso('uid', 'fed', 'fedId', 1, 'last', 'first', 'a@b.com', false);
        // BUG: should return false after setValid(false), but always returns true
        $this->assertTrue($asso->valid());
    }

    public function testSetValidDoesNotAffectValidReturnValue(): void
    {
        $this->asso->setValid(false);
        // BUG: valid() always returns true regardless of $this->valid
        $this->assertTrue($this->asso->valid());
    }

    // -------------------------------------------------------------------------
    // Asso\Orcid — constructor, constants, field mapping
    // -------------------------------------------------------------------------

    public function testOrcidAssoFedeFederationConstant(): void
    {
        $this->assertSame('Orcid', AssoOrcid::ASSOFEDE);
    }

    public function testOrcidAssoFedeidConstant(): void
    {
        $this->assertSame('Orcid', AssoOrcid::ASSOFEDEID);
    }

    public function testOrcidAssoConstructorSetsOrcidAsUid(): void
    {
        $orcid = new AssoOrcid('0000-0001-2345-6789', 42, 'Jane Doe', 'jane@example.com');
        $this->assertSame('0000-0001-2345-6789', $orcid->getUid());
    }

    public function testOrcidAssoConstructorSetsUidCcsd(): void
    {
        $orcid = new AssoOrcid('0000-0001-0000-0000', 77, 'Bob Smith', 'bob@example.com');
        $this->assertSame(77, $orcid->getUidCcsd());
    }

    public function testOrcidAssoConstructorSetsFederationNameToOrcid(): void
    {
        $orcid = new AssoOrcid('0000-0001-0000-0000', 1, 'Name', '');
        $this->assertSame('Orcid', $orcid->getFederationName());
    }

    public function testOrcidAssoConstructorSetsFederationIdToOrcid(): void
    {
        $orcid = new AssoOrcid('0000-0001-0000-0000', 1, 'Name', '');
        $this->assertSame('Orcid', $orcid->getFederationId());
    }

    public function testOrcidAssoConstructorMapsNameToLastname(): void
    {
        // parent::__construct() called with ($orcid, 'Orcid', 'Orcid', $uidCcsd, $name, '', $email)
        // $name goes to $lastName, firstName is always ''
        $orcid = new AssoOrcid('0000-0002-0000-0000', 1, 'Full Name', 'mail@x.com');
        $this->assertSame('Full Name', $orcid->getLastName());
    }

    public function testOrcidAssoConstructorFirstNameIsAlwaysEmpty(): void
    {
        // ORCID doesn't split first/last name — firstName hardcoded as ''
        $orcid = new AssoOrcid('0000-0003-0000-0000', 1, 'Full Name', 'mail@x.com');
        $this->assertSame('', $orcid->getFirstName());
    }

    public function testOrcidAssoConstructorSetsEmail(): void
    {
        $orcid = new AssoOrcid('0000-0004-0000-0000', 1, 'Name', 'orcid@test.org');
        $this->assertSame('orcid@test.org', $orcid->getEmail());
    }

    public function testOrcidAssoValidAlwaysReturnsTrue(): void
    {
        // Inherits the same BUG as Asso::valid()
        $orcid = new AssoOrcid('0000-0005-0000-0000', 1, 'Name', '', false);
        $this->assertTrue($orcid->valid());
    }
}
