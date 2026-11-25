<?php

namespace unit\library\Episciences\user;

use Episciences\Reviewer\InvitationToken;
use Episciences_Review;
use Episciences_User_Invitation;
use Episciences_User_InvitationAnswer;
use PHPUnit\Framework\TestCase;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_User_Invitation
 *
 * Tests pure logic (setters/getters, hasExpired, isAnswered, loadAnswer early return)
 * plus isCancelled(), which touches the DB but is exercised here only through the
 * AID = 0 fallback path (no fixture uses AID 0, so the lookup reliably misses and
 * isCancelled() falls back to comparing the invitation's own status).
 * save() is exercised through a mocked default adapter that captures the inserted row.
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

    public function testSetIdCastsToInt(): void
    {
        $this->invitation->setId('42');
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
    // isCancelled
    //
    // getAid() defaults to 0 (no fixture uses AID = 0), so
    // Episciences_User_InvitationsManager::find(['AID' => 0]) reliably returns
    // false and isCancelled() falls back to comparing the invitation's own
    // status, without requiring DB fixtures.
    // -------------------------------------------------------------------------

    public function testIsCancelledReturnsFalseWhenStatusIsNotCancelled(): void
    {
        $this->invitation->setStatus(Episciences_User_Invitation::STATUS_PENDING);
        $this->assertFalse($this->invitation->isCancelled());
    }

    public function testIsCancelledReturnsTrueWhenStatusIsCancelled(): void
    {
        $this->invitation->setStatus(Episciences_User_Invitation::STATUS_CANCELLED);
        $this->assertTrue($this->invitation->isCancelled());
    }

    public function testIsCancelledReturnsFalseWithNoStatusSet(): void
    {
        $this->assertFalse($this->invitation->isCancelled());
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

    // -------------------------------------------------------------------------
    // token
    // -------------------------------------------------------------------------

    public function testTokenIsNullByDefault(): void
    {
        $this->assertNull($this->invitation->getToken());
    }

    public function testTokenColumnIsMappedBySetOptions(): void
    {
        $this->invitation->setOptions(['TOKEN' => str_repeat('a', 64)]);
        $this->assertSame(str_repeat('a', 64), $this->invitation->getToken());
    }

    public function testEmptyTokenMeansLegacyInvitation(): void
    {
        $this->invitation->setToken('');
        $this->assertNull($this->invitation->getToken());
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function pendingRowProvider(): array
    {
        return [
            'no status (column default)' => [null, true],
            'empty status' => ['', true],
            'pending' => [Episciences_User_Invitation::STATUS_PENDING, true],
            'accepted' => [Episciences_User_Invitation::STATUS_ACCEPTED, false],
            'declined' => [Episciences_User_Invitation::STATUS_DECLINED, false],
            'cancelled' => [Episciences_User_Invitation::STATUS_CANCELLED, false],
        ];
    }

    /**
     * @dataProvider pendingRowProvider
     */
    public function testIsPendingRow(?string $status, bool $expected): void
    {
        $this->invitation->setStatus($status);
        $this->assertSame($expected, $this->invitation->isPendingRow());
    }

    /**
     * Runs save() against a mocked default adapter and returns the inserted row
     * @return array<string, mixed>
     */
    private function saveAndCaptureInsert(Episciences_User_Invitation $invitation): array
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = $this->getMockBuilder($previousAdapter::class)
            ->setConstructorArgs([$previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchAll', 'insert', 'lastInsertId'])
            ->getMock();
        $adapter->method('fetchRow')->willReturn(['RVID' => 1, 'CODE' => 'test']);
        $adapter->method('fetchAll')->willReturn([
            ['SETTING' => 'invitation_deadline', 'VALUE' => Episciences_Review::DEFAULT_INVITATION_DEADLINE],
        ]);
        $adapter->method('lastInsertId')->willReturn('55');

        $inserted = [];
        $adapter->expects($this->once())->method('insert')->willReturnCallback(
            static function (string $table, array $values) use (&$inserted): int {
                $inserted = $values;
                return 1;
            }
        );

        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        // a review cached by another test may carry an unexpected deadline setting: its fallback warning is irrelevant here
        set_error_handler(static fn(): bool => true, E_USER_WARNING);
        try {
            $this->assertTrue($invitation->save());
        } finally {
            restore_error_handler();
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }

        return $inserted;
    }

    /**
     * Pending invitation whose global link secret is $secret (independent of the test environment configuration)
     */
    private function pendingInvitation(string $secret): Episciences_User_Invitation
    {
        return new class(['aid' => 5], $secret) extends Episciences_User_Invitation {
            public function __construct(array $options, private readonly string $secret)
            {
                parent::__construct($options);
            }

            protected function getInvitationToken(): InvitationToken
            {
                return new InvitationToken($this->secret);
            }
        };
    }

    public function testSavingAPendingInvitationStoresARandomRowSecret(): void
    {
        $invitation = $this->pendingInvitation('secret');

        $inserted = $this->saveAndCaptureInsert($invitation);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $inserted['TOKEN'] ?? '');
        $this->assertSame($inserted['TOKEN'], $invitation->getToken());
        $this->assertSame(55, $invitation->getId());
    }

    /**
     * Without the global secret, a row value would make every link generation throw after the insert:
     * the invitation is saved as a legacy one (no row value, former link)
     */
    public function testSavingAPendingInvitationWithoutGlobalSecretStoresNoRowSecret(): void
    {
        $invitation = $this->pendingInvitation('');

        $inserted = $this->saveAndCaptureInsert($invitation);

        $this->assertArrayNotHasKey('TOKEN', $inserted);
        $this->assertNull($invitation->getToken());
        $this->assertNull((new InvitationToken('secret'))->forInvitation($invitation));
    }

    public function testTwoPendingInvitationsGetDifferentRowSecrets(): void
    {
        $first = $this->saveAndCaptureInsert($this->pendingInvitation('secret'));
        $second = $this->saveAndCaptureInsert($this->pendingInvitation('secret'));

        $this->assertNotSame($first['TOKEN'], $second['TOKEN']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusRowProvider(): array
    {
        return [
            'accepted' => [Episciences_User_Invitation::STATUS_ACCEPTED],
            'declined' => [Episciences_User_Invitation::STATUS_DECLINED],
            'cancelled' => [Episciences_User_Invitation::STATUS_CANCELLED],
        ];
    }

    /**
     * A status row is saved from the loaded pending invitation: its row secret must not be copied
     * @dataProvider statusRowProvider
     */
    public function testSavingAStatusRowStoresNoRowSecret(string $status): void
    {
        $invitation = new Episciences_User_Invitation(['id' => 54, 'aid' => 5, 'token' => str_repeat('a', 64)]);
        $invitation->setStatus($status);

        $inserted = $this->saveAndCaptureInsert($invitation);

        $this->assertArrayNotHasKey('TOKEN', $inserted);
        $this->assertSame($status, $inserted['STATUS']);
        $this->assertNull($invitation->getToken());
    }
}
