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

    public function testDefaultStatusIsEnabled(): void
    {
        // Status 1 means enabled (active). The default must be enabled.
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

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameWithEmptySubName(): void
    {
        $fullName = MailingList::buildFullName('DEV');
        self::assertSame('dev@episciences.org', $fullName);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameWithSubName(): void
    {
        $fullName = MailingList::buildFullName('DEV', 'Editors');
        self::assertSame('dev-editors@episciences.org', $fullName);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameSanitizesSubName(): void
    {
        $fullName = MailingList::buildFullName('DEV', 'Editors Space & Test!');
        self::assertSame('dev-editorsspacetest@episciences.org', $fullName);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameAllowsDotsDashesUnderscores(): void
    {
        $fullName = MailingList::buildFullName('DEV', 'sub.name_test-123');
        self::assertSame('dev-sub.name_test-123@episciences.org', $fullName);
    }

    // ------------------------------------------------------------------
    // Default values
    // ------------------------------------------------------------------

    public function testDefaultIdIsNull(): void
    {
        self::assertNull($this->list->getId());
    }

    public function testDefaultRvidIsNull(): void
    {
        self::assertNull($this->list->getRvid());
    }

    public function testDefaultNameIsEmptyString(): void
    {
        self::assertSame('', $this->list->getName());
    }

    public function testDefaultTypeIsNull(): void
    {
        self::assertNull($this->list->getType());
    }

    public function testDefaultUsersIsEmptyArray(): void
    {
        self::assertSame([], $this->list->getUsers());
    }

    public function testDefaultRolesIsEmptyArray(): void
    {
        self::assertSame([], $this->list->getRoles());
    }

    // ------------------------------------------------------------------
    // setType null
    // ------------------------------------------------------------------

    public function testSetTypeAcceptsNull(): void
    {
        $this->list->setType('mailing_list_type_open');
        $this->list->setType(null);
        self::assertNull($this->list->getType());
    }

    // ------------------------------------------------------------------
    // Fluent interface
    // ------------------------------------------------------------------

    public function testAllSettersReturnSelf(): void
    {
        $result = $this->list
            ->setId(1)
            ->setRvid(2)
            ->setName('n')
            ->setType('t')
            ->setStatus(0)
            ->setUsers([1])
            ->setRoles(['editor']);

        self::assertSame($this->list, $result);
    }

    // ------------------------------------------------------------------
    // toArray() contract
    // ------------------------------------------------------------------

    public function testToArrayHasExactlyFiveKeys(): void
    {
        $array = $this->list->toArray();
        self::assertCount(5, $array);
        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('rvid', $array);
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('type', $array);
        self::assertArrayHasKey('status', $array);
    }

    public function testToArrayDoesNotIncludeUsersOrRoles(): void
    {
        $this->list->setUsers([1, 2])->setRoles(['editor']);
        $array = $this->list->toArray();
        self::assertArrayNotHasKey('users', $array);
        self::assertArrayNotHasKey('roles', $array);
    }

    // ------------------------------------------------------------------
    // setOptions / constructor edge cases
    // ------------------------------------------------------------------

    public function testConstructorWithNullDoesNotThrow(): void
    {
        $list = new MailingList(null);
        self::assertNull($list->getId());
        self::assertSame(1, $list->getStatus());
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $list = new MailingList(['unknown_key' => 'value', 'does_not_exist' => 99]);
        self::assertNull($list->getId());
        self::assertSame('', $list->getName());
    }

    public function testConstructorOptionsOverrideDefaults(): void
    {
        $list = new MailingList(['id' => 7, 'status' => 0, 'name' => 'test']);
        self::assertSame(7, $list->getId());
        self::assertSame(0, $list->getStatus());
        self::assertSame('test', $list->getName());
    }

    // ------------------------------------------------------------------
    // buildFullName edge cases
    // ------------------------------------------------------------------

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameWithOnlySpecialCharsSubName(): void
    {
        // All chars stripped → treated as empty → mandatory list form
        $fullName = MailingList::buildFullName('DEV', '!!! @@@');
        self::assertSame('dev@episciences.org', $fullName);
    }

    /**
     * A user who types "-editors" in the sub-name field (thinking the prefix
     * already ends with the separator) must not produce a double dash.
     * buildFullName() strips any leading hyphens from the sub-name before
     * prepending its own separator.
     *
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameStripsLeadingHyphensFromSubName(): void
    {
        $fullName = MailingList::buildFullName('DEV', '-editors');
        self::assertSame('dev-editors@episciences.org', $fullName);
    }

    /**
     * Multiple leading hyphens must all be removed.
     *
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameStripsMultipleLeadingHyphens(): void
    {
        $fullName = MailingList::buildFullName('journal', '--board');
        self::assertSame('journal-board@episciences.org', $fullName);
    }

    /**
     * A sub-name that is only hyphens collapses to empty after stripping,
     * so the result is the mandatory list address.
     *
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameSubNameOfOnlyHyphensYieldsMainList(): void
    {
        $fullName = MailingList::buildFullName('journal', '---');
        self::assertSame('journal@episciences.org', $fullName);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameWithDigitsInSubName(): void
    {
        $fullName = MailingList::buildFullName('journal', '2024');
        self::assertSame('journal-2024@episciences.org', $fullName);
    }

    /**
     * @covers \Episciences\MailingList\MailingList::buildFullName
     */
    public function testBuildFullNameNormalizesSubNameToLowercase(): void
    {
        $fullName = MailingList::buildFullName('dev', 'UPPERCASE');
        self::assertSame('dev-uppercase@episciences.org', $fullName);
    }
}
