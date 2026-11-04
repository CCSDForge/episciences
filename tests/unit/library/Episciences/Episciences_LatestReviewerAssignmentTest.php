<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Mail_Reminder;
use Episciences_PapersManager;
use Episciences_Rating_Report;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;

/**
 * The latest assignment of a reviewer to a paper is the one with the highest ID: each change of status inserts an
 * assignment, possibly in the same second as the previous one, so two assignments can share the latest date.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getReviewers
 * @covers Episciences_Mail_Reminder::getAfterReviewingDeadlineQuery
 */
final class Episciences_LatestReviewerAssignmentTest extends TestCase
{
    private const DOCID = 999201;
    private const ACCEPTED_UID = 999202;
    private const REMOVED_UID = 999203;
    private const OUT_OF_ORDER_UID = 999204;
    private const OTHER_DOCID = 999205;
    private const DATE = '2024-01-01 10:00:00';
    private const DEADLINE = '2024-01-10';

    private const REGISTRY_KEYS = ['Zend_Locale', 'languages', 'Zend_Translate'];

    private Zend_Db_Adapter_Abstract $db;

    /**
     * Registry entries present before the test (the other ones are removed after the test)
     * @var array<string, mixed>
     */
    private array $savedRegistry = [];

    protected function setUp(): void
    {
        $this->savedRegistry = [];
        foreach (self::REGISTRY_KEYS as $key) {
            if (Zend_Registry::isRegistered($key)) {
                $this->savedRegistry[$key] = Zend_Registry::get($key);
            }
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        $registry = Zend_Registry::getInstance();
        foreach (self::REGISTRY_KEYS as $key) {
            if (array_key_exists($key, $this->savedRegistry)) {
                Zend_Registry::set($key, $this->savedRegistry[$key]);
            } elseif ($registry->offsetExists($key)) {
                $registry->offsetUnset($key);
            }
        }
    }

    private function insertAssignment(int $docId, int $uid, string $status, string $when = self::DATE, bool $isTmpUser = false): void
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => RVID,
            'ITEMID' => $docId,
            'ITEM' => 'paper',
            'UID' => $uid,
            'TMP_USER' => (int)$isTmpUser,
            'ROLEID' => 'reviewer',
            'STATUS' => $status,
            'WHEN' => $when,
            'DEADLINE' => self::DEADLINE . ' 00:00:00',
        ]);
    }

    /**
     * The assignments of a reviewer to the paper, in this order and all in the same second, and the report of the reviewer
     *
     * @param string[] $statuses
     */
    private function insertReviewer(int $uid, array $statuses, int $reportStatus = Episciences_Rating_Report::STATUS_PENDING): void
    {
        foreach ($statuses as $status) {
            $this->insertAssignment(self::DOCID, $uid, $status);
        }

        $this->db->insert(T_REVIEWER_REPORTS, [
            'UID' => $uid,
            'DOCID' => self::DOCID,
            'STATUS' => $reportStatus,
            'CREATION_DATE' => self::DATE,
        ]);
    }

    private function insertReviewers(): void
    {
        // accepted in the same second as the invitation: active
        $this->insertReviewer(self::ACCEPTED_UID, [Episciences_User_Assignment::STATUS_PENDING, Episciences_User_Assignment::STATUS_ACTIVE]);
        // removed in the same second as the acceptance: no longer active
        $this->insertReviewer(self::REMOVED_UID, [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE]);
    }

    public function testTheReviewersHaveTheStatusOfTheirLatestAssignment(): void
    {
        $this->insertReviewers();

        $reviewers = Episciences_PapersManager::getReviewers(self::DOCID, [
            Episciences_User_Assignment::STATUS_PENDING,
            Episciences_User_Assignment::STATUS_ACTIVE,
            Episciences_User_Assignment::STATUS_INACTIVE,
        ]);

        $statuses = array_map(static fn($reviewer) => $reviewer->getStatus(), $reviewers);
        ksort($statuses);

        $this->assertSame([
            self::ACCEPTED_UID => Episciences_User_Assignment::STATUS_ACTIVE,
            self::REMOVED_UID => Episciences_User_Assignment::STATUS_INACTIVE,
        ], $statuses);
        $this->assertSame([self::ACCEPTED_UID], array_keys(Episciences_PapersManager::getReviewers(self::DOCID)));
    }

    public function testTheLatestAssignmentIsTheOneWithTheHighestIdEvenIfItsDateIsOlder(): void
    {
        // removed, then accepted again with an older date: the ID order and the date order differ
        $this->insertAssignment(self::DOCID, self::OUT_OF_ORDER_UID, Episciences_User_Assignment::STATUS_INACTIVE, '2024-01-01 10:00:01');
        $this->insertAssignment(self::DOCID, self::OUT_OF_ORDER_UID, Episciences_User_Assignment::STATUS_ACTIVE, '2024-01-01 10:00:00');
        // a later assignment of the same reviewer to another paper does not hide the assignment to this paper
        $this->insertAssignment(self::OTHER_DOCID, self::OUT_OF_ORDER_UID, Episciences_User_Assignment::STATUS_INACTIVE, '2024-01-01 10:00:02');

        $reviewers = Episciences_PapersManager::getReviewers(self::DOCID, [
            Episciences_User_Assignment::STATUS_PENDING,
            Episciences_User_Assignment::STATUS_ACTIVE,
            Episciences_User_Assignment::STATUS_INACTIVE,
        ]);

        $this->assertSame(
            [self::OUT_OF_ORDER_UID => Episciences_User_Assignment::STATUS_ACTIVE],
            array_map(static fn($reviewer) => $reviewer->getStatus(), $reviewers)
        );
        $this->assertSame([self::OUT_OF_ORDER_UID], array_keys(Episciences_PapersManager::getReviewers(self::DOCID)));
        $this->assertSame([], Episciences_PapersManager::getReviewers(self::OTHER_DOCID));
    }

    public function testTheReminderAfterTheReviewingDeadlineOnlyConcernsTheActiveReviewers(): void
    {
        $this->insertReviewers();

        $this->assertSame([self::ACCEPTED_UID], $this->afterReviewingDeadlineReminderUids());
    }

    public function testATemporaryReviewerWithTheSameIdDoesNotHideAnActiveReviewerFromTheReminder(): void
    {
        // a temporary reviewer id is not a UID: both can be equal on the same paper
        $this->insertReviewer(self::ACCEPTED_UID, [Episciences_User_Assignment::STATUS_ACTIVE]);
        $this->insertAssignment(self::DOCID, self::ACCEPTED_UID, Episciences_User_Assignment::STATUS_PENDING, self::DATE, true);

        $this->assertSame([self::ACCEPTED_UID], $this->afterReviewingDeadlineReminderUids());
    }

    /**
     * The reviewers of the after-deadline reminder of the current journal, on the reviewing deadline
     *
     * @return int[]
     */
    private function afterReviewingDeadlineReminderUids(): array
    {
        // the constructor sets the locale of the reminder (see Episciences_Mail_ReminderTest)
        if (!Zend_Registry::isRegistered('Zend_Locale')) {
            Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        }
        if (!Zend_Registry::isRegistered('languages')) {
            Zend_Registry::set('languages', ['en', 'fr']);
        }
        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::set('Zend_Translate', new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => ['' => ''], 'locale' => 'en']));
        }

        $reminder = new Episciences_Mail_Reminder();
        $reminder->setRvid(RVID);
        $reminder->setDelay(0);
        $reminder->setRepetition(0);

        $query = new ReflectionMethod(Episciences_Mail_Reminder::class, 'getAfterReviewingDeadlineQuery');
        $rows = $this->db->fetchAll($query->invoke($reminder, $this->db->quote(self::DEADLINE)));

        return array_map('intval', array_column($rows, 'UID'));
    }
}
