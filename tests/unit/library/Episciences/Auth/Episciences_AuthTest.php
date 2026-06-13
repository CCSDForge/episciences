<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Auth
 */
class Episciences_AuthTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('SESSION_NAMESPACE')) {
            define('SESSION_NAMESPACE', 'Episciences_Auth');
        }
        if (!defined('RVID')) {
            define('RVID', 1);
        }

        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
        unset($session->photoVersion);
        unset($session->currentAttachmentsPath);
    }

    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
        unset($session->photoVersion);
        unset($session->currentAttachmentsPath);
    }

    private function createMockUser(int $uid, array $roles = [], int $rvid = RVID): Episciences_User
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([$rvid => $roles]);
        $user->method('isRoot')->willReturn(in_array(Episciences_Acl::ROLE_ROOT, $roles));
        $user->method('isCopyEditor')->willReturn(in_array(Episciences_Acl::ROLE_COPY_EDITOR, $roles));
        $user->method('isGuestEditor')->willReturn(in_array(Episciences_Acl::ROLE_GUEST_EDITOR, $roles));
        $user->method('isEditor')->willReturn(in_array(Episciences_Acl::ROLE_EDITOR, $roles));
        $user->method('isSecretary')->willReturn(in_array(Episciences_Acl::ROLE_SECRETARY, $roles));
        $user->method('isChiefEditor')->willReturn(in_array(Episciences_Acl::ROLE_CHIEF_EDITOR, $roles));
        $user->method('getScreenName')->willReturn('User ' . $uid);
        return $user;
    }

    private function loginUser(Episciences_User $user): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    // ==================== getRoles ====================

    public function testGetRolesWhenNotLogged(): void
    {
        $roles = Episciences_Auth::getRoles();
        self::assertSame([Episciences_Acl::ROLE_GUEST], $roles);
    }

    public function testGetRolesWhenLogged(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_MEMBER]);
        $this->loginUser($user);

        $roles = Episciences_Auth::getRoles();
        self::assertSame([Episciences_Acl::ROLE_MEMBER], $roles);
    }

    public function testGetRolesReturnsMultipleRoles(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        $roles = Episciences_Auth::getRoles();
        self::assertContains(Episciences_Acl::ROLE_EDITOR, $roles);
        self::assertContains(Episciences_Acl::ROLE_REVIEWER, $roles);
    }

    // ==================== is() ====================

    public function testIsReturnsFalseForGuestWhenNotLogged(): void
    {
        self::assertFalse(Episciences_Auth::is(Episciences_Acl::ROLE_EDITOR));
        self::assertTrue(Episciences_Auth::is(Episciences_Acl::ROLE_GUEST));
    }

    public function testIsReturnsTrueForMatchingRole(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::is(Episciences_Acl::ROLE_EDITOR));
    }

    public function testIsReturnsFalseForNonMatchingRole(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::is(Episciences_Acl::ROLE_EDITOR));
    }

    public function testIsReturnsFalseForUnknownJournal(): void
    {
        // User has roles for RVID=1 but we ask for RVID=999
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_EDITOR], 1);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::is(Episciences_Acl::ROLE_EDITOR, 999));
    }

    public function testIsWithNullRvidSearchesAllJournals(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_EDITOR], 1);
        $this->loginUser($user);

        // null means search all journals — role exists in journal 1
        self::assertTrue(Episciences_Auth::is(Episciences_Acl::ROLE_EDITOR, null));
    }

    // ==================== Role hierarchy checks ====================

    public function testIsRootForRootUser(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ROOT]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isRoot());
    }

    public function testIsRootFalseForNonRoot(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isRoot());
    }

    public function testIsChiefEditorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isChiefEditor());
    }

    public function testIsChiefEditorViaNonStrictRoot(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ROOT]);
        $this->loginUser($user);

        // non-strict: root implies chief_editor
        self::assertTrue(Episciences_Auth::isChiefEditor());
    }

    public function testIsChiefEditorStrictFalseForRoot(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ROOT]);
        $this->loginUser($user);

        // strict: root does NOT count as chief_editor
        self::assertFalse(Episciences_Auth::isChiefEditor(RVID, true));
    }

    public function testIsAdministratorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAdministrator());
    }

    public function testIsAdministratorViaChiefEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->loginUser($user);

        // non-strict: chief_editor implies administrator
        self::assertTrue(Episciences_Auth::isAdministrator());
    }

    public function testIsAdministratorStrictFalseForChiefEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAdministrator(RVID, true));
    }

    public function testIsWebmasterDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_WEBMASTER]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isWebmaster());
    }

    public function testIsWebmasterViaAdministrator(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isWebmaster());
    }

    public function testIsWebmasterStrictFalseForAdmin(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isWebmaster(RVID, true));
    }

    public function testIsSecretaryDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_SECRETARY]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isSecretary());
    }

    public function testIsSecretaryViaAdmin(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isSecretary());
    }

    public function testIsEditorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isEditor());
    }

    public function testIsGuestEditorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isGuestEditor());
    }

    public function testIsReviewerDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isReviewer());
    }

    public function testIsReviewerFalseForGuest(): void
    {
        self::assertFalse(Episciences_Auth::isReviewer());
    }

    public function testIsAuthorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_AUTHOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAuthor());
    }

    public function testIsCopyEditorDirect(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isCopyEditor());
    }

    public function testIsMemberReturnsTrueWhenLogged(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_MEMBER]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isMember());
    }

    public function testIsMemberReturnsFalseWhenNotLogged(): void
    {
        self::assertFalse(Episciences_Auth::isMember());
    }

    // ==================== isAllowed* aggregates ====================

    public function testIsAllowedToManagePaperForSecretary(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_SECRETARY]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToManagePaper());
    }

    public function testIsAllowedToManagePaperForEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToManagePaper());
    }

    public function testIsAllowedToManagePaperForGuestEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToManagePaper());
    }

    public function testIsAllowedToManagePaperFalseForReviewer(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAllowedToManagePaper());
    }

    public function testIsAllowedToManageDoiForCopyEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToManageDoi());
    }

    public function testIsAllowedToManageDoiFalseForReviewer(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAllowedToManageDoi());
    }

    public function testIsAllowedToSendMailForReviewer(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToSendMail());
    }

    public function testIsAllowedToSendMailFalseForAuthor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_AUTHOR]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAllowedToSendMail());
    }

    public function testIsAllowedToUploadPaperReportForSecretary(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_SECRETARY]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToUploadPaperReport());
    }

    public function testIsAllowedToUploadPaperReportFalseForReviewer(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_REVIEWER]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAllowedToUploadPaperReport());
    }

    public function testIsAllowedToManageOrcidAuthorAsOwner(): void
    {
        self::assertTrue(Episciences_Auth::isAllowedToManageOrcidAuthor(true));
    }

    public function testIsAllowedToManageOrcidAuthorAsEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToManageOrcidAuthor(false));
    }

    public function testIsAllowedToManageOrcidAuthorFalseForNonOwnerNonEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_AUTHOR]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::isAllowedToManageOrcidAuthor(false));
    }

    // ==================== hasOnlyAdministratorRole ====================

    public function testHasOnlyAdministratorRoleTrue(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleFalseForAdminPlusEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_ADMIN, Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        // isEditor(strict=true) is true, so hasOnlyAdministratorRole should be false
        self::assertFalse(Episciences_Auth::hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleFalseForNonAdmin(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_EDITOR]);
        $this->loginUser($user);

        self::assertFalse(Episciences_Auth::hasOnlyAdministratorRole());
    }

    // ==================== Identity / impersonation ====================

    public function testHasRealIdentityWhenNotLogged(): void
    {
        self::assertFalse(Episciences_Auth::hasRealIdentity());
    }

    public function testHasRealIdentityWhenLoggedAndNotImpersonating(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_MEMBER]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::hasRealIdentity());
    }

    public function testHasRealIdentityWhenSessionRealIdentitiesIsEmpty(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_MEMBER]);
        $this->loginUser($user);

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [];

        self::assertFalse(Episciences_Auth::hasRealIdentity());
    }

    public function testHasRealIdentityWhenImpersonating(): void
    {
        $admin = $this->createMockUser(99, [Episciences_Acl::ROLE_ADMIN]);
        $author = $this->createMockUser(42, [Episciences_Acl::ROLE_AUTHOR]);

        $this->loginUser($admin);
        Episciences_Auth::saveRealIdentity();
        $this->loginUser($author);

        self::assertFalse(Episciences_Auth::hasRealIdentity());
        self::assertSame($admin, Episciences_Auth::getOriginalIdentity());
    }

    public function testGetOriginalIdentityReturnsNullWhenSessionEmpty(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [];

        // Not logged in, getUser() returns null → getAllIdentities returns []
        self::assertNull(Episciences_Auth::getOriginalIdentity());
    }

    public function testGetAllIdentitiesReturnsCurrentUserWhenNoSession(): void
    {
        $user = $this->createMockUser(42, [Episciences_Acl::ROLE_MEMBER]);
        $this->loginUser($user);

        $identities = Episciences_Auth::getAllIdentities();
        self::assertCount(1, $identities);
        self::assertSame($user, $identities[0]);
    }

    public function testGetAllIdentitiesAfterSaveRealIdentity(): void
    {
        $admin = $this->createMockUser(99, [Episciences_Acl::ROLE_ADMIN]);
        $this->loginUser($admin);
        Episciences_Auth::saveRealIdentity();

        $identities = Episciences_Auth::getAllIdentities();
        self::assertCount(1, $identities);
        self::assertSame($admin, $identities[0]);
    }

    // ==================== isAllowedToDeclareConflict ====================

    public function testIsAllowedToDeclareConflictForRoot(): void
    {
        $root = $this->createMockUser(1, [Episciences_Acl::ROLE_ROOT]);
        $this->loginUser($root);

        // Root has no direct editorial role — conflict declaration requires an explicit editorial role
        self::assertFalse(Episciences_Auth::isAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictRootImpersonatingAuthor(): void
    {
        $root = $this->createMockUser(1, [Episciences_Acl::ROLE_ROOT]);
        $author = $this->createMockUser(42, [Episciences_Acl::ROLE_AUTHOR]);

        $this->loginUser($root);
        Episciences_Auth::saveRealIdentity();
        $this->loginUser($author);

        // Root impersonating an author: suUser is root → inner check skipped → false
        self::assertFalse(Episciences_Auth::isAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictSecretaryImpersonatingAuthorGrantsAccess(): void
    {
        // Secretary (non-root) saves real identity then impersonates an author
        $secretary = $this->createMockUser(99, [Episciences_Acl::ROLE_SECRETARY]);
        $author = $this->createMockUser(42, [Episciences_Acl::ROLE_AUTHOR]);

        $this->loginUser($secretary);
        Episciences_Auth::saveRealIdentity();
        $this->loginUser($author);

        // Current identity (author) has no editorial roles, but suUser (secretary) does → true
        self::assertTrue(Episciences_Auth::isAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForNormalUser(): void
    {
        $author = $this->createMockUser(42, [Episciences_Acl::ROLE_AUTHOR]);
        $this->loginUser($author);

        self::assertFalse(Episciences_Auth::isAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForCopyEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForChiefEditor(): void
    {
        $user = $this->createMockUser(1, [Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->loginUser($user);

        self::assertTrue(Episciences_Auth::isAllowedToDeclareConflict());
    }

    // ==================== Photo version ====================

    public function testGetPhotoVersionAsHashIsString(): void
    {
        $hash = Episciences_Auth::getPhotoVersionAsHash(0);
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $hash);
    }

    public function testGetPhotoVersionAsHashDiffersWithVersion(): void
    {
        $h1 = Episciences_Auth::getPhotoVersionAsHash(0);
        $h2 = Episciences_Auth::getPhotoVersionAsHash(1);
        self::assertNotSame($h1, $h2);
    }

    public function testGetPhotoVersionWhenNotLoggedReturnsHash(): void
    {
        $version = Episciences_Auth::getPhotoVersion();
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $version);
    }

    public function testIncrementPhotoVersionIncrementsCounter(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->photoVersion = 3;

        Episciences_Auth::incrementPhotoVersion();

        self::assertSame(4, $session->photoVersion);
    }

    public function testIncrementPhotoVersionInitializesWhenMissing(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->photoVersion);

        Episciences_Auth::incrementPhotoVersion();

        self::assertSame(1, $session->photoVersion);
    }

    // ==================== Session attachments path ====================

    public function testSetCurrentAttachmentsPathInSession(): void
    {
        $session = Episciences_Auth::setCurrentAttachmentsPathInSession('/tmp/uploads/abc');

        self::assertSame('/tmp/uploads/abc', $session->currentAttachmentsPath);
    }

    public function testResetCurrentAttachmentsPath(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->currentAttachmentsPath = '/tmp/uploads/abc';

        Episciences_Auth::resetCurrentAttachmentsPath();

        self::assertFalse(isset($session->currentAttachmentsPath));
    }
}