<?php

/**
 * Authentication on Episciences
 *
 */
class Episciences_Auth extends Ccsd_Auth
{
    /**
     * UID for anonymous/system users (e.g., anonymized editors)
     * Value 0 ensures no real user avatar is displayed
     */
    public const ANONYMOUS_UID = 0;

    /**
     * Retrieve user privileges for the current site
     * @return list<string>
     */
    public static function getRoles(): array
    {
        if (self::isLogged()) {
            $roles = self::getInstance()->getIdentity()->getRoles();
        } else {
            $roles = [Episciences_Acl::ROLE_GUEST];
        }

        return $roles;
    }

    public static function getFullName(): ?string
    {
        return self::getInstance()->getIdentity()->getFullName();
    }

    public static function getEmail(): ?string
    {
        return self::getInstance()->getIdentity()->getEmail();
    }

    public static function getFirstname(): ?string
    {
        return self::getInstance()->getIdentity()->getFirstname();
    }

    public static function getLastname(): ?string
    {
        return self::getInstance()->getIdentity()->getLastname();
    }

    public static function getLangueid(): mixed
    {
        return self::getInstance()->getIdentity()->getLangueid();
    }

    public static function isWebmaster(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_WEBMASTER, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    /**
     * check if logged-in user has permission $role for a given journal
     * if $rvid is null, check roles in all journals
     * @param string $role
     * @param int|null $rvid
     * @return bool
     */
    public static function is(string $role, int|null $rvid = RVID): bool
    {
        // get user roles list for each journal
        if (self::isLogged()) {
            $user_roles = self::getInstance()->getIdentity()->getAllRoles();
        } else {
            $user_roles[RVID] = [Episciences_Acl::ROLE_GUEST];
        }

        // if $rvid is set, only return roles list for this journal
        if ($rvid !== null) {
            $user_roles = $user_roles[$rvid] ?? [];
        }

        return Ccsd_Tools::in_array_r($role, $user_roles);
    }

    public static function isAdministrator(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_ADMIN, $rvid) || (!$strict && self::isChiefEditor($rvid));
    }

    public static function isChiefEditor(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_CHIEF_EDITOR, $rvid) || (!$strict && self::isRoot($rvid));
    }

    public static function isRoot(int $rvid = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_ROOT, $rvid);
    }

    public static function isMember(): bool
    {
        return self::isLogged();
    }

    public static function isAuthor(int $rvId = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_AUTHOR, $rvId);
    }

    public static function getScreenName(): string
    {
        return self::getInstance()->getIdentity()->getScreenName();
    }

    public static function isAllowedToManagePaper(): bool
    {
        return self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor();
    }

    public static function isSecretary(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_SECRETARY, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    public static function isEditor(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_EDITOR, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    public static function isGuestEditor(int $rvid = RVID, bool $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_GUEST_EDITOR, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    /**
     * @return bool
     */
    public static function isAllowedToManageDoi(): bool
    {
        return self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor() ||
            self::isChiefEditor() ||
            self::isCopyEditor();
    }

    public static function isAllowedToManageOrcidAuthor(bool $isOwner = false): bool
    {
        return self::isAllowedToManagePaper() || $isOwner;
    }

    /**
     * @param int $rvId
     * @return bool
     */
    public static function isCopyEditor(int $rvId = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_COPY_EDITOR, $rvId);
    }

    /**
     * User may send mail (every role except guest and member)
     * @return bool
     */
    public static function isAllowedToSendMail(): bool
    {
        return self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor() ||
            self::isReviewer();
    }

    public static function isReviewer(int $rvid = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_REVIEWER, $rvid);
    }

    /**
     * Ability to upload a peer review report
     * @return bool
     */
    public static function isAllowedToUploadPaperReport(): bool
    {
        return self::isSecretary() || self::isEditor();
    }

    /**
     * Authorizes listing papers assigned to a user
     * @return bool
     */

    public static function isAllowedToListOnlyAssignedPapers(): bool
    {

        try {
            $journalSettings = Zend_Registry::get('reviewSettings');

            return !self::isSecretary() &&
                (self::isEditor(RVID, true) || self::isGuestEditor(RVID, true)) &&
                isset($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_EDITORS]) &&
                !empty($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_EDITORS]);

        } catch (Zend_Exception $e) {
            // @phpstan-ignore notIdentical.alwaysTrue
            if (APPLICATION_MODULE !== OAI) {
                trigger_error($e->getMessage());
            }
            return false;
        }

    }

    /**
     * Get user profile photo version
     * @return string
     */
    public static function getPhotoVersion(): string
    {
        if (!self::isLogged()) {
            return self::getPhotoVersionAsHash(0);
        }

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        if (!is_int($session->photoVersion)) {
            $session->photoVersion = 0;
        }
        return self::getPhotoVersionAsHash($session->photoVersion);
    }

    public static function getPhotoVersionAsHash(int $photoVersion): string
    {
        // add some salt with uid
        return sha1(self::getUid() . $photoVersion);
    }


    /**
     * Increment user profile photo version
     */
    public static function incrementPhotoVersion(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        if (!is_int($session->photoVersion)) {
            $session->photoVersion = 0;
        }
        $session->photoVersion++;
    }

    /**
     * Switch the session to another user, remembering the current identity so it can be restored.
     *
     * The stack is bound to the impersonated UID: it is only honoured while that user is the
     * authenticated one.
     */
    public static function startImpersonation(Episciences_User $target): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $realIdentities = self::getAllIdentities();
        $realIdentities = self::isImpersonating() ? $realIdentities : [];
        $realIdentities[] = self::getUser();

        $session->realIdentities = $realIdentities;
        $session->impersonatedUid = (int)$target->getUid();

        self::updateIdentity($target);
    }

    /**
     * Restore the identity saved by the last startImpersonation().
     *
     * @return Episciences_User|null The restored identity, null when the session is not impersonating
     *                               or when the saved identities are unbound (the identity is then cleared)
     */
    public static function endImpersonation(): ?Episciences_User
    {
        if (!self::isImpersonating()) {
            self::clearImpersonation();
            return null;
        }

        // The saved identities cannot be trusted: nothing is restored and the session is closed
        if (self::hasUnboundImpersonationStack()) {
            self::clearImpersonation();
            self::getInstance()->clearIdentity();
            return null;
        }

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        /** @var Episciences_User[] $realIdentities */
        $realIdentities = $session->realIdentities;
        $originalUser = array_pop($realIdentities);

        if (empty($realIdentities)) {
            self::clearImpersonation();
        } else {
            $session->realIdentities = $realIdentities;
            $session->impersonatedUid = (int)$originalUser->getUid();
        }

        self::updateIdentity($originalUser);

        return $originalUser;
    }

    /**
     * Forget any switch user state (login, logout, stale state).
     */
    public static function clearImpersonation(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities, $session->impersonatedUid);
    }

    /**
     * A non-empty identity stack that is not bound to the authenticated user (e.g. a switch user
     * session started before the stack was bound to the impersonated UID).
     * The session must not be considered as the account owner's one.
     */
    private static function hasUnboundImpersonationStack(): bool
    {
        if (!self::isLogged()) {
            return false;
        }

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $realIdentities = $session->realIdentities;

        return is_array($realIdentities) && $realIdentities !== [] &&
            (int)$session->impersonatedUid !== (int)self::getUid();
    }

    /**
     * Check if user has a real identity
     * @return bool
     */
    public static function hasRealIdentity(): bool
    {
        if (!self::isLogged() || self::hasUnboundImpersonationStack()) {
            return false;
        }
        $original = self::getOriginalIdentity();
        return $original !== null && $original->getUid() === self::getUid();
    }

    /**
     * Check if the current session is impersonating another user (via suAction).
     * @return bool
     */
    public static function isImpersonating(): bool
    {
        return self::isLogged() && !self::hasRealIdentity();
    }

    /**
     * @return Episciences_User | null
     */
    public static function getOriginalIdentity(): ?Episciences_User
    {
        $identities = self::getAllIdentities();
        if (empty($identities)) {
            return null;
        }
        return $identities[array_key_first($identities)];
    }

    /**
     * @return Episciences_User []
     */
    public static function getAllIdentities(): array
    {
        if (!self::isLogged()) {
            return [self::getUser()];
        }

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $realIdentities = $session->realIdentities;

        // A stack left behind by another login is not honoured
        if (
            !is_array($realIdentities) || $realIdentities === [] ||
            (int)$session->impersonatedUid !== (int)self::getUid()
        ) {
            return [self::getUser()];
        }

        return $realIdentities;
    }

    /**
     * @return bool
     */
    public static function isAllowedToDeclareConflict(): bool
    {
        $result =
            self::isCopyEditor() ||
            self::isGuestEditor(RVID, true) ||
            self::isEditor(RVID, true) ||
            self::isSecretary(RVID, true) ||
            self::isChiefEditor(RVID, true);

        if (!self::hasRealIdentity()) {

            $suUser = self::getOriginalIdentity();

            if ($suUser && !$suUser->isRoot()) {

                $result = $result ||
                    $suUser->isCopyEditor() ||
                    $suUser->isGuestEditor() ||
                    $suUser->isEditor() ||
                    $suUser->isSecretary() ||
                    $suUser->isChiefEditor();

            }

        }

        return $result;
    }


    public static function updateIdentity(Episciences_User $user): void
    {
        self::getInstance()->clearIdentity();
        self::setIdentity($user);
        $user->setScreenName();
        self::incrementPhotoVersion();
    }


    public static function hasOnlyAdministratorRole(int $rvId = RVID): bool
    {
        return
            self::isAdministrator($rvId, true) &&
            !self::isChiefEditor($rvId, true) &&
            !self::isSecretary($rvId, true) &&
            !self::isEditor($rvId, true) &&
            !self::isGuestEditor($rvId, true) &&
            !self::isCopyEditor($rvId);
    }

    public static function setCurrentAttachmentsPathInSession(string $currentPath): \Zend_Session_Namespace
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        $session->currentAttachmentsPath = $currentPath;

        return $session;

    }

    public static function resetCurrentAttachmentsPath(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->currentAttachmentsPath);

    }


    public static function isAllowedFormatOnlyAssignedPapers(): bool
    {

        try {
            $journalSettings = Zend_Registry::get('reviewSettings');

            return !self::isSecretary() &&
                self::isCopyEditor() &&
                isset($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS]) &&
                !empty($journalSettings[Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS]);

        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
            return false;
        }

    }

}
