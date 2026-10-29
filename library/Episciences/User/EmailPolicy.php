<?php

namespace Episciences\User;

use Ccsd_User_Models_User;
use Ccsd_User_Models_UserMapper;

/**
 * Single point of decision for "is this email available" across every entry
 * point that can create or change an account's email (change-email form,
 * account creation, reviewer invitation acceptance).
 */
final class EmailPolicy
{
    public static function normalize(string $raw): string
    {
        return Ccsd_User_Models_User::normalizeEmail($raw);
    }

    /**
     * Whether two addresses are the same account-identifying value.
     * Matches T_UTILISATEURS' utf8mb3_general_ci + PAD SPACE comparison:
     * case-insensitive, trailing spaces ignored. Used only to short-circuit
     * the "nothing changed" case, not as a general equality/dedup rule
     * (no Gmail-style dot/+tag folding).
     */
    public static function isSameAddress(string $a, string $b): bool
    {
        return strcasecmp(rtrim($a), rtrim($b)) === 0;
    }

    /**
     * Normalizes $rawEmail and returns the UID of another account already
     * using it, or null if the address is available. $excludeUid = 0 means
     * "creating a new account": any match is a conflict.
     *
     * @param ?callable(string): int[] $uidsFinder Injectable for tests; defaults
     *     to Ccsd_User_Models_UserMapper::findUidsByEmail().
     */
    public static function findConflictingUid(string $rawEmail, int $excludeUid, ?callable $uidsFinder = null): ?int
    {
        $normalized = self::normalize($rawEmail);
        $finder = $uidsFinder ?? static function (string $email): array {
            return (new Ccsd_User_Models_UserMapper())->findUidsByEmail($email);
        };

        foreach ($finder($normalized) as $uid) {
            if ((int)$uid !== $excludeUid) {
                return (int)$uid;
            }
        }

        return null;
    }
}
