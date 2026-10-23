<?php

declare(strict_types=1);

namespace Episciences\User;

use Episciences_Acl;

/**
 * Authorization policy for the Switch User (su) functionality.
 *
 * Enforces security invariants:
 * - Requester must hold sufficient privileges (Root, or Secretary or above in the current review).
 * - Anti-nesting: cannot invoke su while already impersonating.
 * - Anti-auto-switch: cannot switch to one's own account.
 * - Scoping: a non-Root caller may only switch to a user holding a role in the current review.
 * - Anti-escalation: a non-Root caller may only switch to a user ranking strictly below them in the
 *   current review (each review has its own session: only the roles of the current review apply).
 */
final class ImpersonationPolicy
{
    public const DENIED_INSUFFICIENT_PRIVILEGES = 'insufficient_privileges';
    public const DENIED_ALREADY_IMPERSONATING = 'already_impersonating';
    public const DENIED_SELF_TARGET = 'self_target';
    public const DENIED_TARGET_NOT_IN_JOURNAL = 'target_not_in_journal';
    public const DENIED_ESCALATION = 'escalation';
    public const ALLOWED = 'allowed';

    /**
     * Rank of each role, following the inheritance chain declared in Episciences_Acl:
     * a role always ranks strictly above the role it inherits from.
     *
     * Known limitation: the ACL tree is reduced to a total order. Side branches (boards, author,
     * reviewer, copy editor, webmaster...) are not inherited by the secretary but rank below it, so a
     * secretary may switch to a copy editor or a webmaster and get their role-specific checks.
     * This is accepted: switching to authors and board members must stay possible, and the secretary
     * already holds most of these resources in acl.ini. A new side role must be ranked with care.
     */
    private const ROLE_RANKS = [
        Episciences_Acl::ROLE_GUEST => 0,
        Episciences_Acl::ROLE_MEMBER => 1,
        Episciences_Acl::ROLE_EDITORIAL_BOARD => 2,
        Episciences_Acl::ROLE_TECHNICAL_BOARD => 2,
        Episciences_Acl::ROLE_SCIENTIFIC_ADVISORY_BOARD => 2,
        Episciences_Acl::ROLE_ADVISORY_BOARD => 2,
        Episciences_Acl::ROLE_MANAGING_EDITOR => 2,
        Episciences_Acl::ROLE_HANDLING_EDITOR => 2,
        Episciences_Acl::ROLE_FORMER_MEMBER => 2,
        Episciences_Acl::ROLE_AUTHOR => 2,
        Episciences_Acl::ROLE_CO_AUTHOR => 2,
        Episciences_Acl::ROLE_REVIEWER => 2,
        Episciences_Acl::ROLE_COPY_EDITOR => 2,
        Episciences_Acl::ROLE_WEBMASTER => 2,
        Episciences_Acl::ROLE_GUEST_EDITOR => 3,
        Episciences_Acl::ROLE_EDITOR => 4,
        Episciences_Acl::ROLE_SECRETARY => 5,
        Episciences_Acl::ROLE_ADMIN => 6,
        Episciences_Acl::ROLE_CHIEF_EDITOR => 7,
        Episciences_Acl::ROLE_ROOT => 8,
    ];

    /**
     * Whether the caller may use the su feature at all in the current review.
     *
     * @param list<string> $callerRoles Roles of the caller in the current review
     * @param int $rvid Current journal id (0 for the portal)
     */
    public static function hasPrivilege(array $callerRoles, int $rvid): bool
    {
        if (in_array(Episciences_Acl::ROLE_ROOT, $callerRoles, true)) {
            return true;
        }

        return $rvid !== 0 && self::highestRank($callerRoles, 0) >= self::ROLE_RANKS[Episciences_Acl::ROLE_SECRETARY];
    }

    /**
     * Evaluates whether an impersonation request is permitted.
     *
     * @param list<string> $callerRoles Roles of the initiating user in the current review
     * @param int $rvid Current journal id (0 for the portal)
     * @param int $actingUid Acting user UID
     * @param int $targetUid Target user UID
     * @param list<string> $targetRoles Roles of the target user in the current review
     * @param bool $targetHasJournalRole Whether the target holds a stored role in the current review
     * @param bool $isCurrentlyImpersonating Whether the caller is already impersonating
     * @return string One of the DENIED_* or ALLOWED constants
     */
    public static function evaluate(
        array $callerRoles,
        int $rvid,
        int $actingUid,
        int $targetUid,
        array $targetRoles,
        bool $targetHasJournalRole,
        bool $isCurrentlyImpersonating
    ): string {
        if (!self::hasPrivilege($callerRoles, $rvid)) {
            return self::DENIED_INSUFFICIENT_PRIVILEGES;
        }

        if ($isCurrentlyImpersonating) {
            return self::DENIED_ALREADY_IMPERSONATING;
        }

        if ($actingUid === $targetUid) {
            return self::DENIED_SELF_TARGET;
        }

        if (in_array(Episciences_Acl::ROLE_ROOT, $callerRoles, true)) {
            return self::ALLOWED;
        }

        if (!$targetHasJournalRole) {
            return self::DENIED_TARGET_NOT_IN_JOURNAL;
        }

        // Unknown target roles rank above everything: fail closed
        if (self::highestRank($targetRoles, PHP_INT_MAX) >= self::highestRank($callerRoles, 0)) {
            return self::DENIED_ESCALATION;
        }

        return self::ALLOWED;
    }

    /**
     * @param list<string> $roles
     * @param int $unknownRoleRank Rank given to a role missing from the hierarchy
     */
    private static function highestRank(array $roles, int $unknownRoleRank): int
    {
        $highest = 0;
        foreach ($roles as $role) {
            $highest = max($highest, self::ROLE_RANKS[$role] ?? $unknownRoleRank);
        }

        return $highest;
    }
}
