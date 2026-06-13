<?php

namespace unit\library\Ccsd\Auth;

use Ccsd\Auth\Asso;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd\Auth\Asso
 *
 * Tests constructor, getters, setters, and valid() logic.
 * save(), load(), exists() require DB and are not tested here.
 *
 * @covers \Ccsd\Auth\Asso
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
    // valid()
    // -------------------------------------------------------------------------

    public function testValidReturnsTrueWhenConstructedWithTrue(): void
    {
        $this->assertTrue($this->asso->valid());
    }

    public function testValidReturnsFalseWhenConstructedWithFalse(): void
    {
        $asso = new Asso('uid', 'fed', 'fedId', 1, 'last', 'first', 'a@b.com', false);
        $this->assertFalse($asso->valid());
    }

    public function testSetValidFalseIsReflectedByValid(): void
    {
        $this->asso->setValid(false);
        $this->assertFalse($this->asso->valid());
    }

    public function testSetValidTrueIsReflectedByValid(): void
    {
        $asso = new Asso('uid', 'fed', 'fedId', 1, 'last', 'first', 'a@b.com', false);
        $asso->setValid(true);
        $this->assertTrue($asso->valid());
    }
}
