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
 * saveUserRoles() keeps the member role when a save leaves the user without any role in the journal.
 *
 * @covers \Episciences_User::saveUserRoles
 */
final class Episciences_UserSaveUserRolesMemberFallbackTest extends TestCase
{
    /** @var Zend_Db_Adapter_Abstract&MockObject */
    private Zend_Db_Adapter_Abstract $db;

    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $this->db->method('select')->willReturnCallback(fn() => new Zend_Db_Select($this->db));
        $this->db->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $this->db->method('fetchCol')->willReturn([]);
        $this->db->method('query')->willReturnCallback(function (string $sql) {
            $this->queries[] = $sql;
            return null;
        });
    }

    /** @param list<string> $roles */
    private function saveRoles(array $roles, bool $hasRemainingRole): void
    {
        $this->db->method('fetchOne')->willReturn($hasRemainingRole ? Episciences_Acl::ROLE_EDITOR : false);

        $user = new Episciences_User();
        (new ReflectionProperty(Episciences_User::class, '_db'))->setValue($user, $this->db);

        self::assertTrue($user->saveUserRoles(1, $roles, 1));
    }

    /** @return list<string> */
    private function roleInserts(): array
    {
        return array_values(array_filter($this->queries, static fn(string $sql) => str_contains($sql, 'INSERT IGNORE')));
    }

    public function testMemberIsAddedWhenNoRoleIsLeft(): void
    {
        $this->saveRoles([], false);

        self::assertSame(["INSERT IGNORE INTO USER_ROLES (UID, RVID, ROLEID) VALUES (1,1,'member')"], $this->roleInserts());
    }

    public function testMemberIsNotAddedWhenAnotherRoleIsLeft(): void
    {
        $this->saveRoles([], true);

        self::assertSame([], $this->roleInserts());
    }

    public function testSubmittedRolesAreInsertedWithoutMember(): void
    {
        $this->saveRoles([Episciences_Acl::ROLE_REVIEWER], false);

        self::assertSame(["INSERT IGNORE INTO USER_ROLES (UID, RVID, ROLEID) VALUES (1,1,'reviewer')"], $this->roleInserts());
    }
}
