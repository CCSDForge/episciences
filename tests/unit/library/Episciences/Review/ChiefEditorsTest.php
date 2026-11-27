<?php

declare(strict_types=1);

namespace unit\library\Episciences\Review;

use Episciences_Acl;
use Episciences_Editor;
use Episciences_Paper_Logger;
use Episciences_Review;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Json;

/**
 * Chief editors are editors: when assigned automatically to a paper, the paper history shows why.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Review::getChiefEditors
 */
final class ChiefEditorsTest extends TestCase
{
    private const RVID = 990000;
    private const CHIEF_EDITOR_UID = 990000101;
    private const EDITOR_UID = 990000102;

    private Zend_Db_Adapter_Abstract $db;
    private int $previousReviewId;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();

        $this->previousReviewId = Episciences_Review::getCurrentReviewId();
        Episciences_Review::setCurrentReviewId(self::RVID);

        $this->insertUser(self::CHIEF_EDITOR_UID, Episciences_Acl::ROLE_CHIEF_EDITOR);
        $this->insertUser(self::EDITOR_UID, Episciences_Acl::ROLE_EDITOR);
    }

    protected function tearDown(): void
    {
        Episciences_Review::setCurrentReviewId($this->previousReviewId);
        $this->db->rollBack();
    }

    private function insertUser(int $uid, string $role): void
    {
        $this->db->insert(T_USERS, [
            'UID' => $uid,
            'uuid' => sprintf('00000000-0000-4000-8000-%012d', $uid),
            'SCREEN_NAME' => "User $uid",
            'USERNAME' => "user$uid",
            'API_PASSWORD' => '',
            'EMAIL' => "user$uid@example.org",
            'LASTNAME' => "User $uid",
        ]);
        $this->db->insert(T_USER_ROLES, ['UID' => $uid, 'RVID' => self::RVID, 'ROLEID' => $role]);
    }

    public function testChiefEditorsAreEditorsIndexedByUid(): void
    {
        $chiefEditors = Episciences_Review::getChiefEditors();

        self::assertSame([self::CHIEF_EDITOR_UID], array_keys($chiefEditors));
        self::assertInstanceOf(Episciences_Editor::class, $chiefEditors[self::CHIEF_EDITOR_UID]);
        self::assertSame(self::CHIEF_EDITOR_UID, (int)$chiefEditors[self::CHIEF_EDITOR_UID]->getUid());
        self::assertSame('User ' . self::CHIEF_EDITOR_UID, $chiefEditors[self::CHIEF_EDITOR_UID]->getScreenName());
    }

    public function testAutomaticAssignmentOfAChiefEditorIsShownInThePaperHistory(): void
    {
        $editors = [];
        Episciences_Submit::addIfNotExists(Episciences_Review::getChiefEditors(), $editors, Episciences_Editor::TAG_CHIEF_EDITOR);

        // Detail of the CODE_EDITOR_ASSIGNMENT entry written for this assignment
        $log = ['DETAIL' => Zend_Json::encode(['aid' => 1, 'user' => $editors[self::CHIEF_EDITOR_UID]->toArray()])];

        self::assertNotSame('', Episciences_Paper_Logger::extractLogDisplayData($log)['tag']);
    }
}
