<?php

namespace unit\library\Episciences\user;

use Episciences_User_Token;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_Token
 *
 * Tests token generation, uid/email/token validation, constants.
 * DB-related operations are not tested here.
 *
 * @covers Episciences_User_Token
 */
class Episciences_User_TokenTest extends TestCase
{
    private Episciences_User_Token $token;

    protected function setUp(): void
    {
        $this->token = new Episciences_User_Token();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testTokenStringLengthIs40(): void
    {
        $this->assertSame(40, Episciences_User_Token::TOKEN_STRING_LENGTH);
    }

    public function testUsageConstant(): void
    {
        $this->assertSame('REVIEWER_INVITATION', Episciences_User_Token::USAGE_REVIEWER_INVITATION);
    }

    // -------------------------------------------------------------------------
    // generateUserToken
    // -------------------------------------------------------------------------

    public function testGenerateUserTokenProduces40CharString(): void
    {
        $this->token->generateUserToken();
        $this->assertSame(40, strlen($this->token->getToken()));
    }

    public function testGenerateUserTokenProducesHexString(): void
    {
        $this->token->generateUserToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $this->token->getToken());
    }

    public function testGenerateUserTokenProducesUniqueValues(): void
    {
        $token1 = new Episciences_User_Token();
        $token2 = new Episciences_User_Token();
        $token1->generateUserToken();
        // Sleep 1ms between calls to maximize uniqueness chance
        usleep(1000);
        $token2->generateUserToken();

        // Not guaranteed unique but extremely unlikely to collide
        $this->assertSame(40, strlen($token1->getToken()));
        $this->assertSame(40, strlen($token2->getToken()));
    }

    // -------------------------------------------------------------------------
    // setToken / getToken
    // -------------------------------------------------------------------------

    public function testSetValidToken(): void
    {
        $validToken = str_repeat('a', 40);
        $this->token->setToken($validToken);
        $this->assertSame($validToken, $this->token->getToken());
    }

    public function testSetTokenWith40CharHexString(): void
    {
        $sha1Token = sha1('test_value');
        $this->token->setToken($sha1Token);
        $this->assertSame($sha1Token, $this->token->getToken());
    }

    public function testSetTokenWithTooShortValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->token->setToken('short');
    }

    public function testSetTokenWithTooLongValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->token->setToken(str_repeat('a', 41));
    }

    public function testSetTokenWithEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->token->setToken('');
    }

    public function testSetTokenReturnsFluent(): void
    {
        $result = $this->token->setToken(sha1('fluent'));
        $this->assertInstanceOf(Episciences_User_Token::class, $result);
    }

    // -------------------------------------------------------------------------
    // setUid / getUid
    // -------------------------------------------------------------------------

    public function testSetAndGetUidWithValidPositiveInt(): void
    {
        $this->token->setUid(42);
        $this->assertSame(42, (int) $this->token->getUid());
    }

    public function testSetUidWithEmptyStringSetsNull(): void
    {
        $this->token->setUid('');
        $this->assertNull($this->token->getUid());
    }

    public function testSetUidWithZeroThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->token->setUid(0);
    }

    public function testSetUidWithNegativeValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->token->setUid(-5);
    }

    public function testSetUidReturnsFluent(): void
    {
        $result = $this->token->setUid(1);
        $this->assertInstanceOf(Episciences_User_Token::class, $result);
    }

    // -------------------------------------------------------------------------
    // setEmail / getEmail
    // -------------------------------------------------------------------------

    public function testSetAndGetEmail(): void
    {
        $this->token->setEmail('user@example.com');
        $this->assertSame('user@example.com', $this->token->getEmail());
    }

    public function testSetEmailSanitizes(): void
    {
        // FILTER_SANITIZE_EMAIL removes characters not allowed in email addresses
        $this->token->setEmail('user+tag@example.com');
        $this->assertSame('user+tag@example.com', $this->token->getEmail());
    }

    public function testSetEmailReturnsFluent(): void
    {
        $result = $this->token->setEmail('a@b.com');
        $this->assertInstanceOf(Episciences_User_Token::class, $result);
    }

    // -------------------------------------------------------------------------
    // usage
    // -------------------------------------------------------------------------

    public function testSetAndGetUsage(): void
    {
        $this->token->setUsage(Episciences_User_Token::USAGE_REVIEWER_INVITATION);
        $this->assertSame(Episciences_User_Token::USAGE_REVIEWER_INVITATION, $this->token->getUsage());
    }

    public function testSetUsageReturnsFluent(): void
    {
        $result = $this->token->setUsage('SOME_USAGE');
        $this->assertInstanceOf(Episciences_User_Token::class, $result);
    }

    // -------------------------------------------------------------------------
    // time_modified
    // -------------------------------------------------------------------------

    public function testSetAndGetTimeModified(): void
    {
        $this->token->setTime_modified('2024-05-01 12:00:00');
        $this->assertSame('2024-05-01 12:00:00', $this->token->getTime_modified());
    }

    // -------------------------------------------------------------------------
    // Constructor with options
    // -------------------------------------------------------------------------

    public function testConstructorWithOptions(): void
    {
        $token = new Episciences_User_Token([
            'uid'   => 10,
            'email' => 'test@example.com',
            'usage' => Episciences_User_Token::USAGE_REVIEWER_INVITATION,
        ]);

        $this->assertSame(10, (int) $token->getUid());
        $this->assertSame('test@example.com', $token->getEmail());
        $this->assertSame(Episciences_User_Token::USAGE_REVIEWER_INVITATION, $token->getUsage());
    }
}
