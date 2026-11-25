<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\ImpersonationPolicy;
use Episciences_Acl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionProperty;

/**
 * @covers \Episciences\User\ImpersonationPolicy
 */
final class ImpersonationPolicyTest extends TestCase
{
    private const RVID = 3;
    private const CALLER = 10;
    private const TARGET = 20;

    /**
     * @param list<string> $callerRoles
     * @param list<string> $targetRoles
     */
    private static function evaluate(
        array $callerRoles,
        array $targetRoles,
        bool $targetHasJournalRole = true,
        bool $impersonating = false,
        int $rvid = self::RVID,
        int $targetUid = self::TARGET
    ): string {
        return ImpersonationPolicy::evaluate(
            $callerRoles,
            $rvid,
            self::CALLER,
            $targetUid,
            $targetRoles,
            $targetHasJournalRole,
            $impersonating
        );
    }

    // ---------------------------------------------------------------- privilege

    public function testMemberCannotUseSu(): void
    {
        self::assertSame(
            ImpersonationPolicy::DENIED_INSUFFICIENT_PRIVILEGES,
            self::evaluate([Episciences_Acl::ROLE_MEMBER], [Episciences_Acl::ROLE_AUTHOR])
        );
    }

    public function testEditorCannotUseSu(): void
    {
        self::assertSame(
            ImpersonationPolicy::DENIED_INSUFFICIENT_PRIVILEGES,
            self::evaluate([Episciences_Acl::ROLE_EDITOR], [Episciences_Acl::ROLE_AUTHOR])
        );
    }

    public function testSecretaryCannotUseSuOnThePortal(): void
    {
        self::assertFalse(ImpersonationPolicy::hasPrivilege([Episciences_Acl::ROLE_SECRETARY], 0));
        self::assertSame(
            ImpersonationPolicy::DENIED_INSUFFICIENT_PRIVILEGES,
            self::evaluate([Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_AUTHOR], true, false, 0)
        );
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function journalManagerRolesProvider(): array
    {
        return [
            'secretary' => [[Episciences_Acl::ROLE_SECRETARY]],
            'administrator' => [[Episciences_Acl::ROLE_ADMIN]],
            'chief editor' => [[Episciences_Acl::ROLE_CHIEF_EDITOR]],
            'root' => [[Episciences_Acl::ROLE_ROOT]],
        ];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('journalManagerRolesProvider')]
    public function testSecretaryAndAboveHaveThePrivilegeInAJournal(array $roles): void
    {
        self::assertTrue(ImpersonationPolicy::hasPrivilege($roles, self::RVID));
    }

    public function testRootHasThePrivilegeOnThePortal(): void
    {
        self::assertTrue(ImpersonationPolicy::hasPrivilege([Episciences_Acl::ROLE_ROOT], 0));
    }

    // ---------------------------------------------------------------- nesting / self

    public function testNestedSuIsDenied(): void
    {
        self::assertSame(
            ImpersonationPolicy::DENIED_ALREADY_IMPERSONATING,
            self::evaluate([Episciences_Acl::ROLE_ROOT], [Episciences_Acl::ROLE_AUTHOR], true, true)
        );
    }

    public function testSwitchingToOneselfIsDenied(): void
    {
        self::assertSame(
            ImpersonationPolicy::DENIED_SELF_TARGET,
            self::evaluate([Episciences_Acl::ROLE_ROOT], [Episciences_Acl::ROLE_ROOT], true, false, self::RVID, self::CALLER)
        );
    }

    // ---------------------------------------------------------------- scoping

    public function testSecretaryCannotSwitchToAnAccountWithoutRoleInTheJournal(): void
    {
        self::assertSame(
            ImpersonationPolicy::DENIED_TARGET_NOT_IN_JOURNAL,
            self::evaluate([Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_MEMBER], false)
        );
    }

    public function testRootMaySwitchToAnAccountWithoutRoleInTheJournal(): void
    {
        self::assertSame(
            ImpersonationPolicy::ALLOWED,
            self::evaluate([Episciences_Acl::ROLE_ROOT], [Episciences_Acl::ROLE_MEMBER], false)
        );
    }

    // ---------------------------------------------------------------- escalation

    /**
     * @return array<string, array{list<string>, list<string>, string}>
     */
    public static function rankProvider(): array
    {
        $allowed = ImpersonationPolicy::ALLOWED;
        $denied = ImpersonationPolicy::DENIED_ESCALATION;

        return [
            'secretary to author' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_AUTHOR], $allowed],
            'secretary to reviewer' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_REVIEWER], $allowed],
            'secretary to editor' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_EDITOR], $allowed],
            'secretary to copy editor and webmaster' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_COPY_EDITOR, Episciences_Acl::ROLE_WEBMASTER], $allowed],
            'secretary to another secretary' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_SECRETARY], $denied],
            'secretary to administrator' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_ADMIN], $denied],
            'secretary to chief editor' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_CHIEF_EDITOR], $denied],
            'secretary to root' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_ROOT], $denied],
            'secretary to an editor who is also administrator' => [[Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_ADMIN], $denied],
            'secretary to an account with an unknown role' => [[Episciences_Acl::ROLE_SECRETARY], ['not_a_known_role'], $denied],
            'administrator to secretary' => [[Episciences_Acl::ROLE_ADMIN], [Episciences_Acl::ROLE_SECRETARY], $allowed],
            'administrator to chief editor' => [[Episciences_Acl::ROLE_ADMIN], [Episciences_Acl::ROLE_CHIEF_EDITOR], $denied],
            'chief editor to administrator' => [[Episciences_Acl::ROLE_CHIEF_EDITOR], [Episciences_Acl::ROLE_ADMIN], $allowed],
            'chief editor to root' => [[Episciences_Acl::ROLE_CHIEF_EDITOR], [Episciences_Acl::ROLE_ROOT], $denied],
            'editor and secretary to editor' => [[Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_SECRETARY], [Episciences_Acl::ROLE_EDITOR], $allowed],
            'root to chief editor' => [[Episciences_Acl::ROLE_ROOT], [Episciences_Acl::ROLE_CHIEF_EDITOR], $allowed],
            'root to root' => [[Episciences_Acl::ROLE_ROOT], [Episciences_Acl::ROLE_ROOT], $allowed],
        ];
    }

    /**
     * @param list<string> $callerRoles
     * @param list<string> $targetRoles
     */
    #[DataProvider('rankProvider')]
    public function testOnlyAccountsRankingBelowTheCallerCanBeImpersonated(array $callerRoles, array $targetRoles, string $expected): void
    {
        self::assertSame($expected, self::evaluate($callerRoles, $targetRoles));
    }

    /**
     * The rank table must follow the role inheritance declared by the ACL.
     */
    public function testRanksFollowTheAclRoleHierarchy(): void
    {
        $property = new ReflectionProperty(Episciences_Acl::class, '_roles');
        $property->setAccessible(true);
        /** @var array<string, ?string> $hierarchy */
        $hierarchy = $property->getValue(new Episciences_Acl());

        /** @var array<string, int> $ranks */
        $ranks = (new ReflectionClassConstant(ImpersonationPolicy::class, 'ROLE_RANKS'))->getValue();

        foreach ($hierarchy as $role => $parent) {
            self::assertArrayHasKey($role, $ranks, "role '$role' has no rank");
            if ($parent !== null) {
                self::assertGreaterThan($ranks[$parent], $ranks[$role], "'$role' must rank above '$parent'");
            }
        }
    }
}
