<?php

namespace unit\library\Episciences\user;

use Episciences_User_Invitation;
use Episciences_User_InvitationAnswer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_User_Invitation
 *
 * Tests pure logic (setters/getters, hasExpired, isAnswered, loadAnswer early return).
 * save() requires DB and is not tested here.
 *
 * @covers Episciences_User_Invitation
 */
class Episciences_User_InvitationTest extends TestCase
{
    private Episciences_User_Invitation $invitation;

    protected function setUp(): void
    {
        $this->invitation = new Episciences_User_Invitation();
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testStatusConstants(): void
    {
        $this->assertSame('pending',   Episciences_User_Invitation::STATUS_PENDING);
        $this->assertSame('accepted',  Episciences_User_Invitation::STATUS_ACCEPTED);
        $this->assertSame('declined',  Episciences_User_Invitation::STATUS_DECLINED);
        $this->assertSame('cancelled', Episciences_User_Invitation::STATUS_CANCELLED);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('reviewer', Episciences_User_Invitation::TYPE_REVIEWER);
        $this->assertSame('editor',   Episciences_User_Invitation::TYPE_EDITOR);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testDefaultConstructorCreatesEmptyInvitation(): void
    {
        $this->assertNull($this->invitation->getId());
        $this->assertNull($this->invitation->getStatus());
        $this->assertNull($this->invitation->getSending_date());
        $this->assertNull($this->invitation->getExpiration_date());
        $this->assertNull($this->invitation->getSender_uid());
    }

    public function testConstructorWithOptionsPopulatesFields(): void
    {
        $invitation = new Episciences_User_Invitation([
            'id'     => 10,
            'aid'    => 5,
            'status' => Episciences_User_Invitation::STATUS_PENDING,
        ]);

        $this->assertSame(10, $invitation->getId());
        $this->assertSame(5, $invitation->getAid());
        $this->assertSame(Episciences_User_Invitation::STATUS_PENDING, $invitation->getStatus());
    }

    // -------------------------------------------------------------------------
    // id
    // -------------------------------------------------------------------------

    public function testSetAndGetId(): void
    {
        $this->invitation->setId(42);
        $this->assertSame(42, $this->invitation->getId());
    }

    // -------------------------------------------------------------------------
    // aid — cast to int
    // -------------------------------------------------------------------------

    public function testSetAndGetAid(): void
    {
        $this->invitation->setAid(7);
        $this->assertSame(7, $this->invitation->getAid());
    }

    public function testGetAidCastsToInt(): void
    {
        $this->invitation->setAid('15');
        $this->assertSame(15, $this->invitation->getAid());
    }

    public function testGetAidDefaultIsZeroWhenNotSet(): void
    {
        // getAid() returns (int)null = 0
        $this->assertSame(0, $this->invitation->getAid());
    }

    // -------------------------------------------------------------------------
    // status
    // -------------------------------------------------------------------------

    public function testSetAndGetStatus(): void
    {
        $this->invitation->setStatus(Episciences_User_Invitation::STATUS_ACCEPTED);
        $this->assertSame(Episciences_User_Invitation::STATUS_ACCEPTED, $this->invitation->getStatus());
    }

    // -------------------------------------------------------------------------
    // sending_date / expiration_date
    // -------------------------------------------------------------------------

    public function testSetAndGetSendingDate(): void
    {
        $this->invitation->setSending_date('2024-01-10 09:00:00');
        $this->assertSame('2024-01-10 09:00:00', $this->invitation->getSending_date());
    }

    public function testSetAndGetExpirationDate(): void
    {
        $this->invitation->setExpiration_date('2024-02-10 09:00:00');
        $this->assertSame('2024-02-10 09:00:00', $this->invitation->getExpiration_date());
    }

    // -------------------------------------------------------------------------
    // hasExpired
    // -------------------------------------------------------------------------

    public function testHasExpiredReturnsFalseForFutureDate(): void
    {
        $this->invitation->setExpiration_date('2099-12-31 23:59:59');
        $this->assertFalse($this->invitation->hasExpired());
    }

    public function testHasExpiredReturnsTrueForPastDate(): void
    {
        $this->invitation->setExpiration_date('2000-01-01 00:00:00');
        $this->assertTrue($this->invitation->hasExpired());
    }

    // -------------------------------------------------------------------------
    // isAnswered
    // -------------------------------------------------------------------------

    public function testIsAnsweredReturnsFalseWithNoAnswer(): void
    {
        $this->assertFalse($this->invitation->isAnswered());
    }

    public function testIsAnsweredReturnsTrueWhenAnswerSet(): void
    {
        $answer = new Episciences_User_InvitationAnswer();
        $this->invitation->setAnswer($answer);
        $this->assertTrue($this->invitation->isAnswered());
    }

    // -------------------------------------------------------------------------
    // loadAnswer — early return when no id
    // -------------------------------------------------------------------------

    public function testLoadAnswerReturnsFalseWhenNoId(): void
    {
        // getId() returns null → early return false
        $this->assertFalse($this->invitation->loadAnswer());
    }

    // -------------------------------------------------------------------------
    // setOptions
    // -------------------------------------------------------------------------

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $this->invitation->setOptions([
            'unknownfield' => 'value',
            'status'       => Episciences_User_Invitation::STATUS_DECLINED,
        ]);
        $this->assertSame(Episciences_User_Invitation::STATUS_DECLINED, $this->invitation->getStatus());
    }

    public function testSetOptionsReturnsFluent(): void
    {
        $result = $this->invitation->setOptions(['status' => 'pending']);
        $this->assertInstanceOf(Episciences_User_Invitation::class, $result);
    }
}
