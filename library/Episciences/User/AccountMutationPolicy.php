<?php

declare(strict_types=1);

namespace Episciences\User;

/**
 * Single source of truth for account mutation authorization.
 *
 * Enforces the invariant:
 * - Legitimate account owners (logged in, not impersonating) may always modify their account.
 * - Sessions under switch user (su) are strictly read-only for everyone (no mutation permitted).
 * - Root (epiadmin) accounts acting under their own identity are exclusively authorized to
 *   mutate third-party accounts for administration and maintenance purposes.
 * - Other third parties (secretaries, journal administrators) may never edit another user's account.
 */
final class AccountMutationPolicy
{
    /**
     * Determines whether an acting user is allowed to mutate the target account.
     *
     * @param int $targetUid The UID of the account being modified
     * @param ?int $actingUid The UID of the user performing the request
     * @param bool $isImpersonating Whether the current session is under switch user (su)
     * @param bool $isRoot Whether the acting user has root (epiadmin) privileges
     * @return bool True if authorized, false otherwise
     */
    public static function canMutateAccount(
        int $targetUid,
        ?int $actingUid,
        bool $isImpersonating,
        bool $isRoot = false
    ): bool {
        if ($actingUid === null || $actingUid <= 0) {
            return false;
        }

        // Switch user sessions are strictly read-only for all users
        if ($isImpersonating) {
            return false;
        }

        // Account owners can mutate their own account
        if ($targetUid === $actingUid) {
            return true;
        }

        // Only root (epiadmin) may mutate a third-party account
        return $isRoot;
    }

    /**
     * Helper for profile editing (/user/edit).
     *
     * @param ?int $requestedUid The UID requested in the request (null or 0 means self)
     * @param ?int $actingUid The UID of the authenticated user
     * @param bool $isImpersonating Whether the current session is under switch user (su)
     * @param bool $isRoot Whether the acting user has root (epiadmin) privileges
     * @return bool True if authorized, false otherwise
     */
    public static function canEditProfile(
        ?int $requestedUid,
        ?int $actingUid,
        bool $isImpersonating,
        bool $isRoot = false
    ): bool {
        if ($actingUid === null || $actingUid <= 0 || $isImpersonating) {
            return false;
        }

        if ($requestedUid === null || $requestedUid === 0 || $requestedUid === $actingUid) {
            return true;
        }

        return $isRoot;
    }

    /**
     * Helper for photo deletion (/user/ajaxdeletephoto).
     */
    public static function canDeletePhoto(
        int $photoOwnerUid,
        ?int $actingUid,
        bool $isImpersonating,
        bool $isRoot = false
    ): bool {
        return self::canMutateAccount($photoOwnerUid, $actingUid, $isImpersonating, $isRoot);
    }

    /**
     * Helper for email change (/user/changeaccountemail).
     */
    public static function canChangeEmail(
        int $targetUid,
        ?int $actingUid,
        bool $isImpersonating,
        bool $isRoot = false
    ): bool {
        return self::canMutateAccount($targetUid, $actingUid, $isImpersonating, $isRoot);
    }
}
