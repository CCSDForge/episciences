<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Reminder;
use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;

/**
 * The reminder before the reviewing deadline only concerns the reviewers whose latest invitation row (highest ID) is
 * accepted: an answer inserts a row with the same AID, possibly in the same second as the previous one, so two rows
 * can share the latest sending date.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Mail_Reminder::getBeforeReviewingDeadlineQuery
 */
final class Episciences_Mail_Reminder_BeforeDeadlineQueryTest extends TestCase
{
    private const DOCID = 999301;
    private const UID = 999302;
    private const DATE = '2024-01-01 10:00:00';
    private const DEADLINE = '2024-01-10';

    private Zend_Db_Adapter_Abstract $db;

    /** @var string[] registry entries set by this test, removed after it */
    private array $registered = [];

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;

        // the constructor sets the locale of the reminder (see Episciences_Mail_ReminderTest)
        $this->register('Zend_Locale', static fn() => new Zend_Locale('en'));
        $this->register('languages', static fn() => ['en', 'fr']);
        $this->register('Zend_Translate', static fn() => new Zend_Translate([
            'adapter' => Zend_Translate::AN_ARRAY,
            'content' => ['' => ''],
            'locale' => 'en',
        ]));

        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();

        foreach ($this->registered as $key) {
            Zend_Registry::getInstance()->offsetUnset($key);
        }
        $this->registered = [];
    }

    /**
     * Sets a registry entry only if it is missing, and remembers it to remove it after the test
     */
    private function register(string $key, callable $value): void
    {
        if (!Zend_Registry::isRegistered($key)) {
            Zend_Registry::set($key, $value());
            $this->registered[] = $key;
        }
    }

    /**
     * A paper under review, an invitation of the reviewer and its answers, all in the same second (one assignment and
     * one invitation row by step), and the pending report of the reviewer
     *
     * @param array<int, array{0: string, 1: string}> $steps (status of the invitation, status of the assignment)
     */
    private function insertInvitation(array $steps): void
    {
        $this->db->insert(T_PAPERS, [
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'UID' => self::UID,
            'IDENTIFIER' => 'before-deadline-query-test',
            'REPOID' => 0,
            'RECORD' => '',
            'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED,
            'WHEN' => self::DATE,
            'SUBMISSION_DATE' => self::DATE,
        ]);

        $aid = null;

        foreach ($steps as [$invitationStatus, $assignmentStatus]) {
            $this->db->insert(T_ASSIGNMENTS, [
                'RVID' => RVID,
                'ITEMID' => self::DOCID,
                'ITEM' => 'paper',
                'UID' => self::UID,
                'TMP_USER' => 0,
                'ROLEID' => 'reviewer',
                'STATUS' => $assignmentStatus,
                'WHEN' => self::DATE,
                'DEADLINE' => self::DEADLINE . ' 00:00:00',
            ]);
            $assignmentId = (int)$this->db->lastInsertId();
            $aid ??= $assignmentId;

            $this->db->insert(T_USER_INVITATIONS, [
                'AID' => $aid,
                'STATUS' => $invitationStatus,
                'SENDING_DATE' => self::DATE,
                'EXPIRATION_DATE' => '2024-02-01 10:00:00',
            ]);
            $this->db->update(T_ASSIGNMENTS, ['INVITATION_ID' => (int)$this->db->lastInsertId()], ['ID = ?' => $assignmentId]);
        }

        $this->db->insert(T_REVIEWER_REPORTS, [
            'UID' => self::UID,
            'DOCID' => self::DOCID,
            'STATUS' => Episciences_Rating_Report::STATUS_PENDING,
            'CREATION_DATE' => self::DATE,
        ]);
    }

    /**
     * @return int[] the UIDs returned by the query, on the deadline day (no delay, no repetition)
     */
    private function fetchRecipientUids(): array
    {
        $reminder = new Episciences_Mail_Reminder();
        $reminder->setRvid(RVID);
        $reminder->setDelay(0);
        $reminder->setRepetition(0);

        $query = new ReflectionMethod(Episciences_Mail_Reminder::class, 'getBeforeReviewingDeadlineQuery');
        $sql = $query->invoke($reminder, $this->db->quote(self::DEADLINE), [Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper::STATUS_REFUSED]);

        return array_map('intval', array_column($this->db->fetchAll($sql), 'UID'));
    }

    public function testAReviewerWhoAcceptedTheInvitationIsReminded(): void
    {
        $this->insertInvitation([
            [Episciences_User_Invitation::STATUS_PENDING, Episciences_User_Assignment::STATUS_PENDING],
            [Episciences_User_Invitation::STATUS_ACCEPTED, Episciences_User_Assignment::STATUS_ACTIVE],
        ]);

        $this->assertSame([self::UID], $this->fetchRecipientUids());
    }

    public function testAReviewerRemovedInTheSameSecondAsTheAcceptanceIsNotReminded(): void
    {
        $this->insertInvitation([
            [Episciences_User_Invitation::STATUS_PENDING, Episciences_User_Assignment::STATUS_PENDING],
            [Episciences_User_Invitation::STATUS_ACCEPTED, Episciences_User_Assignment::STATUS_ACTIVE],
            [Episciences_User_Invitation::STATUS_CANCELLED, Episciences_User_Assignment::STATUS_CANCELLED],
        ]);

        $this->assertSame([], $this->fetchRecipientUids());
    }
}
