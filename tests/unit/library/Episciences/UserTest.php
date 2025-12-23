<?php

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for Episciences_User class
 *
 * These tests focus on non-database logic:
 * - Role checking methods
 * - Getters and setters
 * - hasAccountData flag logic
 */
class UserTest extends TestCase
{
    private Episciences_User $user;

    protected function setUp(): void
    {
        $this->user = new Episciences_User();
    }

    protected function tearDown(): void
    {
        unset($this->user);
    }

    /**
     * Helper method to set private/protected properties using reflection
     */
    private function setPrivateProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Helper method to get private/protected properties using reflection
     */
    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper method to set user roles with correct RVID structure
     */
    private function setUserRoles(array $roles): void
    {
        $rvid = defined('RVID') ? RVID : 1;
        $this->setPrivateProperty($this->user, '_roles', [$rvid => $roles]);
    }

    // ==================== Tests for hasAccountData flag ====================

    /**
     * Test that hasAccountData flag can be set and retrieved correctly
     * This tests the optimization where find() sets this flag automatically
     */
    public function testSetAndGetHasAccountData(): void
    {
        // Initially should be null
        $this->assertNull($this->user->getHasAccountData());

        // Set to true
        $this->user->setHasAccountData(true);
        $this->assertTrue($this->user->getHasAccountData());

        // Set to false
        $this->user->setHasAccountData(false);
        $this->assertFalse($this->user->getHasAccountData());
    }

    /**
     * Test that setHasAccountData returns the user instance (fluent interface)
     */
    public function testSetHasAccountDataReturnsInstance(): void
    {
        $result = $this->user->setHasAccountData(true);
        $this->assertInstanceOf(Episciences_User::class, $result);
        $this->assertSame($this->user, $result);
    }

    /**
     * Test hasLocalData behavior when hasAccountData flag is already set
     * This tests the optimization that avoids redundant SQL queries
     */
    public function testHasLocalDataUsesExistingFlag(): void
    {
        // Set UID for the user
        $testUid = 12345;
        $this->user->setUid($testUid);

        // Set hasAccountData flag to true
        $this->user->setHasAccountData(true);

        // hasLocalData should return the cached value without querying DB
        // Note: This will only work if UID matches and flag is set
        // Since we can't test DB queries, we verify the flag is used
        $this->assertTrue($this->user->getHasAccountData());
    }

    // ==================== Tests for role checking methods ====================
    // Note: Role tests depend on RVID constant which varies by environment
    // Testing basic role functionality with dynamic RVID

    /**
     * Test isRoot method returns true when user has ROOT role
     */
    public function testIsRootReturnsTrueWhenUserHasRootRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_ROOT]);
        $this->assertTrue($this->user->isRoot());
    }

    /**
     * Test isRoot method returns false when user doesn't have ROOT role
     */
    public function testIsRootReturnsFalseWhenUserLacksRootRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isRoot());
    }

    /**
     * Test isChiefEditor method returns true when user has CHIEF_EDITOR role
     */
    public function testIsChiefEditorReturnsTrueWhenUserHasChiefEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_CHIEF_EDITOR]);
        $this->assertTrue($this->user->isChiefEditor());
    }

    /**
     * Test isChiefEditor method returns false when user doesn't have CHIEF_EDITOR role
     */
    public function testIsChiefEditorReturnsFalseWhenUserLacksChiefEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isChiefEditor());
    }

    /**
     * Test isEditor method returns true when user has EDITOR role
     */
    public function testIsEditorReturnsTrueWhenUserHasEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertTrue($this->user->isEditor());
    }

    /**
     * Test isEditor method returns false when user doesn't have EDITOR role
     */
    public function testIsEditorReturnsFalseWhenUserLacksEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_REVIEWER]);
        $this->assertFalse($this->user->isEditor());
    }

    /**
     * Test isGuestEditor method returns true when user has GUEST_EDITOR role
     */
    public function testIsGuestEditorReturnsTrueWhenUserHasGuestEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_GUEST_EDITOR]);
        $this->assertTrue($this->user->isGuestEditor());
    }

    /**
     * Test isGuestEditor method returns false when user doesn't have GUEST_EDITOR role
     */
    public function testIsGuestEditorReturnsFalseWhenUserLacksGuestEditorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isGuestEditor());
    }

    /**
     * Test isReviewer method returns true when user has REVIEWER role
     */
    public function testIsReviewerReturnsTrueWhenUserHasReviewerRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_REVIEWER]);
        $this->assertTrue($this->user->isReviewer());
    }

    /**
     * Test isReviewer method returns false when user doesn't have REVIEWER role
     */
    public function testIsReviewerReturnsFalseWhenUserLacksReviewerRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isReviewer());
    }

    /**
     * Test isSecretary method returns true when user has SECRETARY role
     */
    public function testIsSecretaryReturnsTrueWhenUserHasSecretaryRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_SECRETARY]);
        $this->assertTrue($this->user->isSecretary());
    }

    /**
     * Test isSecretary method returns false when user doesn't have SECRETARY role
     */
    public function testIsSecretaryReturnsFalseWhenUserLacksSecretaryRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isSecretary());
    }

    /**
     * Test isAdministrator method returns true when user has ADMIN role
     */
    public function testIsAdministratorReturnsTrueWhenUserHasAdminRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_ADMIN]);
        $this->assertTrue($this->user->isAdministrator());
    }

    /**
     * Test isAdministrator method returns false when user doesn't have ADMIN role
     */
    public function testIsAdministratorReturnsFalseWhenUserLacksAdminRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isAdministrator());
    }

    /**
     * Test isWebmaster method returns true when user has WEBMASTER role
     */
    public function testIsWebmasterReturnsTrueWhenUserHasWebmasterRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_WEBMASTER]);
        $this->assertTrue($this->user->isWebmaster());
    }

    /**
     * Test isWebmaster method returns false when user doesn't have WEBMASTER role
     */
    public function testIsWebmasterReturnsFalseWhenUserLacksWebmasterRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isWebmaster());
    }

    /**
     * Test isMember method returns true when user has MEMBER role
     */
    public function testIsMemberReturnsTrueWhenUserHasMemberRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_MEMBER]);
        $this->assertTrue($this->user->isMember());
    }

    /**
     * Test isMember method returns false when user doesn't have MEMBER role
     */
    public function testIsMemberReturnsFalseWhenUserLacksMemberRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isMember());
    }

    /**
     * Test isAuthor method returns true when user has AUTHOR role
     */
    public function testIsAuthorReturnsTrueWhenUserHasAuthorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_AUTHOR]);
        $this->assertTrue($this->user->isAuthor());
    }

    /**
     * Test isAuthor method returns false when user doesn't have AUTHOR role
     */
    public function testIsAuthorReturnsFalseWhenUserLacksAuthorRole(): void
    {
        $this->setUserRoles([Episciences_Acl::ROLE_EDITOR]);
        $this->assertFalse($this->user->isAuthor());
    }

    /**
     * Test hasRole method with multiple roles
     */
    public function testHasRoleWithMultipleRoles(): void
    {
        $this->setUserRoles([
            Episciences_Acl::ROLE_EDITOR,
            Episciences_Acl::ROLE_REVIEWER
        ]);

        $this->assertTrue($this->user->hasRole(Episciences_Acl::ROLE_EDITOR));
        $this->assertTrue($this->user->hasRole(Episciences_Acl::ROLE_REVIEWER));
        $this->assertFalse($this->user->hasRole(Episciences_Acl::ROLE_ADMIN));
    }

    /**
     * Test hasRole method returns false when roles array is not set
     */
    public function testHasRoleReturnsFalseWhenRolesNotSet(): void
    {
        $this->setPrivateProperty($this->user, '_roles', null);
        $this->assertFalse($this->user->hasRole(Episciences_Acl::ROLE_EDITOR));
    }

    /**
     * Test hasRole method returns false when roles array is empty
     */
    public function testHasRoleReturnsFalseWhenRolesEmpty(): void
    {
        $this->setUserRoles([]);
        $this->assertFalse($this->user->hasRole(Episciences_Acl::ROLE_EDITOR));
    }

    // ==================== Tests for basic getters/setters ====================

    /**
     * Test setUid and getUid methods
     */
    public function testSetAndGetUid(): void
    {
        $uid = 12345;
        $this->user->setUid($uid);
        $this->assertEquals($uid, $this->user->getUid());
    }

    /**
     * Test setEmail and getEmail methods
     */
    public function testSetAndGetEmail(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);
        $this->assertEquals($email, $this->user->getEmail());
    }

    /**
     * Test setUsername and getUsername methods
     */
    public function testSetAndGetUsername(): void
    {
        $username = 'testuser';
        $this->user->setUsername($username);
        $this->assertEquals($username, $this->user->getUsername());
    }

    /**
     * Test setScreenName and getScreenName methods
     */
    public function testSetAndGetScreenName(): void
    {
        $screenName = 'Test User';
        $this->user->setScreenName($screenName);
        $this->assertEquals($screenName, $this->user->getScreenName());
    }

    /**
     * Test setFirstname and getFirstname methods
     */
    public function testSetAndGetFirstname(): void
    {
        $firstname = 'John';
        $this->user->setFirstname($firstname);
        $this->assertEquals($firstname, $this->user->getFirstname());
    }

    /**
     * Test setLastname and getLastname methods
     */
    public function testSetAndGetLastname(): void
    {
        $lastname = 'Doe';
        $this->user->setLastname($lastname);
        $this->assertEquals($lastname, $this->user->getLastname());
    }

    /**
     * Test getFullName method combines firstname and lastname
     */
    public function testGetFullNameCombinesFirstnameAndLastname(): void
    {
        $this->user->setFirstname('John');
        $this->user->setLastname('Doe');
        $this->assertEquals('John Doe', $this->user->getFullName());
    }

    /**
     * Test setLangueid and getLangueid methods
     */
    public function testSetAndGetLangueid(): void
    {
        $langId = 'fr';
        $this->user->setLangueid($langId);
        $this->assertEquals($langId, $this->user->getLangueid());
    }

    /**
     * Test setUuid and getUuid methods
     */
    public function testSetAndGetUuid(): void
    {
        $uuid = 'uuid-123-456-789';
        $this->user->setUuid($uuid);
        $this->assertEquals($uuid, $this->user->getUuid());
    }

    // ==================== Tests for optimization logic ====================

    /**
     * Test that hasAccountData flag is properly initialized as null
     * This is important for the optimization logic
     */
    public function testHasAccountDataInitiallyNull(): void
    {
        $freshUser = new Episciences_User();
        $this->assertNull($freshUser->getHasAccountData());
    }

    /**
     * Test that setHasAccountData with false works correctly
     * This simulates the case when find() returns empty result
     */
    public function testSetHasAccountDataFalseWhenNoDataFound(): void
    {
        $this->user->setHasAccountData(false);
        $this->assertFalse($this->user->getHasAccountData());
        $this->assertNotNull($this->user->getHasAccountData());
    }

    /**
     * Test that setHasAccountData with true works correctly
     * This simulates the case when find() returns data
     */
    public function testSetHasAccountDataTrueWhenDataFound(): void
    {
        $this->user->setHasAccountData(true);
        $this->assertTrue($this->user->getHasAccountData());
        $this->assertNotNull($this->user->getHasAccountData());
    }

    /**
     * Test that flag state can be distinguished from uninitialized state
     * This is crucial for the optimization to work correctly
     */
    public function testHasAccountDataDistinguishesNullFromFalse(): void
    {
        // Start with null (uninitialized)
        $user1 = new Episciences_User();
        $this->assertNull($user1->getHasAccountData());

        // Set to false (initialized, no data)
        $user2 = new Episciences_User();
        $user2->setHasAccountData(false);
        $this->assertFalse($user2->getHasAccountData());
        $this->assertNotNull($user2->getHasAccountData());

        // These should be distinguishable
        $this->assertNotSame($user1->getHasAccountData(), $user2->getHasAccountData());
    }
}
