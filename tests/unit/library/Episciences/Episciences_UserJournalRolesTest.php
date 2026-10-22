<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;

/**
 * The roles of a user are indexed by journal and kept in a session shared by the journals of the same host:
 * updating the roles of one journal must keep the others, and a journal without any role means "member".
 *
 * @covers \Episciences_User::getRoles
 * @covers \Episciences_User::getAllRoles
 * @covers \Episciences_User::setJournalRoles
 * @covers \Episciences_User::addRole
 * @covers \Episciences_User::fetchJournalRoles
 */
final class Episciences_UserJournalRolesTest extends TestCase
{
    private const OTHER_RVID = RVID + 1000;

    /**
     * @param array<int, list<string>> $roles
     * @param list<string> $savedRoles roles returned by the database for one journal
     */
    private function createUserWithRoles(array $roles, array $savedRoles = []): Episciences_User
    {
        $db = $this->createMock(Zend_Db_Adapter_Abstract::class);
        $db->method('select')->willReturnCallback(static fn() => new Zend_Db_Select($db));
        $db->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $db->method('fetchCol')->willReturn($savedRoles);
        $db->method('fetchOne')->willReturn(false);

        $user = new Episciences_User();
        (new ReflectionProperty(Episciences_User::class, '_db'))->setValue($user, $db);
        $user->setUid(42);
        $user->setRoles($roles);

        return $user;
    }

    public function testCurrentJournalWithoutRoleFallsBackToMember(): void
    {
        $user = $this->createUserWithRoles([self::OTHER_RVID => [Episciences_Acl::ROLE_EDITOR]]);

        self::assertSame([Episciences_Acl::ROLE_MEMBER], $user->getRoles());
        self::assertSame(
            [self::OTHER_RVID => [Episciences_Acl::ROLE_EDITOR], RVID => [Episciences_Acl::ROLE_MEMBER]],
            $user->getAllRoles()
        );
    }

    public function testOtherJournalWithoutRoleReturnsNull(): void
    {
        $user = $this->createUserWithRoles([RVID => [Episciences_Acl::ROLE_EDITOR]]);

        self::assertNull($user->getRoles(self::OTHER_RVID));
    }

    public function testSetJournalRolesKeepsTheOtherJournals(): void
    {
        $user = $this->createUserWithRoles([
            RVID => [Episciences_Acl::ROLE_MEMBER],
            self::OTHER_RVID => [Episciences_Acl::ROLE_EDITOR],
        ]);

        $user->setJournalRoles(RVID, [Episciences_Acl::ROLE_REVIEWER]);

        self::assertSame([Episciences_Acl::ROLE_REVIEWER], $user->getRoles());
        self::assertSame([Episciences_Acl::ROLE_EDITOR], $user->getRoles(self::OTHER_RVID));
    }

    public function testAddRoleKeepsTheRolesOfTheOtherJournals(): void
    {
        $user = $this->createUserWithRoles([
            RVID => [Episciences_Acl::ROLE_MEMBER],
            self::OTHER_RVID => [Episciences_Acl::ROLE_EDITOR],
        ]);

        $user->addRole(Episciences_Acl::ROLE_AUTHOR);

        self::assertSame([Episciences_Acl::ROLE_AUTHOR], array_values($user->getRoles()));
        self::assertSame([Episciences_Acl::ROLE_EDITOR], $user->getRoles(self::OTHER_RVID));
    }

    public function testAddRoleAlreadyHeldKeepsTheRolesOfTheOtherJournals(): void
    {
        $user = $this->createUserWithRoles([
            RVID => [Episciences_Acl::ROLE_AUTHOR],
            self::OTHER_RVID => [Episciences_Acl::ROLE_EDITOR],
        ]);

        $user->addRole(Episciences_Acl::ROLE_AUTHOR);

        self::assertSame([Episciences_Acl::ROLE_AUTHOR], array_values($user->getRoles()));
        self::assertSame([Episciences_Acl::ROLE_EDITOR], $user->getRoles(self::OTHER_RVID));
    }

    public function testFetchJournalRolesReturnsTheSavedRoles(): void
    {
        // The saved roles include the roles the caller may not edit (e.g. author), which are not submitted
        $user = $this->createUserWithRoles([], [Episciences_Acl::ROLE_SECRETARY, Episciences_Acl::ROLE_AUTHOR]);

        self::assertSame(
            [Episciences_Acl::ROLE_SECRETARY, Episciences_Acl::ROLE_AUTHOR],
            $user->fetchJournalRoles(42, RVID)
        );
    }

    public function testFetchJournalRolesWithoutSavedRoleFallsBackToMember(): void
    {
        $user = $this->createUserWithRoles([]);

        self::assertSame([Episciences_Acl::ROLE_MEMBER], $user->fetchJournalRoles(42, RVID));
    }
}
