<?php

namespace unit\library\Ccsd\User;

use Ccsd_User_Models_UserTokens;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_User_Models_UserTokens
 *
 * Tests token generation, uid/token/email validation, getTime_modified fallback.
 * DB-related operations (save, find) are not tested here.
 *
 * @covers Ccsd_User_Models_UserTokens
 */
class Ccsd_User_Models_UserTokensTest extends TestCase
{
    private Ccsd_User_Models_UserTokens $tokens;

    protected function setUp(): void
    {
        $this->tokens = new Ccsd_User_Models_UserTokens();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testTokenStringLengthIs40(): void
    {
        $this->assertSame(40, Ccsd_User_Models_UserTokens::TOKEN_STRING_LENGTH);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testDefaultConstructorCreatesEmptyObject(): void
    {
        $this->assertNull($this->tokens->getUid());
        $this->assertNull($this->tokens->getToken());
        $this->assertNull($this->tokens->getEmail());
    }

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $tokens = new Ccsd_User_Models_UserTokens([
            'uid'   => 5,
            'email' => 'test@example.com',
        ]);

        $this->assertSame(5, (int) $tokens->getUid());
        $this->assertSame('test@example.com', $tokens->getEmail());
    }

    // -------------------------------------------------------------------------
    // generateUserToken
    // -------------------------------------------------------------------------

    public function testGenerateUserTokenProduces40CharString(): void
    {
        $this->tokens->generateUserToken();
        $this->assertSame(40, strlen($this->tokens->getToken()));
    }

    public function testGenerateUserTokenProducesHexString(): void
    {
        $this->tokens->generateUserToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $this->tokens->getToken());
    }

    // -------------------------------------------------------------------------
    // setToken / getToken
    // -------------------------------------------------------------------------

    public function testSetValidToken(): void
    {
        $token = sha1('valid_token');
        $this->tokens->setToken($token);
        $this->assertSame($token, $this->tokens->getToken());
    }

    public function testSetTokenWithTooShortValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->setToken('short');
    }

    public function testSetTokenWithTooLongValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->setToken(str_repeat('a', 41));
    }

    public function testSetTokenWithEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->setToken('');
    }

    public function testSetTokenReturnsFluent(): void
    {
        $result = $this->tokens->setToken(sha1('fluent'));
        $this->assertInstanceOf(Ccsd_User_Models_UserTokens::class, $result);
    }

    // -------------------------------------------------------------------------
    // setUid / getUid
    // -------------------------------------------------------------------------

    public function testSetAndGetUidWithValidPositiveInt(): void
    {
        $this->tokens->setUid(42);
        $this->assertSame(42, (int) $this->tokens->getUid());
    }

    public function testSetUidWithEmptyStringSetsNull(): void
    {
        $this->tokens->setUid('');
        $this->assertNull($this->tokens->getUid());
    }

    public function testSetUidWithZeroThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->setUid(0);
    }

    public function testSetUidWithNegativeValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tokens->setUid(-3);
    }

    public function testSetUidReturnsFluent(): void
    {
        $result = $this->tokens->setUid(1);
        $this->assertInstanceOf(Ccsd_User_Models_UserTokens::class, $result);
    }

    // -------------------------------------------------------------------------
    // setEmail / getEmail
    // -------------------------------------------------------------------------

    public function testSetAndGetEmail(): void
    {
        $this->tokens->setEmail('user@domain.com');
        $this->assertSame('user@domain.com', $this->tokens->getEmail());
    }

    public function testSetEmailReturnsFluent(): void
    {
        $result = $this->tokens->setEmail('a@b.com');
        $this->assertInstanceOf(Ccsd_User_Models_UserTokens::class, $result);
    }

    // -------------------------------------------------------------------------
    // setUsage / getUsage
    // -------------------------------------------------------------------------

    public function testSetAndGetUsage(): void
    {
        $this->tokens->setUsage('ACCOUNT_ACTIVATION');
        $this->assertSame('ACCOUNT_ACTIVATION', $this->tokens->getUsage());
    }

    public function testSetUsageReturnsFluent(): void
    {
        $result = $this->tokens->setUsage('ANY_USAGE');
        $this->assertInstanceOf(Ccsd_User_Models_UserTokens::class, $result);
    }

    // -------------------------------------------------------------------------
    // setTime_modified / getTime_modified
    // -------------------------------------------------------------------------

    public function testSetAndGetTimeModified(): void
    {
        $this->tokens->setTime_modified('2024-05-01 12:00:00');
        $this->assertSame('2024-05-01 12:00:00', $this->tokens->getTime_modified());
    }

    public function testGetTimeModifiedReturnsCurrentDateWhenEmpty(): void
    {
        // getTime_modified() returns date('Y-m-d H:i:s') when _time_modified is empty
        $before = date('Y-m-d H:i');
        $result = $this->tokens->getTime_modified();
        $after = date('Y-m-d H:i');

        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
        $this->assertGreaterThanOrEqual($before, substr($result, 0, 16));
        $this->assertLessThanOrEqual($after, substr($result, 0, 16));
    }

    public function testGetTimeModifiedReturnsStoredValueWhenSet(): void
    {
        $this->tokens->setTime_modified('2023-12-31 23:59:59');
        // Must return stored value, not current date
        $this->assertSame('2023-12-31 23:59:59', $this->tokens->getTime_modified());
    }
}
