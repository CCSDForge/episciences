<?php

namespace unit\library\Episciences\User;

use Ccsd_User_Models_User;
use Episciences\User\EmailPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\EmailPolicy
 */
class EmailPolicyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // normalize()
    // -------------------------------------------------------------------------

    public function testNormalizeTrimsLeadingAndTrailingSpaces(): void
    {
        self::assertSame('a@b.org', EmailPolicy::normalize(' a@b.org '));
    }

    /**
     * Regression guard for D2: proves that the value the form validated could
     * diverge from the value actually written, unless both go through the
     * same normalization.
     */
    public function testNormalizeStripsWhatFilterSanitizeEmailRemoves(): void
    {
        self::assertSame('ab@c.org', EmailPolicy::normalize('a b@c.org'));
        self::assertSame('xy@z.org', EmailPolicy::normalize('x<y>@z.org'));
    }

    /**
     * @return list<array{string}>
     */
    public static function normalizeIdempotentProvider(): array
    {
        return [
            ['a@b.org'],
            [' A@B.ORG '],
            ['x<y>@z.org'],
            [''],
        ];
    }

    /**
     * @dataProvider normalizeIdempotentProvider
     */
    public function testNormalizeIsIdempotent(string $raw): void
    {
        $once = EmailPolicy::normalize($raw);
        $twice = EmailPolicy::normalize($once);

        self::assertSame($once, $twice);
    }

    /**
     * Guards the collation decision: T_UTILISATEURS defaults to
     * utf8mb3_general_ci, already case-insensitive at the SQL level, so
     * normalize() must not fold case itself.
     */
    public function testNormalizeDoesNotChangeCase(): void
    {
        self::assertSame('John@Example.ORG', EmailPolicy::normalize('John@Example.ORG'));
    }

    public function testNormalizeMatchesSetEmailGetEmail(): void
    {
        $raw = ' John<>Doe@Example.ORG ';

        $user = new Ccsd_User_Models_User();
        $user->setEmail($raw);

        self::assertSame($user->getEmail(), EmailPolicy::normalize($raw));
    }

    // -------------------------------------------------------------------------
    // isSameAddress()
    // -------------------------------------------------------------------------

    public function testIsSameAddressTrueForCaseAndTrailingSpaceDifferences(): void
    {
        self::assertTrue(EmailPolicy::isSameAddress('a@b.org', 'A@B.ORG '));
    }

    public function testIsSameAddressFalseForDifferentLocalPart(): void
    {
        self::assertFalse(EmailPolicy::isSameAddress('a.b@x.org', 'ab@x.org'));
    }

    public function testIsSameAddressFalseForPlusTag(): void
    {
        // Deliberately no Gmail-style +tag folding.
        self::assertFalse(EmailPolicy::isSameAddress('a+1@x.org', 'a@x.org'));
    }

    // -------------------------------------------------------------------------
    // findConflictingUid()
    // -------------------------------------------------------------------------

    public function testFindConflictingUidReturnsNullWhenFinderReturnsNothing(): void
    {
        $result = EmailPolicy::findConflictingUid('a@b.org', 42, static fn(string $email): array => []);

        self::assertNull($result);
    }

    public function testFindConflictingUidReturnsNullWhenOnlyExcludedUidMatches(): void
    {
        $result = EmailPolicy::findConflictingUid('a@b.org', 42, static fn(string $email): array => [42]);

        self::assertNull($result);
    }

    public function testFindConflictingUidReturnsOtherUidWhenPresent(): void
    {
        $result = EmailPolicy::findConflictingUid('a@b.org', 42, static fn(string $email): array => [42, 77]);

        self::assertSame(77, $result);
    }

    public function testFindConflictingUidWithExcludeUidZeroTreatsAnyMatchAsConflict(): void
    {
        $result = EmailPolicy::findConflictingUid('a@b.org', 0, static fn(string $email): array => [77]);

        self::assertSame(77, $result);
    }

    public function testFindConflictingUidPassesNormalizedEmailToFinder(): void
    {
        $captured = null;

        EmailPolicy::findConflictingUid(' A<>@B.ORG ', 0, function (string $email) use (&$captured): array {
            $captured = $email;
            return [];
        });

        self::assertSame(EmailPolicy::normalize(' A<>@B.ORG '), $captured);
    }
}
