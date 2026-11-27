<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Token;

use Episciences\User\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;

/**
 * Token lifetimes: configurable in pwd.json (USER_TOKEN section), with a default for a missing or invalid value.
 * The values are passed to resolveTtlSeconds(): the constants may already be defined by the test configuration.
 *
 * @covers \Episciences\User\Token\TokenPurpose
 */
final class TokenPurposeTest extends TestCase
{
    public function testDefaultLifetimes(): void
    {
        self::assertSame(3600, TokenPurpose::PASSWORD_RESET->defaultTtlSeconds());
        self::assertSame(7 * 86400, TokenPurpose::ACCOUNT_ACTIVATION->defaultTtlSeconds());
        self::assertSame(14 * 86400, TokenPurpose::ACCOUNT_SETUP->defaultTtlSeconds());
    }

    public function testConstantNamesMatchThePwdJsonSection(): void
    {
        self::assertSame('USER_TOKEN_PASSWORD_RESET_TTL', TokenPurpose::PASSWORD_RESET->ttlConstantName());
        self::assertSame('USER_TOKEN_ACCOUNT_ACTIVATION_TTL', TokenPurpose::ACCOUNT_ACTIVATION->ttlConstantName());
        self::assertSame('USER_TOKEN_ACCOUNT_SETUP_TTL', TokenPurpose::ACCOUNT_SETUP->ttlConstantName());
    }

    /**
     * @dataProvider unconfiguredLifetimes
     */
    public function testUnconfiguredLifetimeIsTheDefault(mixed $configured): void
    {
        self::assertSame(TokenPurpose::ACCOUNT_SETUP->defaultTtlSeconds(), TokenPurpose::ACCOUNT_SETUP->resolveTtlSeconds($configured));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unconfiguredLifetimes(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
    }

    public function testConfiguredLifetimeIsUsed(): void
    {
        self::assertSame(900, TokenPurpose::PASSWORD_RESET->resolveTtlSeconds(900));
        self::assertSame(86400, TokenPurpose::ACCOUNT_ACTIVATION->resolveTtlSeconds('86400')); // JSON string value
    }

    public function testLifetimeIsReadFromTheConfigurationConstant(): void
    {
        foreach (TokenPurpose::cases() as $purpose) {
            $constantName = $purpose->ttlConstantName();
            $expected = $purpose->resolveTtlSeconds(defined($constantName) ? constant($constantName) : null);

            self::assertSame($expected, $purpose->ttlSeconds(), $purpose->value);
        }
    }

    /**
     * @dataProvider invalidLifetimes
     */
    public function testInvalidLifetimeFallsBackToTheDefaultWithAWarning(mixed $configured): void
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $ttl = TokenPurpose::PASSWORD_RESET->resolveTtlSeconds($configured);
        } finally {
            restore_error_handler();
        }

        self::assertSame(3600, $ttl);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('USER_TOKEN_PASSWORD_RESET_TTL', $warnings[0]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidLifetimes(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-60];
        yield 'not a number' => ['1h'];
        yield 'decimal' => [1.5];
    }
}
