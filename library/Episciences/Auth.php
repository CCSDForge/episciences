<?php

/**
 * Authentification sur Episciences
 *
 */
class Episciences_Auth extends Ccsd_Auth
{
    /**
     * Récupération des privilèges de l'utilisateur pour le site actuel
     * @return array
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

    public static function getFullName()
    {
        return self::getInstance()->getIdentity()->getFullName();
    }

    public static function getEmail()
    {
        return self::getInstance()->getIdentity()->getEmail();
    }

    public static function getFirstname()
    {
        return self::getInstance()->getIdentity()->getFirstname();
    }

    public static function getLastname()
    {
        return self::getInstance()->getIdentity()->getLastname();
    }

    public static function getLangueid()
    {
        return self::getInstance()->getIdentity()->getLangueid();
    }

    public static function isWebmaster($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_WEBMASTER, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    /**
     * check if logged-in user has permission $role for a given journal
     * if $rvid is null, check roles in all journals
     * @param $role
     * @param int $rvid
     * @return bool
     */
    public static function is($role, $rvid = RVID): bool
    {
        // get user roles list for each journal
        if (self::isLogged()) {
            $user_roles = self::getInstance()->getIdentity()->getAllRoles();
        } else {
            $user_roles[RVID] = [Episciences_Acl::ROLE_GUEST];
        }

        // if $rvid is set, only return roles list for this journal
        if (is_numeric($rvid)) {
            $user_roles = $user_roles[$rvid];
        }

        return Ccsd_Tools::in_array_r($role, $user_roles);
    }

    public static function isAdministrator($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_ADMIN, $rvid) || (!$strict && self::isChiefEditor($rvid));
    }

    public static function isChiefEditor($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_CHIEF_EDITOR, $rvid) || (!$strict && self::isRoot($rvid));
    }

    public static function isRoot($rvid = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_ROOT, $rvid);
    }

    public static function isMember(): bool
    {
        return self::isLogged();
    }

    public static function getScreenName()
    {
        return self::getInstance()->getIdentity()->getScreenName();
    }

    public static function isAllowedToManagePaper(): bool
    {
        return (
            self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor()
        );
    }

    public static function isSecretary($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_SECRETARY, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    public static function isEditor($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_EDITOR, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    public static function isGuestEditor($rvid = RVID, $strict = false): bool
    {
        return self::is(Episciences_Acl::ROLE_GUEST_EDITOR, $rvid) || (!$strict && self::isAdministrator($rvid));
    }

    /**
     * @return bool
     */
    public static function isAllowedToManageDoi(): bool
    {
        return (
            self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor() ||
            self::isChiefEditor() ||
            self::isCopyEditor()
        );
    }

    /**
     * @param int $rvId
     * @return bool
     */
    public static function isCopyEditor($rvId = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_COPY_EDITOR, $rvId);
    }

    /**
     * User may send mail (every role except guest and member)
     * @return bool
     */
    public static function isAllowedToSendMail(): bool
    {
        return (
            self::isSecretary() ||
            self::isEditor() ||
            self::isGuestEditor() ||
            self::isReviewer()
        );
    }

    public static function isReviewer($rvid = RVID): bool
    {
        return self::is(Episciences_Acl::ROLE_REVIEWER, $rvid);
    }

    /**
     * Possibilité de déposer un rapport de relecture
     * @return bool
     */
    public static function isAllowedToUploadPaperReport(): bool
    {
        return (
            self::isSecretary() ||
            self::isEditor()
        );
    }

    /**
     * Autorise de lister les papiers assignés à un utilisateur
     * @return bool
     */

    public static function isAllowedToListOnlyAssignedPapers(): bool
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        return (
            !self::isSecretary() &&
            (
                self::isEditor(RVID, true) ||
                self::isGuestEditor(RVID, true)
            ) &&
            $review->getSetting('encapsulateEditors')
        );
    }

    /**
     * Get user profile photo version
     * @return string
     */
    public static function getPhotoVersion(): string
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        if (!is_int($session->photoVersion)) {
            $session->photoVersion = 0;
        }
        return self::getPhotoVersionAsHash($session->photoVersion);
    }

    /**
     * @param $photoVersion
     * @return string
     */
    public static function getPhotoVersionAsHash($photoVersion) :string
    {
        //add some salt with uid
        return sha1(self::getUid() . $photoVersion);
    }


    /**
     * Increment user profile photo version
     */
    public static function incrementPhotoVersion()
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        if (!is_int($session->photoVersion)) {
            $session->photoVersion = 0;
        }
        $session->photoVersion++;
    }


}
