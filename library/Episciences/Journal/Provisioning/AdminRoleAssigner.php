<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Episciences_Acl;
use Episciences_User;
use InvalidArgumentException;

/**
 * Grants the administrator role on a newly created journal to an existing user.
 *
 * Uses Episciences_User::addRole() rather than saveUserRoles() directly: saveUserRoles() first
 * deletes the user's existing "editable" roles for the RVID before reinserting, so calling it
 * here would wipe any role the admin already holds on that journal under --complete. addRole()
 * reads the current roles, merges the new one in, then calls saveUserRoles() with the merged
 * set — the same safe, additive pattern scripts/CreateBotUserCommand.php uses.
 */
final class AdminRoleAssigner
{
    public function assertUserExists(int $uid): void
    {
        $user = new Episciences_User();
        if ($user->find($uid) === []) {
            throw new InvalidArgumentException("No user found with UID $uid.");
        }
    }

    public function assign(int $uid, int $rvid): void
    {
        $user = new Episciences_User();
        $user->setUid($uid);
        $user->addRole(Episciences_Acl::ROLE_ADMIN, $rvid);
    }
}
