<?php

use PHPUnit\Framework\TestCase;
use Episciences\MailingList\MailingList;

/**
 * Unit tests for MailingList.
 *
 * @covers \Episciences\MailingList\MailingList
 */
class MailingListTest extends TestCase
{
    private MailingList $list;

    protected function setUp(): void
    {
        $this->list = new MailingList();
    }

    public function testSetAndGetId(): void
    {
        $this->list->setId(123);
        self::assertSame(123, $this->list->getId());
    }

    public function testSetAndGetRvid(): void
    {
        $this->list->setRvid(456);
        self::assertSame(456, $this->list->getRvid());
    }

    public function testSetAndGetName(): void
    {
        $this->list->setName('Test List');
        self::assertSame('Test List', $this->list->getName());
    }

    public function testSetAndGetType(): void
    {
        $this->list->setType('Test Type');
        self::assertSame('Test Type', $this->list->getType());
    }

    public function testSetAndGetStatus(): void
    {
        $this->list->setStatus(0);
        self::assertSame(0, $this->list->getStatus());
    }

    public function testDefaultStatusIsOpen(): void
    {
        self::assertSame(1, $this->list->getStatus());
    }

    public function testSetAndGetUsers(): void
    {
        $users = [1, 2, 3];
        $this->list->setUsers($users);
        self::assertSame($users, $this->list->getUsers());
    }

    public function testSetAndGetRoles(): void
    {
        $roles = ['editor', 'reviewer'];
        $this->list->setRoles($roles);
        self::assertSame($roles, $this->list->getRoles());
    }

    public function testToArray(): void
    {
        $this->list->setId(1)
            ->setRvid(2)
            ->setName('Name')
            ->setType('Type')
            ->setStatus(1);

        $array = $this->list->toArray();

        self::assertSame(1, $array['id']);
        self::assertSame(2, $array['rvid']);
        self::assertSame('Name', $array['name']);
        self::assertSame('Type', $array['type']);
        self::assertSame(1, $array['status']);
    }

    public function testConstructorWithOptions(): void
    {
        $options = [
            'id' => 10,
            'rvid' => 20,
            'name' => 'Name',
            'type' => 'Type',
            'status' => 0
        ];
        $list = new MailingList($options);

        self::assertSame(10, $list->getId());
        self::assertSame(20, $list->getRvid());
        self::assertSame('Name', $list->getName());
        self::assertSame('Type', $list->getType());
        self::assertSame(0, $list->getStatus());
    }
}
