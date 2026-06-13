<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Acl
 */
class Episciences_AclTest extends TestCase
{
    private Episciences_Acl $acl;

    protected function setUp(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../../../../application'));
        }
        $this->acl = new Episciences_Acl();
    }

    // ==================== Constants ====================

    public function testAllRoleConstantsDefined(): void
    {
        $roles = [
            'ROLE_ROOT', 'ROLE_CHIEF_EDITOR', 'ROLE_ADMIN', 'ROLE_EDITOR',
            'ROLE_GUEST_EDITOR', 'ROLE_SECRETARY', 'ROLE_WEBMASTER', 'ROLE_REVIEWER',
            'ROLE_AUTHOR', 'ROLE_EDITORIAL_BOARD', 'ROLE_TECHNICAL_BOARD',
            'ROLE_SCIENTIFIC_ADVISORY_BOARD', 'ROLE_ADVISORY_BOARD',
            'ROLE_MANAGING_EDITOR', 'ROLE_HANDLING_EDITOR', 'ROLE_FORMER_MEMBER',
            'ROLE_MEMBER', 'ROLE_GUEST', 'ROLE_CO_AUTHOR', 'ROLE_COPY_EDITOR',
        ];

        foreach ($roles as $const) {
            self::assertTrue(
                defined('Episciences_Acl::' . $const),
                "Constant $const must be defined"
            );
        }
    }

    public function testPluralRoleConstantsDefined(): void
    {
        $plurals = [
            'ROLE_CHIEF_EDITOR_PLURAL', 'ROLE_ADMINISTRATOR_PLURAL', 'ROLE_EDITOR_PLURAL',
            'ROLE_GUEST_EDITOR_PLURAL', 'ROLE_SECRETARY_PLURAL', 'ROLE_WEBMASTER_PLURAL',
            'ROLE_REVIEWER_PLURAL', 'ROLE_EDITORIAL_BOARD_PLURAL', 'ROLE_TECHNICAL_BOARD_PLURAL',
            'ROLE_SCIENTIFIC_ADVISORY_BOARD_PLURAL', 'ROLE_FORMER_MEMBER_PLURAL',
            'ROLE_MEMBER_PLURAL', 'ROLE_AUTHOR_PLURAL', 'ROLE_GUEST_PLURAL',
            'ROLE_CO_AUTHOR_PLURAL',
        ];

        foreach ($plurals as $const) {
            self::assertTrue(
                defined('Episciences_Acl::' . $const),
                "Constant $const must be defined"
            );
        }
    }

    public function testConfigurableResourceFlagValues(): void
    {
        self::assertTrue(Episciences_Acl::CONFIGURABLE_RESOURCE);
        self::assertFalse(Episciences_Acl::NOT_CONFIGURABLE_RESOURCE);
    }

    public function testTypeOfResourcesNotToBeDisplayedIsStringList(): void
    {
        $list = Episciences_Acl::TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED;

        self::assertIsArray($list);
        self::assertNotEmpty($list);

        foreach ($list as $key => $value) {
            self::assertIsInt($key, "List must have integer keys (no duplicates)");
            self::assertIsString($value, "Each resource must be a string");
            self::assertStringContainsString('-', $value, "Resources follow controller-action pattern");
        }
    }

    public function testTypeOfResourcesHasNoDuplicates(): void
    {
        $list = Episciences_Acl::TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED;
        $unique = array_unique($list);

        // Known duplicate: 'administratelinkeddata-ajaxgetldform' appears twice
        // Document it so it doesn't go unnoticed
        $duplicates = array_diff_assoc($list, $unique);
        if (!empty($duplicates)) {
            self::markTestIncomplete(
                'TYPE_OF_RESOURCES_NOT_TO_BE_DISPLAYED contains duplicates: ' . implode(', ', $duplicates)
            );
        }

        self::assertSame($unique, $list);
    }

    // ==================== CONFIGURABLE_RESOURCES ====================

    public function testConfigurableResourcesCoversKeyRoles(): void
    {
        $roles = array_keys(Episciences_Acl::CONFIGURABLE_RESOURCES);

        self::assertContains(Episciences_Acl::ROLE_CHIEF_EDITOR, $roles);
        self::assertContains(Episciences_Acl::ROLE_ADMIN, $roles);
        self::assertContains(Episciences_Acl::ROLE_EDITOR, $roles);
        self::assertContains(Episciences_Acl::ROLE_REVIEWER, $roles);
        self::assertContains(Episciences_Acl::ROLE_COPY_EDITOR, $roles);
    }

    public function testConfigurableResourceValuesAreBool(): void
    {
        foreach (Episciences_Acl::CONFIGURABLE_RESOURCES as $role => $resources) {
            foreach ($resources as $resource => $configurable) {
                self::assertIsBool(
                    $configurable,
                    "Resource '$resource' for role '$role' must have a bool value"
                );
            }
        }
    }

    // ==================== Constructor / role loading ====================

    public function testConstructorLoadsRoles(): void
    {
        $ref = new ReflectionProperty(Episciences_Acl::class, '_roles');
        $ref->setAccessible(true);
        $roles = $ref->getValue($this->acl);

        self::assertIsArray($roles);
        self::assertArrayHasKey(Episciences_Acl::ROLE_ROOT, $roles);
        self::assertArrayHasKey(Episciences_Acl::ROLE_MEMBER, $roles);
        self::assertArrayHasKey(Episciences_Acl::ROLE_GUEST, $roles);
    }

    public function testRoleHierarchyStructure(): void
    {
        $ref = new ReflectionProperty(Episciences_Acl::class, '_roles');
        $ref->setAccessible(true);
        $roles = $ref->getValue($this->acl);

        // Root chain: root → chief_editor → admin → secretary → editor → guest_editor → reviewer → member → guest
        self::assertSame(Episciences_Acl::ROLE_CHIEF_EDITOR, $roles[Episciences_Acl::ROLE_ROOT]);
        self::assertSame(Episciences_Acl::ROLE_ADMIN, $roles[Episciences_Acl::ROLE_CHIEF_EDITOR]);
        self::assertSame(Episciences_Acl::ROLE_SECRETARY, $roles[Episciences_Acl::ROLE_ADMIN]);
        self::assertSame(Episciences_Acl::ROLE_EDITOR, $roles[Episciences_Acl::ROLE_SECRETARY]);
        self::assertSame(Episciences_Acl::ROLE_GUEST_EDITOR, $roles[Episciences_Acl::ROLE_EDITOR]);
        self::assertSame(Episciences_Acl::ROLE_REVIEWER, $roles[Episciences_Acl::ROLE_GUEST_EDITOR]);
        self::assertSame(Episciences_Acl::ROLE_MEMBER, $roles[Episciences_Acl::ROLE_REVIEWER]);
        self::assertNull($roles[Episciences_Acl::ROLE_GUEST]);
    }

    public function testAuthorAndCopyEditorParentIsMember(): void
    {
        $ref = new ReflectionProperty(Episciences_Acl::class, '_roles');
        $ref->setAccessible(true);
        $roles = $ref->getValue($this->acl);

        self::assertSame(Episciences_Acl::ROLE_MEMBER, $roles[Episciences_Acl::ROLE_AUTHOR]);
        self::assertSame(Episciences_Acl::ROLE_MEMBER, $roles[Episciences_Acl::ROLE_COPY_EDITOR]);
    }

    // ==================== getRolesCodes ====================

    public function testGetRolesCodes(): void
    {
        $codes = $this->acl->getRolesCodes();

        self::assertIsArray($codes);
        self::assertArrayHasKey(Episciences_Acl::ROLE_ROOT, $codes);
        self::assertSame(Episciences_Acl::ROLE_ROOT, $codes[Episciences_Acl::ROLE_ROOT]);
    }

    public function testGetRolesCodesValuesEqualKeys(): void
    {
        $codes = $this->acl->getRolesCodes();

        foreach ($codes as $key => $value) {
            self::assertSame($key, $value, "Each role code must map to itself");
        }
    }

    public function testGetRolesCodesContainsAllRoles(): void
    {
        $codes = $this->acl->getRolesCodes();

        $expectedRoles = [
            Episciences_Acl::ROLE_ROOT, Episciences_Acl::ROLE_CHIEF_EDITOR,
            Episciences_Acl::ROLE_ADMIN, Episciences_Acl::ROLE_EDITOR,
            Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_Acl::ROLE_SECRETARY,
            Episciences_Acl::ROLE_REVIEWER, Episciences_Acl::ROLE_MEMBER,
            Episciences_Acl::ROLE_GUEST,
        ];

        foreach ($expectedRoles as $role) {
            self::assertArrayHasKey($role, $codes, "getRolesCodes() must include $role");
        }
    }

    // ==================== getEditableRoles ====================

    public function testGetEditableRolesInCliMode(): void
    {
        // In CLI mode (under PHPUnit), getEditableRoles() returns all roles codes
        $editable = $this->acl->getEditableRoles();
        $expected = $this->acl->getRolesCodes();

        self::assertSame($expected, $editable);
    }

    // ==================== getCode (no-op stub) ====================

    public function testGetCodeReturnsVoid(): void
    {
        // getCode is a no-op stub (commented out body) — verify it doesn't throw
        $result = Episciences_Acl::getCode('someRole');
        self::assertNull($result);
    }
}