<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Configuration consistency checks for the `common` module user endpoints.
 *
 * Access control is applied at dispatch time over the union of acl.ini and the
 * navigation JSON files: the dispatcher only applies role rules to an action that
 * is declared there. The user-lookup and roles endpoints below are not part of the
 * navigation menu, so they must stay declared in acl.ini under an editorial role
 * for the configured roles to take effect.
 *
 * acl.ini is parsed as a flat INI (parse_ini_file) so the test carries no Zend
 * dependency; keys keep their literal `allow.<controller>-<action>` form within each
 * role section.
 *
 * @coversNothing
 */
final class CommonModuleAclCoverageTest extends TestCase
{
    /** @var array<string, array<string, mixed>> role => key => value */
    private array $acl = [];

    protected function setUp(): void
    {
        $parsed = parse_ini_file(APPLICATION_PATH . '/configs/acl.ini', true);
        self::assertIsArray($parsed, 'acl.ini must be a parsable INI file');
        $this->acl = $parsed;
    }

    /**
     * @return list<string> role sections in which the resource is declared
     */
    private function rolesFor(string $resource): array
    {
        $key = 'allow.' . $resource;
        $roles = [];
        foreach ($this->acl as $role => $entries) {
            if (is_array($entries) && array_key_exists($key, $entries)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    /**
     * Each user-lookup / roles endpoint must be declared in acl.ini, otherwise the
     * dispatcher applies no role rule to it.
     *
     * @dataProvider managementEndpoints
     */
    public function testEndpointIsDeclaredInAcl(string $resource): void
    {
        self::assertNotEmpty(
            $this->rolesFor($resource),
            "Resource '$resource' must be declared in acl.ini for its roles to be enforced"
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function managementEndpoints(): iterable
    {
        yield 'findusers'                   => ['user-findusers'];
        yield 'findcasusers'                => ['user-findcasusers'];
        yield 'ajaxfindusersbymail'         => ['user-ajaxfindusersbymail'];
        yield 'findusersbyfirstnameandname' => ['user-findusersbyfirstnameandname'];
        yield 'ajaxfindcasuser'             => ['user-ajaxfindcasuser'];
        yield 'getmails'                    => ['user-getmails'];
        yield 'rolesform'                   => ['user-rolesform'];
    }

    /**
     * These endpoints are editorial helpers; they must not be opened to the guest or
     * member roles.
     *
     * @dataProvider managementEndpoints
     */
    public function testEndpointIsEditorialOnly(string $resource): void
    {
        $roles = $this->rolesFor($resource);
        self::assertNotContains('guest', $roles, "'$resource' must not be declared for guest");
        self::assertNotContains('member', $roles, "'$resource' must not be declared for member");
    }

    public function testReviewerInvitationHelpersAreGuestEditorLevel(): void
    {
        foreach (['user-findcasusers', 'user-ajaxfindusersbymail', 'user-findusersbyfirstnameandname', 'user-ajaxfindcasuser'] as $resource) {
            self::assertContains(
                'guest_editor',
                $this->rolesFor($resource),
                "'$resource' is used by the reviewer-invitation modal (guest_editor and above)"
            );
        }
    }

    public function testRolesFormSharesTheSameRoleAsSaveRoles(): void
    {
        // rolesform serves the form that posts to saveroles: same scope.
        self::assertContains('editor', $this->rolesFor('user-rolesform'));
        self::assertContains('editor', $this->rolesFor('user-saveroles'));
    }

    /**
     * XHR endpoints called by the front-end JavaScript. An XHR request on a resource that is
     * absent from the ACL is denied by Episciences_Auth_Plugin, so each one must be declared.
     *
     * @dataProvider xhrEndpoints
     * @param list<string> $expectedRoles roles that must declare the resource
     */
    public function testXhrEndpointIsDeclaredForItsRoles(string $resource, array $expectedRoles): void
    {
        $roles = $this->rolesFor($resource);
        foreach ($expectedRoles as $role) {
            self::assertContains($role, $roles, "'$resource' must be declared for '$role' in acl.ini");
        }
        self::assertNotContains('guest', $roles, "'$resource' must not be declared for guest");
    }

    public function testUserUnsuIsDeclaredUnderMember(): void
    {
        $roles = $this->rolesFor('user-unsu');
        self::assertContains('member', $roles, "'user-unsu' must be declared for 'member' in acl.ini");
        self::assertNotContains('guest', $roles, "'user-unsu' must not be declared for 'guest'");
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function xhrEndpoints(): iterable
    {
        yield 'user photo deletion'  => ['user-ajaxdeletephoto', ['member']];
        yield 'website menu add'     => ['website-ajaxformpage', ['webmaster', 'secretary']];
        yield 'website menu order'   => ['website-ajaxorder', ['webmaster', 'secretary']];
        yield 'website menu removal' => ['website-ajaxrmpage', ['webmaster', 'secretary']];
    }
}
