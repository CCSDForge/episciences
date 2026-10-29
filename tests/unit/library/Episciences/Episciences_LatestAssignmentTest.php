<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_PapersManager;
use Episciences_Reviewer;
use Episciences_User;
use Episciences_User_Assignment;
use Episciences_User_AssignmentsManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The latest assignment of a user to an item is the one with the highest ID: each change of status inserts an
 * assignment, possibly in the same second as the previous one, so two assignments can share the latest date.
 * Each test unassigns a user in the same second as the assignment: the user is no longer active.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getEditors
 * @covers Episciences_PapersManager::allPapersAssignedToRole
 * @covers Episciences_User_AssignmentsManager::getList
 * @covers Episciences_Reviewer::loadAssignedPapers
 * @covers Episciences_User::saveUserRoles
 */
final class Episciences_LatestAssignmentTest extends TestCase
{
    private const DOCID = 999301;
    private const UID = 999302;
    private const DATE = '2024-01-01 10:00:00';

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
        // getList() caches its results: never answer with the rows of another test
        Episciences_User_AssignmentsManager::getCachePool()->clear();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        Episciences_User_AssignmentsManager::getCachePool()->clear();
    }

    /**
     * The assignments of the user to the paper, in this order and all in the same second
     *
     * @param string[] $statuses
     */
    private function insertAssignments(string $roleId, array $statuses): void
    {
        foreach ($statuses as $status) {
            $this->db->insert(T_ASSIGNMENTS, [
                'RVID' => RVID,
                'ITEMID' => self::DOCID,
                'ITEM' => Episciences_User_Assignment::ITEM_PAPER,
                'UID' => self::UID,
                'TMP_USER' => 0,
                'ROLEID' => $roleId,
                'STATUS' => $status,
                'WHEN' => self::DATE,
            ]);
        }
    }

    private function insertUnassignedEditor(): void
    {
        $this->db->insert(T_USERS, [
            'UID' => self::UID,
            'uuid' => '00000000-0000-0000-0000-000000999302',
            'SCREEN_NAME' => 'Test Editor',
            'USERNAME' => 'latest-assignment-test',
            'API_PASSWORD' => '',
            'EMAIL' => 'latest-assignment-test@example.org',
            'LASTNAME' => 'Editor',
        ]);
        $this->insertAssignments(Episciences_User_Assignment::ROLE_EDITOR, [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE]);
    }

    public function testAnEditorUnassignedInTheSameSecondIsNoLongerAnEditorOfThePaper(): void
    {
        $this->insertUnassignedEditor();

        $this->assertSame([], Episciences_PapersManager::getEditors(self::DOCID, false));
    }

    public function testAPaperWhoseEditorWasUnassignedInTheSameSecondHasNoEditor(): void
    {
        $this->insertUnassignedEditor();

        $query = new ReflectionMethod(Episciences_PapersManager::class, 'allPapersAssignedToRole');
        $docIds = array_map('intval', $this->db->fetchCol($query->invoke(null, Episciences_User_Assignment::ROLE_EDITOR)));

        $this->assertNotContains(self::DOCID, $docIds);
    }

    public function testTheActiveAssignmentsAreTheLatestOnes(): void
    {
        $this->insertUnassignedEditor();

        $assignments = Episciences_User_AssignmentsManager::getList([
            'item' => Episciences_User_Assignment::ITEM_PAPER,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
            'rvid' => RVID,
            'uid' => self::UID,
            'status' => Episciences_User_Assignment::STATUS_ACTIVE,
        ]);

        $this->assertSame([], $assignments);
    }

    public function testAReviewerHasOneAssignmentByPaper(): void
    {
        $this->insertAssignments(Episciences_User_Assignment::ROLE_REVIEWER, [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE]);

        $reviewer = new Episciences_Reviewer();
        $reviewer->setUid(self::UID);
        $query = new ReflectionMethod(Episciences_Reviewer::class, 'fetchPaperReviewerAssignmentsQuery');

        $this->assertSame([Episciences_User_Assignment::STATUS_INACTIVE], array_column($this->db->fetchAll($query->invoke($reviewer)), 'STATUS'));
    }

    public function testRemovingTheEditorRoleDoesNotDisableAnAssignmentAlreadyInactive(): void
    {
        $this->insertUnassignedEditor();

        $disable = new ReflectionMethod(Episciences_User::class, 'disableAssignmentsForRemovedRoles');
        $disable->invoke(new Episciences_User(), self::UID, RVID, [Episciences_Acl::ROLE_EDITOR]);

        $disabled = $this->db->fetchOne(
            $this->db->select()->from(T_ASSIGNMENTS, 'COUNT(*)')->where('UID = ?', self::UID)->where('STATUS = ?', 'disabled')
        );

        $this->assertSame(0, (int)$disabled);
    }
}
