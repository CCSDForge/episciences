<?php

namespace unit\library\Episciences\Reviewer;

use Episciences_Reviewer_AccountResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure helpers of Episciences_Reviewer_AccountResolver:
 * login base generation, available-login resolution and strong password
 * generation. The DB-backed helpers (CAS lookup, account creation) are not
 * covered here as they require the auth database.
 */
class Episciences_Reviewer_AccountResolverTest extends TestCase
{
    public function testGenerateLoginBaseSimple(): void
    {
        self::assertSame(
            'fzanasi',
            Episciences_Reviewer_AccountResolver::generateLoginBase('Fabio', 'Zanasi', 'fabio@example.org')
        );
    }

    public function testGenerateLoginBaseTransliteratesAccents(): void
    {
        self::assertSame(
            'gvanberg',
            Episciences_Reviewer_AccountResolver::generateLoginBase('Géraldine', 'Van Berg', 'g@example.org')
        );
    }

    public function testGenerateLoginBaseStripsNonAlphanumeric(): void
    {
        self::assertSame(
            'jorourke',
            Episciences_Reviewer_AccountResolver::generateLoginBase("John", "O'Rourke", 'j@example.org')
        );
    }

    public function testGenerateLoginBaseFallsBackToEmailLocalPartWhenFirstnameEmpty(): void
    {
        self::assertSame(
            'fabiozanasi',
            Episciences_Reviewer_AccountResolver::generateLoginBase('', 'Zanasi', 'Fabio.Zanasi@example.org')
        );
    }

    public function testGenerateLoginBaseFallsBackToEmailLocalPartWhenLastnameEmpty(): void
    {
        self::assertSame(
            'jdoe',
            Episciences_Reviewer_AccountResolver::generateLoginBase('John', '', 'jdoe@example.org')
        );
    }

    public function testGenerateLoginBaseDefaultsWhenEverythingEmpty(): void
    {
        self::assertSame(
            Episciences_Reviewer_AccountResolver::DEFAULT_LOGIN_BASE,
            Episciences_Reviewer_AccountResolver::generateLoginBase('', '', '')
        );
    }

    public function testGenerateLoginBaseDefaultsWhenOnlyNonAlphaCharacters(): void
    {
        // No usable name and the email local part normalizes to empty.
        self::assertSame(
            Episciences_Reviewer_AccountResolver::DEFAULT_LOGIN_BASE,
            Episciences_Reviewer_AccountResolver::generateLoginBase('***', '', '@example.org')
        );
    }

    public function testResolveAvailableLoginReturnsBaseWhenFree(): void
    {
        $login = Episciences_Reviewer_AccountResolver::resolveAvailableLogin(
            'fzanasi',
            static fn(string $candidate): bool => false
        );

        self::assertSame('fzanasi', $login);
    }

    public function testResolveAvailableLoginIncrementsUntilFree(): void
    {
        $taken = ['fzanasi', 'fzanasi1', 'fzanasi2'];

        $login = Episciences_Reviewer_AccountResolver::resolveAvailableLogin(
            'fzanasi',
            static fn(string $candidate): bool => in_array($candidate, $taken, true)
        );

        self::assertSame('fzanasi3', $login);
    }

    public function testResolveAvailableLoginUsesDefaultBaseWhenEmpty(): void
    {
        $login = Episciences_Reviewer_AccountResolver::resolveAvailableLogin(
            '',
            static fn(string $candidate): bool => false
        );

        self::assertSame(Episciences_Reviewer_AccountResolver::DEFAULT_LOGIN_BASE, $login);
    }

    public function testGenerateStrongPasswordHasRequestedLength(): void
    {
        self::assertSame(40, strlen(Episciences_Reviewer_AccountResolver::generateStrongPassword(40)));
    }

    public function testGenerateStrongPasswordEnforcesMinimumLength(): void
    {
        // Anything below 16 is bumped up to 16.
        self::assertSame(16, strlen(Episciences_Reviewer_AccountResolver::generateStrongPassword(4)));
    }

    public function testGenerateStrongPasswordContainsEveryCharacterClass(): void
    {
        $password = Episciences_Reviewer_AccountResolver::generateStrongPassword(32);

        self::assertMatchesRegularExpression('/[a-z]/', $password, 'missing lowercase');
        self::assertMatchesRegularExpression('/[A-Z]/', $password, 'missing uppercase');
        self::assertMatchesRegularExpression('/[0-9]/', $password, 'missing digit');
        self::assertMatchesRegularExpression('/[^a-zA-Z0-9]/', $password, 'missing symbol');
    }

    public function testGenerateStrongPasswordIsRandom(): void
    {
        $first = Episciences_Reviewer_AccountResolver::generateStrongPassword(32);
        $second = Episciences_Reviewer_AccountResolver::generateStrongPassword(32);

        self::assertNotSame($first, $second);
    }
}
