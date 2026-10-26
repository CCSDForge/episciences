<?php

declare(strict_types=1);

namespace unit\library\Episciences\Files;

use Episciences\Files\UploadedFileToken;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Files\UploadedFileToken
 */
final class UploadedFileTokenTest extends TestCase
{
    private const SECRET = 'uploaded-file-test-secret';
    private const UID = 4242;
    private const NOW = 1_800_000_000;
    private const FOLDER = '/data/journal/files/12/tmp/';

    private function token(int $uid = self::UID, string $secret = self::SECRET, int $now = self::NOW): UploadedFileToken
    {
        return new UploadedFileToken($uid, $secret, $now);
    }

    public function testTokenOfTheUploadedFileIsValid(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertTrue($this->token()->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testFolderWithOrWithoutTrailingSeparatorIsTheSame(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertTrue($this->token()->isValid(rtrim(self::FOLDER, '/'), 'answer.pdf', $token));
    }

    public function testTokenDoesNotGrantAnotherFileOfTheFolder(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertFalse($this->token()->isValid(self::FOLDER, 'request.pdf', $token));
    }

    public function testTokenDoesNotGrantTheSameNameInAnotherFolder(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertFalse($this->token()->isValid('/data/journal/files/13/tmp/', 'answer.pdf', $token));
    }

    public function testTokenOfAnotherUserIsRefused(): void
    {
        $token = $this->token(self::UID + 1)->sign(self::FOLDER, 'answer.pdf');

        self::assertFalse($this->token()->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testTokenSignedWithAnotherSecretIsRefused(): void
    {
        $token = $this->token(self::UID, 'another-secret')->sign(self::FOLDER, 'answer.pdf');

        self::assertFalse($this->token()->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testTokenIsValidUntilItExpires(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertTrue($this->token(now: self::NOW + UploadedFileToken::TTL)->isValid(self::FOLDER, 'answer.pdf', $token));
        self::assertFalse($this->token(now: self::NOW + UploadedFileToken::TTL + 1)->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testExtendedExpiryInvalidatesTheToken(): void
    {
        [, $signature] = explode('.', $this->token()->sign(self::FOLDER, 'answer.pdf'));
        $forged = (self::NOW + 10 * UploadedFileToken::TTL) . '.' . $signature;

        self::assertFalse($this->token()->isValid(self::FOLDER, 'answer.pdf', $forged));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'not a string' => [['1800000000.abc']];
        yield 'no expiry' => [str_repeat('a', 64)];
        yield 'short signature' => [self::NOW . '.abc'];
        yield 'upper case signature' => [self::NOW . '.' . str_repeat('A', 64)];
    }

    /**
     * @dataProvider malformedTokens
     */
    public function testMalformedTokenIsRefused(mixed $token): void
    {
        self::assertFalse($this->token()->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testEmptyFileNameIsRefused(): void
    {
        $token = $this->token()->sign(self::FOLDER, '');

        self::assertFalse($this->token()->isValid(self::FOLDER, '', $token));
    }

    public function testWithoutSecretSigningFails(): void
    {
        $token = $this->token(self::UID, '');

        self::assertFalse($token->isConfigured());
        $this->expectException(LogicException::class);
        $token->sign(self::FOLDER, 'answer.pdf');
    }

    public function testWithoutSecretNoTokenIsValid(): void
    {
        $token = $this->token()->sign(self::FOLDER, 'answer.pdf');

        self::assertFalse($this->token(self::UID, '')->isValid(self::FOLDER, 'answer.pdf', $token));
    }

    public function testOnlyTheNamesWithAValidTokenAreAccepted(): void
    {
        $signer = $this->token();
        $names = ['answer.pdf', 'request.pdf', '', 'figure.png', ['nested']];
        $tokens = [
            $signer->sign(self::FOLDER, 'answer.pdf'),
            // a token of another file
            $signer->sign(self::FOLDER, 'answer.pdf'),
            '',
            $signer->sign(self::FOLDER, 'figure.png'),
            $signer->sign(self::FOLDER, 'nested'),
        ];

        self::assertSame([0 => 'answer.pdf', 3 => 'figure.png'], $signer->acceptedNames(self::FOLDER, $names, $tokens));
    }

    public function testNamesWithoutTokensAreRefused(): void
    {
        self::assertSame([], $this->token()->acceptedNames(self::FOLDER, ['answer.pdf'], null));
        self::assertSame([], $this->token()->acceptedNames(self::FOLDER, ['answer.pdf'], 'not-a-list'));
    }
}
