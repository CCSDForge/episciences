<?php

declare(strict_types=1);

namespace Episciences\User\Import;

use Ccsd_User_Models_UserMapper;
use Episciences_Reviewer_AccountResolver as AccountResolver;
use Episciences_User;
use RuntimeException;
use Zend_Db_Table_Abstract;

/**
 * Database access of the user import: accounts (shared accounts table and Episciences users) and journal roles.
 */
class UserAccountStore
{
    /**
     * Account to reuse for this email: the most recently modified valid one, otherwise the most recently modified
     * one not activated yet
     *
     * @return array{UID: int, VALID: int}|null
     */
    public function findByEmail(string $email): ?array
    {
        return (new Ccsd_User_Models_UserMapper())->findMostRecentByEmail($email);
    }

    public function loadUser(int $uid): Episciences_User
    {
        $user = new Episciences_User();
        $user->findWithCAS($uid);

        if ((int)$user->getUid() !== $uid) {
            throw new RuntimeException(sprintf('account #%d cannot be loaded', $uid));
        }

        return $user;
    }

    /**
     * Creates the Episciences data of an account only known in the shared accounts table (created by another
     * application): screen name, language. Existing data, names included, are never changed.
     *
     * @return bool true when the data has been created
     */
    public function completeLocalData(Episciences_User $user, string $lang, int $rvId): bool
    {
        if ($user->hasLocalData((int)$user->getUid())) {
            return false;
        }

        $user->setScreenName($user->getFullName());
        $user->setLangueid($lang);
        $user->setIs_valid();
        $user->setRegistrationDate();

        // the shared account is left untouched: rewriting it could restore an email changed meanwhile
        if ($user->save(false, false, $rvId) === false) {
            throw new RuntimeException(sprintf('the Episciences data of account #%d cannot be saved', (int)$user->getUid()));
        }

        return true;
    }

    /**
     * Creates an account not activated yet, with a strong random password nobody knows: the account is activated
     * when its owner chooses a password with the link sent to the address, which proves the address.
     */
    public function createAccount(UserImportRow $row, string $lang, int $rvId): Episciences_User
    {
        $login = AccountResolver::resolveAvailableLogin(
            AccountResolver::generateLoginBase($row->firstname, $row->lastname, $row->email),
            static fn(string $candidate): bool => AccountResolver::usernameExists($candidate)
        );

        $user = new Episciences_User();
        $user->setFirstname($row->firstname);
        $user->setLastname($row->lastname);
        $user->setEmail($row->email);
        $user->setUsername($login);
        $user->setPassword(AccountResolver::generateStrongPassword());
        $user->setLangueid($lang);
        $user->setTime_registered();
        $user->setRegistrationDate();
        $user->setScreenName($user->getFullName());
        $user->setValid(0);
        $user->setIs_valid();

        $result = $user->save(false, true, $rvId);
        if ($result === false) {
            throw new RuntimeException(sprintf('the account of "%s" cannot be created', $row->email));
        }

        // save() may return 0 with an explicit UID insert (dev CAS adapter): the UID set on the user is preferred
        if (!$user->getUid()) {
            $user->setUid((int)$result);
        }

        return $user;
    }

    /**
     * Roles stored for the account in the journal (an account without any role is not a member yet)
     *
     * @return list<string>
     */
    public function journalRoles(int $uid, int $rvId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_USER_ROLES, ['ROLEID'])
            ->where('UID = ?', $uid)
            ->where('RVID = ?', $rvId);

        return array_values(array_map('strval', $db->fetchCol($select)));
    }

    /**
     * Adds roles in the journal, keeping the roles already held
     *
     * @param list<string> $roles
     */
    public function addRoles(int $uid, array $roles, int $rvId): void
    {
        if ($roles !== [] && !(new Episciences_User())->saveNewRoles($uid, $roles, $rvId)) {
            throw new RuntimeException(sprintf('the roles of account #%d cannot be saved', $uid));
        }
    }
}
