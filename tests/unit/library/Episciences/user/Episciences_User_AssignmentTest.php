<?php

namespace unit\library\Episciences\user;

use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_Assignment
 *
 * Tests pure logic (setters/getters, options constructor, constants).
 * The save() method requires a DB connection and is not tested here.
 *
 * @covers Episciences_User_Assignment
 */
class Episciences_User_AssignmentTest extends TestCase
{
    private Episciences_User_Assignment $assignment;

    protected function setUp(): void
    {
        $this->assignment = new Episciences_User_Assignment();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testItemConstants(): void
    {
        $this->assertSame('paper',   Episciences_User_Assignment::ITEM_PAPER);
        $this->assertSame('section', Episciences_User_Assignment::ITEM_SECTION);
        $this->assertSame('volume',  Episciences_User_Assignment::ITEM_VOLUME);
    }

    public function testRoleConstants(): void
    {
        $this->assertSame('reviewer',   Episciences_User_Assignment::ROLE_REVIEWER);
        $this->assertSame('editor',     Episciences_User_Assignment::ROLE_EDITOR);
        $this->assertSame('copyeditor', Episciences_User_Assignment::ROLE_COPY_EDITOR);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('pending',   Episciences_User_Assignment::STATUS_PENDING);
        $this->assertSame('active',    Episciences_User_Assignment::STATUS_ACTIVE);
        $this->assertSame('inactive',  Episciences_User_Assignment::STATUS_INACTIVE);
        $this->assertSame('expired',   Episciences_User_Assignment::STATUS_EXPIRED);
        $this->assertSame('cancelled', Episciences_User_Assignment::STATUS_CANCELLED);
        $this->assertSame('declined',  Episciences_User_Assignment::STATUS_DECLINED);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testDefaultConstructorCreatesEmptyAssignment(): void
    {
        $this->assertNull($this->assignment->getId());
        $this->assertNull($this->assignment->getItemid());
        $this->assertNull($this->assignment->getUid());
        $this->assertNull($this->assignment->getRoleid());
        $this->assertNull($this->assignment->getStatus());
    }

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $assignment = new Episciences_User_Assignment([
            'id'     => 99,
            'uid'    => 42,
            'itemid' => 7,
            'item'   => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
            'status' => Episciences_User_Assignment::STATUS_ACTIVE,
        ]);

        $this->assertSame(99, $assignment->getId());
        $this->assertSame(42, $assignment->getUid());
        $this->assertSame(7, $assignment->getItemid());
        $this->assertSame(Episciences_User_Assignment::ITEM_PAPER, $assignment->getItem());
        $this->assertSame(Episciences_User_Assignment::ROLE_REVIEWER, $assignment->getRoleid());
        $this->assertSame(Episciences_User_Assignment::STATUS_ACTIVE, $assignment->getStatus());
    }

    // -------------------------------------------------------------------------
    // id
    // -------------------------------------------------------------------------

    public function testSetAndGetId(): void
    {
        $this->assignment->setId(123);
        $this->assertSame(123, $this->assignment->getId());
    }

    // -------------------------------------------------------------------------
    // invitation_id
    // -------------------------------------------------------------------------

    public function testSetAndGetInvitationId(): void
    {
        $this->assignment->setInvitation_id(55);
        $this->assertSame(55, $this->assignment->getInvitation_id());
    }

    // -------------------------------------------------------------------------
    // itemid — cast to int
    // -------------------------------------------------------------------------

    public function testSetItemidCastsToInt(): void
    {
        $this->assignment->setItemid('42');
        $this->assertSame(42, $this->assignment->getItemid());
    }

    public function testSetAndGetItemid(): void
    {
        $this->assignment->setItemid(10);
        $this->assertSame(10, $this->assignment->getItemid());
    }

    // -------------------------------------------------------------------------
    // rvid
    // -------------------------------------------------------------------------

    public function testSetAndGetRvid(): void
    {
        $this->assignment->setRvid(3);
        $this->assertSame(3, $this->assignment->getRvid());
    }

    // -------------------------------------------------------------------------
    // item
    // -------------------------------------------------------------------------

    public function testSetAndGetItem(): void
    {
        $this->assignment->setItem(Episciences_User_Assignment::ITEM_VOLUME);
        $this->assertSame(Episciences_User_Assignment::ITEM_VOLUME, $this->assignment->getItem());
    }

    // -------------------------------------------------------------------------
    // uid — cast to int
    // -------------------------------------------------------------------------

    public function testSetUidCastsToInt(): void
    {
        $this->assignment->setUid('15');
        $this->assertSame(15, $this->assignment->getUid());
    }

    // -------------------------------------------------------------------------
    // from_uid
    // -------------------------------------------------------------------------

    public function testSetAndGetFromUid(): void
    {
        $this->assignment->setFrom_uid(77);
        $this->assertSame(77, $this->assignment->getFrom_uid());
    }

    public function testSetFromUidWithNull(): void
    {
        $this->assignment->setFrom_uid(null);
        $this->assertNull($this->assignment->getFrom_uid());
    }

    public function testDefaultFromUidIsNull(): void
    {
        $this->assertNull($this->assignment->getFrom_uid());
    }

    public function testSetFromUidReturnsFluent(): void
    {
        $result = $this->assignment->setFrom_uid(5);
        $this->assertInstanceOf(Episciences_User_Assignment::class, $result);
    }

    // -------------------------------------------------------------------------
    // tmp_user
    // -------------------------------------------------------------------------

    public function testIsTmpUserDefaultsToZero(): void
    {
        $this->assertSame(0, $this->assignment->isTmp_user());
    }

    public function testSetTmpUserToTrue(): void
    {
        $this->assignment->setTmp_user(true);
        $this->assertTrue((bool) $this->assignment->isTmp_user());
    }

    public function testSetTmpUserToFalse(): void
    {
        $this->assignment->setTmp_user(false);
        $this->assertFalse((bool) $this->assignment->isTmp_user());
    }

    public function testSetTmpUserToOne(): void
    {
        $this->assignment->setTmp_user(1);
        $this->assertSame(1, $this->assignment->isTmp_user());
    }

    // -------------------------------------------------------------------------
    // roleid
    // -------------------------------------------------------------------------

    public function testSetAndGetRoleid(): void
    {
        $this->assignment->setRoleid(Episciences_User_Assignment::ROLE_EDITOR);
        $this->assertSame(Episciences_User_Assignment::ROLE_EDITOR, $this->assignment->getRoleid());
    }

    // -------------------------------------------------------------------------
    // status
    // -------------------------------------------------------------------------

    public function testSetAndGetStatus(): void
    {
        $this->assignment->setStatus(Episciences_User_Assignment::STATUS_PENDING);
        $this->assertSame(Episciences_User_Assignment::STATUS_PENDING, $this->assignment->getStatus());
    }

    // -------------------------------------------------------------------------
    // when / deadline
    // -------------------------------------------------------------------------

    public function testSetAndGetWhen(): void
    {
        $this->assignment->setWhen('2024-03-15 10:00:00');
        $this->assertSame('2024-03-15 10:00:00', $this->assignment->getWhen());
    }

    public function testSetAndGetDeadline(): void
    {
        $this->assignment->setDeadline('2024-06-30 23:59:59');
        $this->assertSame('2024-06-30 23:59:59', $this->assignment->getDeadline());
    }

    // -------------------------------------------------------------------------
    // setOptions
    // -------------------------------------------------------------------------

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $this->assignment->setOptions(['nonexistentkey' => 'value', 'status' => 'active']);
        $this->assertSame('active', $this->assignment->getStatus());
    }

    public function testSetOptionsReturnsFluent(): void
    {
        $result = $this->assignment->setOptions(['status' => 'pending']);
        $this->assertInstanceOf(Episciences_User_Assignment::class, $result);
    }
}
