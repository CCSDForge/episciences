<?php

declare(strict_types=1);

namespace Episciences\User;

/**
 * Authorization policy for the Switch User (su) functionality.
 *
 * Enforces security invariants:
 * - Requester must hold sufficient privileges (Root, or Secretary in the current review).
 * - Anti-nesting: cannot invoke su while already impersonating.
 * - Anti-auto-switch: cannot switch to one's own account.
 * - Anti-escalation: a non-Root caller cannot switch to a Root user.
 */
final class ImpersonationPolicy
{
    public const DENIED_INSUFFICIENT_PRIVILEGES = 'insufficient_privileges';
    public const DENIED_ALREADY_IMPERSONATING = 'already_impersonating';
    public const DENIED_SELF_TARGET = 'self_target';
    public const DENIED_ESCALATION_TO_ROOT = 'escalation_to_root';
    public const ALLOWED = 'allowed';

    /**
     * Evaluates whether an impersonation request is permitted.
     *
     * @param bool $isCallerRoot Whether the initiating user is Root
     * @param bool $isCallerSecretary Whether the initiating user is Secretary in the current journal
     * @param int $rvid Current journal id
     * @param int $actingUid Acting user UID
     * @param int $targetUid Target user UID
     * @param bool $isTargetRoot Whether the target user is Root
     * @param bool $isCurrentlyImpersonating Whether the caller is already impersonating
     * @return string One of the DENIED_* or ALLOWED constants
     */
    public static function evaluate(
        bool $isCallerRoot,
        bool $isCallerSecretary,
        int $rvid,
        int $actingUid,
        int $targetUid,
        bool $isTargetRoot,
        bool $isCurrentlyImpersonating
    ): string {
        $hasPrivilege = ($rvid === 0 && $isCallerRoot) || ($rvid !== 0 && ($isCallerRoot || $isCallerSecretary));
        if (!$hasPrivilege) {
            return self::DENIED_INSUFFICIENT_PRIVILEGES;
        }

        if ($isCurrentlyImpersonating) {
            return self::DENIED_ALREADY_IMPERSONATING;
        }

        if ($actingUid === $targetUid) {
            return self::DENIED_SELF_TARGET;
        }

        if (!$isCallerRoot && $isTargetRoot) {
            return self::DENIED_ESCALATION_TO_ROOT;
        }

        return self::ALLOWED;
    }
}
