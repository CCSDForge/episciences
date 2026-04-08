<?php

namespace unit\library\Ccsd\User;

use Ccsd_User_Models_User;
use InvalidArgumentException;
use LengthException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_User_Models_User
 *
 * Tests pure logic: setters/getters, uid/password/valid validation,
 * timestamp defaults, ftp_home default, toArray keys.
 * save() requires DB and is not tested here.
 *
 * @covers Ccsd_User_Models_User
 */
class Ccsd_User_Models_UserTest extends TestCase
{
    private Ccsd_User_Models_User $user;

    protected function setUp(): void
    {
        $this->user = new Ccsd_User_Models_User();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testPasswordHashSizeIs128(): void
    {
        $this->assertSame(128, Ccsd_User_Models_User::PASSWORD_HASH_SIZE);
    }

    public function testValidValuesConstant(): void
    {
        $this->assertSame([0, 1, 3, 4], Ccsd_User_Models_User::VALID_VALUES);
    }

    public function testFtpPathConstant(): void
    {
        $this->assertSame('/ftp/', Ccsd_User_Models_User::CCSD_FTP_PATH);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testDefaultConstructorCreatesEmptyUser(): void
    {
        $this->assertNull($this->user->getUid());
        $this->assertNull($this->user->getUsername());
        $this->assertNull($this->user->getEmail());
    }

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $user = new Ccsd_User_Models_User([
            'uid'      => 42,
            'username' => 'jdoe',
            'email'    => 'jdoe@example.com',
        ]);

        $this->assertSame(42, $user->getUid());
        $this->assertSame('jdoe', $user->getUsername());
        $this->assertSame('jdoe@example.com', $user->getEmail());
    }

    // -------------------------------------------------------------------------
    // setUid / getUid
    // -------------------------------------------------------------------------

    public function testSetAndGetUid(): void
    {
        $this->user->setUid(10);
        $this->assertSame(10, $this->user->getUid());
    }

    public function testSetUidWithEmptyStringSetsNull(): void
    {
        $this->user->setUid('');
        $this->assertNull($this->user->getUid());
    }

    public function testSetUidWithZeroThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->user->setUid(0);
    }

    public function testSetUidWithNegativeValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->user->setUid(-1);
    }

    public function testSetUidReturnsFluent(): void
    {
        $result = $this->user->setUid(5);
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // setPassword / getPassword
    // -------------------------------------------------------------------------

    public function testSetPasswordHashesWith128CharSha512(): void
    {
        $this->user->setPassword('secret');
        $this->assertSame(128, strlen($this->user->getPassword()));
    }

    public function testSetPasswordEmptyStringSetsNull(): void
    {
        $this->user->setPassword('');
        $this->assertNull($this->user->getPassword());
    }

    public function testSetPasswordProducesHexString(): void
    {
        $this->user->setPassword('test123');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $this->user->getPassword());
    }

    public function testSetPasswordIsDeterministic(): void
    {
        $this->user->setPassword('mypassword');
        $hash1 = $this->user->getPassword();

        $other = new Ccsd_User_Models_User();
        $other->setPassword('mypassword');
        $hash2 = $other->getPassword();

        $this->assertSame($hash1, $hash2);
    }

    public function testSetPasswordReturnsFluent(): void
    {
        $result = $this->user->setPassword('pass');
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // setValid / getValid
    // -------------------------------------------------------------------------

    public function testSetValidWithZero(): void
    {
        $this->user->setValid(0);
        $this->assertSame(0, $this->user->getValid());
    }

    public function testSetValidWithOne(): void
    {
        $this->user->setValid(1);
        $this->assertSame(1, $this->user->getValid());
    }

    public function testSetValidWithThree(): void
    {
        $this->user->setValid(3);
        $this->assertSame(3, $this->user->getValid());
    }

    public function testSetValidWithFour(): void
    {
        $this->user->setValid(4);
        $this->assertSame(4, $this->user->getValid());
    }

    public function testSetValidWithInvalidValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->user->setValid(2);
    }

    public function testSetValidWithNegativeValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->user->setValid(-1);
    }

    public function testSetValidReturnsFluent(): void
    {
        $result = $this->user->setValid(1);
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // setTime_registered / getTime_registered
    // -------------------------------------------------------------------------

    public function testSetTimeRegisteredWithExplicitValue(): void
    {
        $this->user->setTime_registered('2024-01-15 10:00:00');
        $this->assertSame('2024-01-15 10:00:00', $this->user->getTime_registered());
    }

    public function testSetTimeRegisteredWithNullUsesCurrentDate(): void
    {
        $before = date('Y-m-d H:i');
        $this->user->setTime_registered(null);
        $after = date('Y-m-d H:i');

        $stored = $this->user->getTime_registered();
        $this->assertNotNull($stored);
        // Check that the stored date starts with the current minute
        $this->assertGreaterThanOrEqual($before, substr($stored, 0, 16));
        $this->assertLessThanOrEqual($after, substr($stored, 0, 16));
    }

    public function testSetTimeRegisteredReturnsFluent(): void
    {
        $result = $this->user->setTime_registered('2024-01-01 00:00:00');
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // setFtp_home / getFtp_home
    // -------------------------------------------------------------------------

    public function testSetFtpHomeWithExplicitValue(): void
    {
        $this->user->setFtp_home('/ftp/custom');
        $this->assertSame('/ftp/custom', $this->user->getFtp_home());
    }

    public function testSetFtpHomeWithNullUsesUidPath(): void
    {
        $this->user->setUid(99);
        $this->user->setFtp_home(null);
        $this->assertSame('/ftp/99', $this->user->getFtp_home());
    }

    public function testSetFtpHomeReturnsFluent(): void
    {
        $result = $this->user->setFtp_home('/ftp/test');
        $this->assertInstanceOf(Ccsd_User_Models_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // setEmail / getEmail
    // -------------------------------------------------------------------------

    public function testSetAndGetEmail(): void
    {
        $this->user->setEmail('user@example.com');
        $this->assertSame('user@example.com', $this->user->getEmail());
    }

    // -------------------------------------------------------------------------
    // setFirstname / getFirstname / setLastname / getLastname
    // -------------------------------------------------------------------------

    public function testSetAndGetFirstname(): void
    {
        $this->user->setFirstname('Alice');
        $this->assertSame('Alice', $this->user->getFirstname());
    }

    public function testSetAndGetLastname(): void
    {
        $this->user->setLastname('Smith');
        $this->assertSame('Smith', $this->user->getLastname());
    }

    // -------------------------------------------------------------------------
    // setUsername / getUsername
    // -------------------------------------------------------------------------

    public function testSetAndGetUsername(): void
    {
        $this->user->setUsername('asmith');
        $this->assertSame('asmith', $this->user->getUsername());
    }

    // -------------------------------------------------------------------------
    // setCiv / getCiv
    // -------------------------------------------------------------------------

    public function testSetAndGetCiv(): void
    {
        $this->user->setCiv('Dr');
        $this->assertSame('Dr', $this->user->getCiv());
    }

    // -------------------------------------------------------------------------
    // setMiddlename / getMiddlename
    // -------------------------------------------------------------------------

    public function testSetAndGetMiddlename(): void
    {
        $this->user->setMiddlename('Jean');
        $this->assertSame('Jean', $this->user->getMiddlename());
    }

    // -------------------------------------------------------------------------
    // setUuid / getUuid
    // -------------------------------------------------------------------------

    public function testSetAndGetUuid(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $this->user->setUuid($uuid);
        $this->assertSame($uuid, $this->user->getUuid());
    }

    public function testDefaultUuidIsNull(): void
    {
        $this->assertNull($this->user->getUuid());
    }

    public function testSetUuidWithNullClearsValue(): void
    {
        $this->user->setUuid('some-uuid');
        $this->user->setUuid(null);
        $this->assertNull($this->user->getUuid());
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    public function testToArrayContainsExpectedKeys(): void
    {
        $result = $this->user->toArray();

        $expectedKeys = [
            'uid', 'username', 'civ', 'lastname', 'firstname',
            'middlename', 'email', 'time_registered', 'time_modified', 'ftp_home',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Key '$key' missing from toArray()");
        }
    }

    public function testToArrayValuesMatchSetters(): void
    {
        $this->user->setUid(7);
        $this->user->setUsername('bob');
        $this->user->setEmail('bob@test.com');
        $this->user->setFirstname('Bob');
        $this->user->setLastname('Doe');
        $this->user->setCiv('Mr');

        $result = $this->user->toArray();

        $this->assertSame(7, $result['uid']);
        $this->assertSame('bob', $result['username']);
        $this->assertSame('bob@test.com', $result['email']);
        $this->assertSame('Bob', $result['firstname']);
        $this->assertSame('Doe', $result['lastname']);
        $this->assertSame('Mr', $result['civ']);
    }
}
