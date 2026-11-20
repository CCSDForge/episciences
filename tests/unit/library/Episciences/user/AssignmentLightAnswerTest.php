<?php

declare(strict_types=1);

namespace unit\library\Episciences\user;

use Episciences_User_Assignment;
use Episciences_User_AssignmentsManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use unit\library\Episciences\Reviewer\RequestTypeTestDatabase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Mode of answer of a review request (#319): the option "answered without an account" of the request type when the
 * request is sent, kept on the assignment afterwards.
 *
 * @covers Episciences_User_Assignment
 */
final class AssignmentLightAnswerTest extends TestCase
{
    private const RVID = 3;

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;
    private ?Zend_Db_Adapter_Abstract $db = null;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->db = RequestTypeTestDatabase::create();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
        Episciences_User_AssignmentsManager::setCachePool(new ArrayAdapter());
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function requestType(bool $lightAnswer): int
    {
        $this->db->insert('review_request_type', [
            'rvid' => self::RVID,
            'labels' => '{"en": "Quick opinion"}',
            'light_answer' => (int)$lightAnswer,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function setLightAnswerOfType(int $typeId, bool $lightAnswer): void
    {
        $this->db->update('review_request_type', ['light_answer' => (int)$lightAnswer], ['id = ?' => $typeId]);
    }

    private function newAssignment(?int $typeId, string $role = Episciences_User_Assignment::ROLE_REVIEWER): Episciences_User_Assignment
    {
        return new Episciences_User_Assignment([
            'rvid' => self::RVID,
            'itemid' => 10,
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'uid' => 42,
            'roleid' => $role,
            'status' => Episciences_User_Assignment::STATUS_PENDING,
            'request_type_id' => $typeId,
        ]);
    }

    private function storedLightAnswer(Episciences_User_Assignment $assignment): int
    {
        return (int)$this->db->fetchOne('SELECT LIGHT_ANSWER FROM USER_ASSIGNMENT WHERE ID = ?', [$assignment->getId()]);
    }

    public function testARequestSentForATypeAnsweredWithoutAnAccountIsAnsweredWithoutAnAccount(): void
    {
        $assignment = $this->newAssignment($this->requestType(true));

        self::assertTrue($assignment->save());

        self::assertTrue($assignment->isLight_answer());
        self::assertSame(1, $this->storedLightAnswer($assignment));
    }

    public function testARequestSentForATypeAnsweredWithAnAccountIsAnsweredWithAnAccount(): void
    {
        $assignment = $this->newAssignment($this->requestType(false));

        self::assertTrue($assignment->save());

        self::assertFalse($assignment->isLight_answer());
        self::assertSame(0, $this->storedLightAnswer($assignment));
    }

    public function testChangingTheOptionOfTheTypeDoesNotChangeTheRequestsAlreadySent(): void
    {
        $typeId = $this->requestType(true);
        $sent = $this->newAssignment($typeId);
        $sent->save();

        $this->setLightAnswerOfType($typeId, false);
        // the row read again, then written again (a status change, a deadline): the mode is kept
        $reloaded = Episciences_User_AssignmentsManager::find(['ID' => $sent->getId()]);
        self::assertInstanceOf(Episciences_User_Assignment::class, $reloaded);
        self::assertTrue($reloaded->isLight_answer());
        $reloaded->setStatus(Episciences_User_Assignment::STATUS_ACTIVE);
        $reloaded->save();
        self::assertSame(1, $this->storedLightAnswer($sent));

        // a request sent after the change follows the new option
        $next = $this->newAssignment($typeId);
        $next->save();
        self::assertSame(0, $this->storedLightAnswer($next));
    }

    public function testAnObjectBuiltWithoutTheModeDoesNotEraseItWhenSaved(): void
    {
        $sent = $this->newAssignment($this->requestType(true));
        $sent->save();

        $partial = $this->newAssignment(null);
        $partial->setId($sent->getId());
        $partial->save();

        self::assertSame(1, $this->storedLightAnswer($sent));
    }

    public function testOnlyAReviewerRequestIsAnsweredWithoutAnAccount(): void
    {
        $editor = $this->newAssignment($this->requestType(true), Episciences_User_Assignment::ROLE_EDITOR);
        $editor->save();
        self::assertSame(0, $this->storedLightAnswer($editor));

        $withoutType = $this->newAssignment(null);
        $withoutType->save();
        self::assertSame(0, $this->storedLightAnswer($withoutType));
    }

    public function testTheModeGivenWhenTheRowIsCreatedIsKept(): void
    {
        $assignment = $this->newAssignment($this->requestType(false));
        $assignment->setLight_answer(true);
        $assignment->save();

        self::assertSame(1, $this->storedLightAnswer($assignment));
    }
}
