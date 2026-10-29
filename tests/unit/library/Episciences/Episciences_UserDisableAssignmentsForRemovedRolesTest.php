<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;

/**
 * Removing a role disables the editor assignments only when no kept role still grants them
 * (editor and chief_editor both map to the "editor" assignment role).
 *
 * @covers \Episciences_User::saveUserRoles
 */
final class Episciences_UserDisableAssignmentsForRemovedRolesTest extends TestCase
{
    /** @var Zend_Db_Adapter_Abstract&MockObject */
    private Zend_Db_Adapter_Abstract $db;

    /** @var list<string> Fake USER_ROLES table of the user in the journal */
    private array $storedRoles = [];

    /** @var list<array<int, mixed>> */
    private array $disabledAssignmentBinds = [];

    protected function setUp(): void
    {
        $this->db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $this->db->method('select')->willReturnCallback(fn() => new Zend_Db_Select($this->db));
        $this->db->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");

        // Role reads always reflect the current state of the fake table, whatever the call order
        $this->db->method('fetchCol')->willReturnCallback(fn() => $this->storedRoles);

        $this->db->method('delete')->willReturnCallback(function (string $table, array $where) {
            $before = count($this->storedRoles);
            $this->storedRoles = array_values(array_diff($this->storedRoles, [$where['ROLEID = ?']]));
            return $before - count($this->storedRoles);
        });

        $this->db->method('query')->willReturnCallback(function (string $sql, array $bind = []) {
            if (str_contains($sql, 'INSERT IGNORE INTO')) {
                preg_match_all("/\(\d+,\d+,'([^']+)'\)/", $sql, $matches);
                $this->storedRoles = array_values(array_unique(array_merge($this->storedRoles, $matches[1])));
            } elseif (str_contains($sql, 'INSERT INTO `USER_ASSIGNMENT`')) {
                $this->disabledAssignmentBinds[] = $bind;
            }
            return null;
        });
    }

    /**
     * @param list<string> $currentRoles roles in the database before the save
     * @param list<string> $submittedRoles
     */
    private function saveRoles(array $currentRoles, array $submittedRoles): void
    {
        $this->storedRoles = $currentRoles;

        $user = new Episciences_User();
        (new ReflectionProperty(Episciences_User::class, '_db'))->setValue($user, $this->db);

        self::assertTrue($user->saveUserRoles(1, $submittedRoles, 1));
        self::assertEqualsCanonicalizing($submittedRoles, $this->storedRoles);
    }

    public function testChiefEditorRemovedWhileEditorKeptKeepsEditorAssignments(): void
    {
        $this->saveRoles(
            [Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR],
            [Episciences_Acl::ROLE_EDITOR]
        );

        self::assertSame([], $this->disabledAssignmentBinds);
    }

    public function testEditorRemovedWhileChiefEditorKeptKeepsEditorAssignments(): void
    {
        $this->saveRoles(
            [Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR],
            [Episciences_Acl::ROLE_CHIEF_EDITOR]
        );

        self::assertSame([], $this->disabledAssignmentBinds);
    }

    public function testBothEditorRolesRemovedDisablesEditorAssignmentsOnce(): void
    {
        $this->saveRoles(
            [Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_REVIEWER],
            [Episciences_Acl::ROLE_REVIEWER]
        );

        self::assertSame([[1, 1, 'editor']], $this->disabledAssignmentBinds);
    }
}
