<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\InvitationToken;
use Episciences_User_Invitation;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Reviewer\InvitationToken
 */
final class InvitationTokenTest extends TestCase
{
    private const SECRET = 'test-reviewer-invitation-secret';
    private const ROW_SECRET = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private InvitationToken $token;

    protected function setUp(): void
    {
        $this->token = new InvitationToken(self::SECRET);
    }

    public function testRowSecretIsSixtyFourHexadecimalCharacters(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', InvitationToken::generateRowSecret());
    }

    public function testRowSecretIsRandom(): void
    {
        self::assertNotSame(InvitationToken::generateRowSecret(), InvitationToken::generateRowSecret());
    }

    public function testComputeIsDeterministic(): void
    {
        self::assertSame($this->token->compute(7, self::ROW_SECRET), $this->token->compute(7, self::ROW_SECRET));
    }

    public function testComputeIsTheHmacOfTheIdAndTheRowSecret(): void
    {
        self::assertSame(
            hash_hmac('sha256', '7|' . self::ROW_SECRET, self::SECRET),
            $this->token->compute(7, self::ROW_SECRET)
        );
    }

    public function testTokenDependsOnTheInvitationId(): void
    {
        self::assertNotSame($this->token->compute(7, self::ROW_SECRET), $this->token->compute(8, self::ROW_SECRET));
    }

    public function testTokenDependsOnTheRowSecret(): void
    {
        self::assertNotSame(
            $this->token->compute(7, self::ROW_SECRET),
            $this->token->compute(7, strrev(self::ROW_SECRET))
        );
    }

    public function testTokenDependsOnTheGlobalSecret(): void
    {
        self::assertNotSame(
            $this->token->compute(7, self::ROW_SECRET),
            (new InvitationToken('another-secret'))->compute(7, self::ROW_SECRET)
        );
    }

    public function testValidTokenIsAccepted(): void
    {
        self::assertTrue($this->token->isValid(7, self::ROW_SECRET, $this->token->compute(7, self::ROW_SECRET)));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTokenProvider(): array
    {
        $valid = hash_hmac('sha256', '7|' . self::ROW_SECRET, self::SECRET);

        return [
            'wrong' => [str_repeat('0', 64)],
            'one character changed' => [substr($valid, 0, -1) . ($valid[63] === 'a' ? 'b' : 'a')],
            'truncated' => [substr($valid, 0, 32)],
            'upper case' => [strtoupper($valid)],
            'token of another invitation' => [hash_hmac('sha256', '8|' . self::ROW_SECRET, self::SECRET)],
            'row secret itself' => [self::ROW_SECRET],
            'empty' => [''],
            'null' => [null],
            'array' => [[$valid]],
        ];
    }

    /**
     * @dataProvider invalidTokenProvider
     */
    public function testInvalidTokenIsRefused(mixed $presented): void
    {
        self::assertFalse($this->token->isValid(7, self::ROW_SECRET, $presented));
    }

    public function testIsConfiguredOnlyWithANonEmptySecret(): void
    {
        self::assertTrue($this->token->isConfigured());
        self::assertFalse((new InvitationToken(''))->isConfigured());
    }

    public function testComputeFailsClosedWithoutSecret(): void
    {
        $this->expectException(LogicException::class);
        (new InvitationToken(''))->compute(7, self::ROW_SECRET);
    }

    public function testIsValidFailsClosedWithoutSecret(): void
    {
        $this->expectException(LogicException::class);
        (new InvitationToken(''))->isValid(7, self::ROW_SECRET, '');
    }

    public function testLegacyInvitationHasNoLinkToken(): void
    {
        $invitation = new Episciences_User_Invitation(['id' => 7]);

        self::assertNull($this->token->forInvitation($invitation));
    }

    public function testInvitationWithRowSecretGetsItsLinkToken(): void
    {
        $invitation = new Episciences_User_Invitation(['id' => 7, 'token' => self::ROW_SECRET]);

        self::assertSame($this->token->compute(7, self::ROW_SECRET), $this->token->forInvitation($invitation));
    }
}
