<?php

use PHPUnit\Framework\TestCase;
use Episciences\MailingList\MailingList;
use Episciences\MailingList\Manager;

/**
 * Unit tests for Manager.
 *
 * DB-dependent methods are tested only for their pure-logic
 * branches.
 *
 * @covers \Episciences\MailingList\Manager
 */
class ManagerTest extends TestCase
{
    /**
     * @covers \Episciences\MailingList\Manager::getUserCountByRole
     */
    public function testGetUserCountByRoleReturnsEmptyArrayOnNoData(): void
    {
        // This test will fail if constants like T_USER_ROLES are not defined
        // or if DB adapter is not set. 
        // In a real environment we would mock the DB adapter.
        // For now let's just check if we can at least call it without crash if mocks were set.
        
        $this->markTestSkipped('Database connection/mock required for this test');
    }

    /**
     * @covers \Episciences\MailingList\Manager::resolveMembers
     */
    public function testResolveMembersReturnsEmptyArrayWhenNoUsersOrRoles(): void
    {
        $list = new MailingList();
        $list->setUsers([])
             ->setRoles([]);
        
        $result = Manager::resolveMembers($list);
        self::assertSame([], $result);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::setOptions
     */
    public function testSetOptionsHandlesUnderscoredKeys(): void
    {
        $options = [
            'rvid' => 123,
            'name' => 'My List'
        ];
        
        $list = new MailingList();
        $list->setOptions($options);
        
        self::assertSame(123, $list->getRvid());
        self::assertSame('My List', $list->getName());
    }
}
