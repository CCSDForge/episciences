<?php

namespace unit\library\Episciences\user;

use Episciences_Acl;
use Episciences_User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User
 *
 * Focuses on pure logic: setters/getters, role checks, alias handling.
 * DB-dependent methods (find, save, loadRoles, etc.) are not tested here.
 *
 * @covers Episciences_User
 */
class Episciences_UserTest extends TestCase
{
    private Episciences_User $user;

    protected function setUp(): void
    {
        $this->user = new Episciences_User();
    }

    // -------------------------------------------------------------------------
    // screenName
    // -------------------------------------------------------------------------

    public function testSetAndGetScreenName(): void
    {
        $this->user->setScreenName('John Doe');
        $this->assertSame('John Doe', $this->user->getScreenName());
    }

    public function testSetScreenNameReplacesSlashWithSpace(): void
    {
        $this->user->setScreenName('John/Doe');
        $this->assertSame('John Doe', $this->user->getScreenName());
    }

    public function testSetScreenNameTrimsSlashesOnBothSides(): void
    {
        $this->user->setScreenName('A/B/C');
        $this->assertSame('A B C', $this->user->getScreenName());
    }

    // -------------------------------------------------------------------------
    // langueid
    // -------------------------------------------------------------------------

    public function testSetAndGetLangueid(): void
    {
        $this->user->setLangueid('fr');
        $this->assertSame('fr', $this->user->getLangueid());
    }

    public function testGetLangueidReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->user->getLangueid());
    }

    public function testSetLangueidReturnsFluent(): void
    {
        $result = $this->user->setLangueid('en');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // socialMedias
    // -------------------------------------------------------------------------

    public function testSetAndGetSocialMedias(): void
    {
        $this->user->setSocialMedias('https://twitter.com/test');
        $this->assertSame('https://twitter.com/test', $this->user->getSocialMedias());
    }

    public function testSetSocialMediasTrimsWhitespace(): void
    {
        $this->user->setSocialMedias('  https://twitter.com/test  ');
        $this->assertSame('https://twitter.com/test', $this->user->getSocialMedias());
    }

    public function testSetSocialMediasWithNullDoesNotOverrideExistingValue(): void
    {
        $this->user->setSocialMedias('https://twitter.com/test');
        $this->user->setSocialMedias(null);
        $this->assertSame('https://twitter.com/test', $this->user->getSocialMedias());
    }

    public function testGetSocialMediasReturnsNullByDefault(): void
    {
        $this->assertNull($this->user->getSocialMedias());
    }

    public function testSetSocialMediasReturnsFluent(): void
    {
        $result = $this->user->setSocialMedias('https://twitter.com/test');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // webSites
    // -------------------------------------------------------------------------

    public function testSetAndGetWebSites(): void
    {
        $sites = ['https://example.com', 'https://example.org'];
        $this->user->setWebSites($sites);
        $result = $this->user->getWebSites();
        $this->assertContains('https://example.com', $result);
        $this->assertContains('https://example.org', $result);
    }

    public function testSetWebSitesFiltersInvalidUrls(): void
    {
        $sites = ['https://valid.com', 'not-a-valid-url', 'also invalid'];
        $this->user->setWebSites($sites);
        $result = $this->user->getWebSites();
        $this->assertContains('https://valid.com', $result);
        $this->assertNotContains('not-a-valid-url', $result);
        $this->assertNotContains('also invalid', $result);
    }

    public function testSetWebSitesWithNullSetsNullValue(): void
    {
        $this->user->setWebSites(null);
        $this->assertNull($this->user->getWebSites());
    }

    public function testGetWebSitesReturnsNullByDefault(): void
    {
        $this->assertNull($this->user->getWebSites());
    }

    public function testSetWebSitesReturnsFluent(): void
    {
        $result = $this->user->setWebSites(['https://example.com']);
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // orcid
    // -------------------------------------------------------------------------

    public function testSetAndGetOrcid(): void
    {
        $this->user->setOrcid('0000-0002-9193-9560');
        $this->assertSame('0000-0002-9193-9560', $this->user->getOrcid());
    }

    public function testGetOrcidReturnsNullByDefault(): void
    {
        $this->assertNull($this->user->getOrcid());
    }

    public function testSetOrcidWithNull(): void
    {
        $this->user->setOrcid('0000-0002-9193-9560');
        $this->user->setOrcid(null);
        $this->assertNull($this->user->getOrcid());
    }

    public function testSetOrcidReturnsFluent(): void
    {
        $result = $this->user->setOrcid('0000-0001-2345-6789');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // affiliations
    // -------------------------------------------------------------------------

    public function testSetAndGetAffiliations(): void
    {
        $affiliations = [['name' => 'CNRS', 'country' => 'FR']];
        $this->user->setAffiliations($affiliations);
        $this->assertSame($affiliations, $this->user->getAffiliations());
    }

    public function testGetAffiliationsReturnsNullByDefault(): void
    {
        $this->assertNull($this->user->getAffiliations());
    }

    public function testSetAffiliationsWithNull(): void
    {
        $this->user->setAffiliations(['some' => 'data']);
        $this->user->setAffiliations(null);
        $this->assertNull($this->user->getAffiliations());
    }

    public function testSetAffiliationsReturnsFluent(): void
    {
        $result = $this->user->setAffiliations([]);
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // biography
    // -------------------------------------------------------------------------

    public function testSetAndGetBiography(): void
    {
        $this->user->setBiography('A researcher in computer science.');
        $this->assertSame('A researcher in computer science.', $this->user->getBiography());
    }

    public function testGetBiographyReturnsNullByDefault(): void
    {
        $this->assertNull($this->user->getBiography());
    }

    public function testSetBiographyWithNull(): void
    {
        $this->user->setBiography('Some bio');
        $this->user->setBiography(null);
        $this->assertNull($this->user->getBiography());
    }

    public function testSetBiographyReturnsFluent(): void
    {
        $result = $this->user->setBiography('Bio text');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // apiPassword
    // -------------------------------------------------------------------------

    public function testSetAndGetApiPassword(): void
    {
        $this->user->setApiPassword('hashed_password_xyz');
        $this->assertSame('hashed_password_xyz', $this->user->getApiPassword());
    }

    public function testSetApiPasswordReturnsFluent(): void
    {
        $result = $this->user->setApiPassword('hash');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // is_valid
    // -------------------------------------------------------------------------

    public function testDefaultIsValidIsOne(): void
    {
        $this->assertSame(1, $this->user->getIs_valid());
    }

    public function testSetIsValidToZero(): void
    {
        $this->user->setIs_valid(0);
        $this->assertSame(0, $this->user->getIs_valid());
    }

    public function testSetIsValidReturnsFluent(): void
    {
        $result = $this->user->setIs_valid(1);
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // registrationDate
    // -------------------------------------------------------------------------

    public function testSetRegistrationDateWithExplicitValue(): void
    {
        $this->user->setRegistrationDate('2024-01-15 10:00:00');
        $this->assertSame('2024-01-15 10:00:00', $this->user->getRegistrationDate());
    }

    public function testSetRegistrationDateWithNullSetsCurrentDateTime(): void
    {
        $this->user->setRegistrationDate(null);
        $date = $this->user->getRegistrationDate();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    public function testSetRegistrationDateReturnsFluent(): void
    {
        $result = $this->user->setRegistrationDate('2024-01-01 00:00:00');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // modificationDate
    // -------------------------------------------------------------------------

    public function testSetModificationDateWithExplicitValue(): void
    {
        $this->user->setModificationDate('2024-06-01 12:00:00');
        $this->assertSame('2024-06-01 12:00:00', $this->user->getModificationDate());
    }

    public function testSetModificationDateWithNullSetsCurrentDateTime(): void
    {
        $this->user->setModificationDate(null);
        $date = $this->user->getModificationDate();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }

    public function testSetModificationDateReturnsFluent(): void
    {
        $result = $this->user->setModificationDate('2024-01-01 00:00:00');
        $this->assertInstanceOf(Episciences_User::class, $result);
    }

    // -------------------------------------------------------------------------
    // hasAccountData
    // -------------------------------------------------------------------------

    public function testSetHasAccountDataToTrue(): void
    {
        $this->user->setHasAccountData(true);
        $this->assertTrue($this->user->getHasAccountData());
    }

    public function testSetHasAccountDataToFalse(): void
    {
        $this->user->setHasAccountData(false);
        $this->assertFalse($this->user->getHasAccountData());
    }

    public function testSetHasAccountDataThrowsOnStringArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line */
        $this->user->setHasAccountData('true');
    }

    public function testSetHasAccountDataThrowsOnIntArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line */
        $this->user->setHasAccountData(1);
    }

    // -------------------------------------------------------------------------
    // Role checks — pre-set roles to avoid DB access
    // -------------------------------------------------------------------------

    public function testHasRoleReturnsTrueForExistingRole(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_EDITOR]]);
        $this->assertTrue($this->user->hasRole(Episciences_Acl::ROLE_EDITOR));
    }

    public function testHasRoleReturnsFalseForNonExistentRole(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_MEMBER]]);
        $this->assertFalse($this->user->hasRole(Episciences_Acl::ROLE_EDITOR));
    }

    public function testHasRoleReturnsFalseWhenRolesArrayIsEmpty(): void
    {
        $this->user->setRoles([]);
        $this->assertFalse($this->user->hasRole(Episciences_Acl::ROLE_MEMBER));
    }

    public function testIsEditor(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_EDITOR]]);
        $this->assertTrue($this->user->isEditor());
        $this->assertFalse($this->user->isReviewer());
    }

    public function testIsReviewer(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_REVIEWER]]);
        $this->assertTrue($this->user->isReviewer());
        $this->assertFalse($this->user->isEditor());
    }

    public function testIsChiefEditor(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_CHIEF_EDITOR]]);
        $this->assertTrue($this->user->isChiefEditor());
    }

    public function testIsMember(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_MEMBER]]);
        $this->assertTrue($this->user->isMember());
    }

    public function testIsAdministrator(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_ADMIN]]);
        $this->assertTrue($this->user->isAdministrator());
    }

    public function testIsRoot(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_ROOT]]);
        $this->assertTrue($this->user->isRoot());
    }

    public function testIsAuthor(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_AUTHOR]]);
        $this->assertTrue($this->user->isAuthor());
    }

    public function testIsCopyEditor(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_COPY_EDITOR]]);
        $this->assertTrue($this->user->isCopyEditor());
    }

    public function testIsSecretary(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_SECRETARY]]);
        $this->assertTrue($this->user->isSecretary());
    }

    public function testIsWebmaster(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_WEBMASTER]]);
        $this->assertTrue($this->user->isWebmaster());
    }

    public function testIsGuestEditor(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_GUEST_EDITOR]]);
        $this->assertTrue($this->user->isGuestEditor());
    }

    public function testUserWithMultipleRoles(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_EDITOR,
            Episciences_Acl::ROLE_REVIEWER,
        ]]);
        $this->assertTrue($this->user->isEditor());
        $this->assertTrue($this->user->isReviewer());
        $this->assertFalse($this->user->isAdministrator());
    }

    // -------------------------------------------------------------------------
    // hasOnlyAdministratorRole
    // -------------------------------------------------------------------------

    public function testHasOnlyAdministratorRoleWithSingleAdminRole(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_ADMIN]]);
        $this->assertTrue($this->user->hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleWithAdminAndEditor(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ADMIN,
            Episciences_Acl::ROLE_EDITOR,
        ]]);
        $this->assertFalse($this->user->hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleWithAdminAndChiefEditor(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ADMIN,
            Episciences_Acl::ROLE_CHIEF_EDITOR,
        ]]);
        $this->assertFalse($this->user->hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleWithAdminAndSecretary(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ADMIN,
            Episciences_Acl::ROLE_SECRETARY,
        ]]);
        $this->assertFalse($this->user->hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleWithAdminAndCopyEditor(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ADMIN,
            Episciences_Acl::ROLE_COPY_EDITOR,
        ]]);
        $this->assertFalse($this->user->hasOnlyAdministratorRole());
    }

    public function testHasOnlyAdministratorRoleForNonAdmin(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_EDITOR]]);
        $this->assertFalse($this->user->hasOnlyAdministratorRole());
    }

    // -------------------------------------------------------------------------
    // isNotAllowedToDeclareConflict
    // -------------------------------------------------------------------------

    public function testIsNotAllowedToDeclareConflictForRootOnly(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_ROOT]]);
        $this->assertTrue($this->user->isNotAllowedToDeclareConflict());
    }

    public function testIsNotAllowedToDeclareConflictForAdminOnly(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_ADMIN]]);
        $this->assertTrue($this->user->isNotAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForRootWithEditorRole(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ROOT,
            Episciences_Acl::ROLE_EDITOR,
        ]]);
        $this->assertFalse($this->user->isNotAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForAdminWithChiefEditorRole(): void
    {
        $this->user->setRoles([RVID => [
            Episciences_Acl::ROLE_ADMIN,
            Episciences_Acl::ROLE_CHIEF_EDITOR,
        ]]);
        $this->assertFalse($this->user->isNotAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForEditorAlone(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_EDITOR]]);
        $this->assertFalse($this->user->isNotAllowedToDeclareConflict());
    }

    public function testIsAllowedToDeclareConflictForReviewer(): void
    {
        $this->user->setRoles([RVID => [Episciences_Acl::ROLE_REVIEWER]]);
        $this->assertFalse($this->user->isNotAllowedToDeclareConflict());
    }

    // -------------------------------------------------------------------------
    // getAllRoles
    // -------------------------------------------------------------------------

    public function testGetAllRolesWhenRolesAlreadySet(): void
    {
        $roles = [RVID => [Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_MEMBER]];
        $this->user->setRoles($roles);
        $this->assertSame($roles, $this->user->getAllRoles());
    }

    // -------------------------------------------------------------------------
    // Alias management (no DB needed when pre-setting aliases)
    // -------------------------------------------------------------------------

    public function testSetAndGetAlias(): void
    {
        $this->user->setAlias(42, 7);
        $this->assertSame(7, $this->user->getAlias(42));
    }

    public function testGetAliasReturnsNullForUnknownDocId(): void
    {
        $this->user->setAliases([]);
        $this->assertNull($this->user->getAlias(999));
    }

    public function testHasAlias(): void
    {
        $this->user->setAliases([42 => 1]);
        $this->assertTrue($this->user->hasAlias(42));
    }

    public function testHasAliasReturnsFalseForUnknownDocId(): void
    {
        $this->user->setAliases([]);
        $this->assertFalse($this->user->hasAlias(999));
    }

    public function testSetAliasesAndGetAliases(): void
    {
        $aliases = [10 => 1, 20 => 2, 30 => 3];
        $this->user->setAliases($aliases);
        $this->assertSame($aliases, $this->user->getAliases());
    }

    public function testGetAliasFallsBackToAliasesArrayWhenSet(): void
    {
        $this->user->setAliases([100 => 5, 200 => 9]);
        $this->assertSame(5, $this->user->getAlias(100));
        $this->assertSame(9, $this->user->getAlias(200));
        $this->assertNull($this->user->getAlias(999));
    }

    // -------------------------------------------------------------------------
    // Constructor with options array
    // -------------------------------------------------------------------------

    public function testConstructorWithOptionsArray(): void
    {
        $user = new Episciences_User([
            'SCREEN_NAME' => 'Test User',
            'LANGUEID'    => 'en',
            'ORCID'       => '0000-0001-1234-5678',
        ]);

        $this->assertSame('Test User', $user->getScreenName());
        $this->assertSame('en', $user->getLangueid());
        $this->assertSame('0000-0001-1234-5678', $user->getOrcid());
    }
}
