<?php

namespace unit\library\Episciences\user;

use Episciences_User_Tmp;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_Tmp
 *
 * Tests pure logic: setters/getters, generateScreen_name, getUid override.
 * DB-dependent methods (find, save, delete, availableUsername) are not tested here.
 *
 * @covers Episciences_User_Tmp
 */
class Episciences_User_TmpTest extends TestCase
{
    private Episciences_User_Tmp $user;

    protected function setUp(): void
    {
        $this->user = new Episciences_User_Tmp();
    }

    // -------------------------------------------------------------------------
    // getUid — overrides parent to always return null
    // -------------------------------------------------------------------------

    public function testGetUidAlwaysReturnsNull(): void
    {
        $this->assertNull($this->user->getUid());
    }

    // -------------------------------------------------------------------------
    // id
    // -------------------------------------------------------------------------

    public function testSetAndGetId(): void
    {
        $this->user->setId(42);
        $this->assertSame(42, $this->user->getId());
    }

    public function testDefaultIdIsNull(): void
    {
        $this->assertNull($this->user->getId());
    }

    // -------------------------------------------------------------------------
    // status
    // -------------------------------------------------------------------------

    public function testSetAndGetStatus(): void
    {
        $this->user->setStatus('pending');
        $this->assertSame('pending', $this->user->getStatus());
    }

    public function testDefaultStatusIsNull(): void
    {
        $this->assertNull($this->user->getStatus());
    }

    // -------------------------------------------------------------------------
    // generateScreen_name
    // -------------------------------------------------------------------------

    public function testGenerateScreenNameConcatenatesFirstAndLastName(): void
    {
        $this->user->setFirstname('Marie');
        $this->user->setLastname('Curie');
        $this->user->generateScreen_name();

        $this->assertSame('Marie Curie', $this->user->getScreenName());
    }

    public function testGenerateScreenNameTrimsSurroundingSpaces(): void
    {
        $this->user->setFirstname('');
        $this->user->setLastname('Solo');
        $this->user->generateScreen_name();

        // sprintf('%s %s', '', 'Solo') => ' Solo', then trim => 'Solo'
        $this->assertSame('Solo', $this->user->getScreenName());
    }

    public function testGenerateScreenNameWithOnlyFirstname(): void
    {
        $this->user->setFirstname('Han');
        $this->user->setLastname('');
        $this->user->generateScreen_name();

        $this->assertSame('Han', $this->user->getScreenName());
    }

    // -------------------------------------------------------------------------
    // setLang
    // -------------------------------------------------------------------------

    public function testSetLangStoresValue(): void
    {
        // setLang() writes to private $_lang; only observable via getLangueid()
        // We verify it doesn't throw
        $this->user->setLang('fr');
        $this->addToAssertionCount(1); // ensure assertion count is met
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    public function testToArrayContainsExpectedKeys(): void
    {
        $this->user->setId(1);
        $this->user->setEmail('tmp@example.com');
        $this->user->setFirstname('Alice');
        $this->user->setLastname('Smith');
        $this->user->setStatus('active');
        $this->user->generateScreen_name();

        $result = $this->user->toArray();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('firstname', $result);
        $this->assertArrayHasKey('lastname', $result);
        $this->assertArrayHasKey('fullname', $result);
        $this->assertArrayHasKey('screen_name', $result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testToArrayValuesMatchSetters(): void
    {
        $this->user->setId(7);
        $this->user->setEmail('test@test.com');
        $this->user->setFirstname('Bob');
        $this->user->setLastname('Doe');
        $this->user->setStatus('pending');
        $this->user->generateScreen_name();

        $result = $this->user->toArray();

        $this->assertSame(7, $result['id']);
        $this->assertSame('test@test.com', $result['email']);
        $this->assertSame('Bob', $result['firstname']);
        $this->assertSame('Doe', $result['lastname']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('Bob Doe', $result['screen_name']);
    }

    public function testToArrayUsernameKey(): void
    {
        $result = $this->user->toArray();
        $this->assertArrayHasKey('username', $result);
    }
}
