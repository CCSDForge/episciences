<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_PapersManager;
use Episciences_Reviewer;
use Episciences_Section;
use Episciences_User;
use Episciences_User_Assignment;
use Episciences_User_AssignmentsManager;
use Episciences_Volume;
use Episciences_VolumesManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The latest assignment of a user to an item is the one with the highest ID: each change of status inserts an
 * assignment, possibly in the same second as the previous one, so two assignments can share the latest date.
 * Each test unassigns a user in the same second as the assignment: the user is no longer active.
 * When a second user is involved, they are assigned in the same second as their unassignment: they are active.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getEditors
 * @covers Episciences_PapersManager::allPapersAssignedToRole
 * @covers Episciences_PapersManager::getCopyEditors
 * @covers Episciences_User_AssignmentsManager::getList
 * @covers Episciences_Reviewer::fetchPaperReviewerAssignmentsQuery
 * @covers Episciences_Reviewer::loadAssignments
 * @covers Episciences_User::disableAssignmentsForRemovedRoles
 * @covers Episciences_User::deleteLocalData
 * @covers Episciences_VolumesManager::fetchVolumeEditorRows
 * @covers Episciences_Section::loadEditors
 * @covers Episciences_Volume::loadVolumeAssignmentsForRoleQuery
 */
final class Episciences_LatestAssignmentTest extends TestCase
{
    private const DOCID = 999301;
    private const UID = 999302;
    private const OTHER_UID = 999303;
    private const VID = 999304;
    private const SID = 999305;
    private const OTHER_RVID = 999306;
    private const DATE = '2024-01-01 10:00:00';
    private const NEXT_SECOND = '2024-01-01 10:00:01';
    private const DISABLED = 'disabled';

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
     * The assignments of the user to the item, in this order (increasing IDs) and all at the same date
     *
     * @param string[] $statuses
     */
    private function insertAssignments(
        string $roleId,
        array $statuses,
        int $uid = self::UID,
        string $item = Episciences_User_Assignment::ITEM_PAPER,
        int $itemId = self::DOCID,
        int $rvid = RVID,
        int $tmpUser = 0,
        string $when = self::DATE
    ): void {
        foreach ($statuses as $status) {
            $this->db->insert(T_ASSIGNMENTS, [
                'RVID' => $rvid,
                'ITEMID' => $itemId,
                'ITEM' => $item,
                'UID' => $uid,
                'TMP_USER' => $tmpUser,
                'ROLEID' => $roleId,
                'STATUS' => $status,
                'WHEN' => $when,
            ]);
        }
    }

    /**
     * A local account, with the editor role in the journal if $editorRole
     */
    private function insertUser(int $uid, bool $editorRole = false): void
    {
        $this->db->insert(T_USERS, [
            'UID' => $uid,
            'uuid' => sprintf('00000000-0000-0000-0000-%012d', $uid),
            'SCREEN_NAME' => 'Test Editor ' . $uid,
            'USERNAME' => 'latest-assignment-test-' . $uid,
            'API_PASSWORD' => '',
            'EMAIL' => 'latest-assignment-test-' . $uid . '@example.org',
            'LASTNAME' => 'Editor',
        ]);

        if ($editorRole) {
            $this->db->insert(T_USER_ROLES, ['UID' => $uid, 'RVID' => RVID, 'ROLEID' => Episciences_Acl::ROLE_EDITOR]);
        }
    }

    /**
     * UID is assigned then unassigned, OTHER_UID unassigned then assigned, all in the same second
     */
    private function insertSameSecondAssignments(string $roleId, string $item, int $itemId): void
    {
        $this->insertAssignments($roleId, [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE], self::UID, $item, $itemId);
        $this->insertAssignments($roleId, [Episciences_User_Assignment::STATUS_INACTIVE, Episciences_User_Assignment::STATUS_ACTIVE], self::OTHER_UID, $item, $itemId);
    }

    /**
     * The latest assignment (highest ID) has the older date: UID is unassigned, OTHER_UID is assigned.
     * Taking the latest date instead would give the opposite statuses.
     */
    private function insertAssignmentsWithAnOlderLatestDate(string $item, int $itemId): void
    {
        $editor = Episciences_User_Assignment::ROLE_EDITOR;
        $this->insertAssignments($editor, [Episciences_User_Assignment::STATUS_ACTIVE], self::UID, $item, $itemId, RVID, 0, self::NEXT_SECOND);
        $this->insertAssignments($editor, [Episciences_User_Assignment::STATUS_INACTIVE], self::UID, $item, $itemId);
        $this->insertAssignments($editor, [Episciences_User_Assignment::STATUS_INACTIVE], self::OTHER_UID, $item, $itemId, RVID, 0, self::NEXT_SECOND);
        $this->insertAssignments($editor, [Episciences_User_Assignment::STATUS_ACTIVE], self::OTHER_UID, $item, $itemId);
    }

    /**
     * The disabled assignments of the user, as "RVID/ITEM/ITEMID/ROLEID"
     *
     * @return string[]
     */
    private function disabledAssignments(int $uid): array
    {
        $rows = $this->db->fetchAll(
            $this->db->select()
                ->from(T_ASSIGNMENTS, ['RVID', 'ITEM', 'ITEMID', 'ROLEID'])
                ->where('UID = ?', $uid)
                ->where('STATUS = ?', self::DISABLED)
        );
        $assignments = array_map(static fn(array $row): string => implode('/', $row), $rows);
        sort($assignments);

        return $assignments;
    }

    private function insertUnassignedEditor(): void
    {
        $this->insertUser(self::UID);
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
            $this->db->select()->from(T_ASSIGNMENTS, 'COUNT(*)')->where('UID = ?', self::UID)->where('STATUS = ?', self::DISABLED)
        );

        $this->assertSame(0, (int)$disabled);
    }

    /**
     * The assignments of UID and OTHER_UID to a paper and a volume of the journal, and to a paper of another journal
     */
    private function insertAssignmentsToDisable(): void
    {
        $this->insertSameSecondAssignments(Episciences_User_Assignment::ROLE_REVIEWER, Episciences_User_Assignment::ITEM_PAPER, self::DOCID);
        $this->insertSameSecondAssignments(Episciences_User_Assignment::ROLE_EDITOR, Episciences_User_Assignment::ITEM_VOLUME, self::VID);
        foreach ([self::UID, self::OTHER_UID] as $uid) {
            $this->insertAssignments(
                Episciences_User_Assignment::ROLE_REVIEWER,
                [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_INACTIVE],
                $uid,
                Episciences_User_Assignment::ITEM_PAPER,
                self::DOCID,
                self::OTHER_RVID
            );
        }
    }

    public function testDeletingTheLocalDataOfAUserDisablesEachAssignmentOnce(): void
    {
        $this->insertAssignmentsToDisable();

        Episciences_User::deleteLocalData(self::UID);
        Episciences_User::deleteLocalData(self::OTHER_UID);

        $expected = [RVID . '/paper/' . self::DOCID . '/reviewer', RVID . '/volume/' . self::VID . '/editor'];
        sort($expected);
        $this->assertSame($expected, $this->disabledAssignments(self::UID));
        $this->assertSame($expected, $this->disabledAssignments(self::OTHER_UID));
    }

    public function testDeletingTheLocalDataOfAUserEverywhereDisablesEachAssignmentOnce(): void
    {
        $this->insertAssignmentsToDisable();

        Episciences_User::deleteLocalData(self::UID, true);
        Episciences_User::deleteLocalData(self::OTHER_UID, true);

        $expected = [
            RVID . '/paper/' . self::DOCID . '/reviewer',
            RVID . '/volume/' . self::VID . '/editor',
            self::OTHER_RVID . '/paper/' . self::DOCID . '/reviewer',
        ];
        sort($expected);
        $this->assertSame($expected, $this->disabledAssignments(self::UID));
        $this->assertSame($expected, $this->disabledAssignments(self::OTHER_UID));
    }

    public function testAVolumeHasOneRowByEditorWithTheLatestStatus(): void
    {
        $this->insertUser(self::UID, true);
        $this->insertUser(self::OTHER_UID, true);
        $this->insertSameSecondAssignments(Episciences_User_Assignment::ROLE_EDITOR, Episciences_User_Assignment::ITEM_VOLUME, self::VID);
        // another role on the same volume, in the same second
        $this->insertAssignments(
            Episciences_User_Assignment::ROLE_COPY_EDITOR,
            [Episciences_User_Assignment::STATUS_ACTIVE],
            self::UID,
            Episciences_User_Assignment::ITEM_VOLUME,
            self::VID
        );

        $fetch = new ReflectionMethod(Episciences_VolumesManager::class, 'fetchVolumeEditorRows');
        $rows = array_map(
            static fn(array $row): string => $row['ITEMID'] . '/' . $row['UID'] . '/' . $row['STATUS'],
            $fetch->invoke(null, [self::VID])
        );
        sort($rows);

        $this->assertSame([
            self::VID . '/' . self::UID . '/' . Episciences_User_Assignment::STATUS_INACTIVE,
            self::VID . '/' . self::OTHER_UID . '/' . Episciences_User_Assignment::STATUS_ACTIVE,
        ], $rows);
    }

    public function testACopyEditorUnassignedInTheSameSecondIsNotACopyEditorOfThePaper(): void
    {
        $this->insertUser(self::UID);
        $this->insertUser(self::OTHER_UID);
        $this->insertSameSecondAssignments(Episciences_User_Assignment::ROLE_COPY_EDITOR, Episciences_User_Assignment::ITEM_PAPER, self::DOCID);

        $this->assertSame([self::OTHER_UID], array_keys(Episciences_PapersManager::getCopyEditors(self::DOCID)));
    }

    public function testTheStatusOfASectionEditorIsTheStatusOfTheLatestAssignment(): void
    {
        $this->insertUser(self::UID, true);
        $this->insertUser(self::OTHER_UID, true);
        $this->insertAssignmentsWithAnOlderLatestDate(Episciences_User_Assignment::ITEM_SECTION, self::SID);

        $section = (new Episciences_Section())->setSid(self::SID)->setRvid(RVID);
        $section->loadEditors(false);
        $statuses = array_map(static fn($editor) => $editor->getStatus(), $section->getEditors(false));
        ksort($statuses);

        $this->assertSame([
            self::UID => Episciences_User_Assignment::STATUS_INACTIVE,
            self::OTHER_UID => Episciences_User_Assignment::STATUS_ACTIVE,
        ], $statuses);

        $section->loadEditors();
        $this->assertSame([self::OTHER_UID], array_keys($section->getEditors()));
    }

    public function testTheStatusOfAVolumeEditorIsTheStatusOfTheLatestAssignment(): void
    {
        $this->insertUser(self::UID, true);
        $this->insertUser(self::OTHER_UID, true);
        $this->insertAssignmentsWithAnOlderLatestDate(Episciences_User_Assignment::ITEM_VOLUME, self::VID);

        $volume = new Episciences_Volume();
        $volume->setVid(self::VID);
        $volume->setRvid(RVID);
        $query = new ReflectionMethod(Episciences_Volume::class, 'loadVolumeAssignmentsForRoleQuery');
        $statuses = array_column($this->db->fetchAll($query->invoke($volume, Episciences_User_Assignment::ROLE_EDITOR)), 'STATUS', 'UID');
        ksort($statuses);

        $this->assertSame([
            self::UID => Episciences_User_Assignment::STATUS_INACTIVE,
            self::OTHER_UID => Episciences_User_Assignment::STATUS_ACTIVE,
        ], $statuses);
    }

    /**
     * The ID of a temporary user can be equal to the UID of a user
     */
    public function testATemporaryReviewerDoesNotHideAReviewerWithTheSameId(): void
    {
        $reviewer = Episciences_User_Assignment::ROLE_REVIEWER;
        $this->insertAssignments($reviewer, [Episciences_User_Assignment::STATUS_ACTIVE]);
        $this->insertAssignments($reviewer, [Episciences_User_Assignment::STATUS_INACTIVE], self::UID, Episciences_User_Assignment::ITEM_PAPER, self::DOCID, RVID, 1);

        $query = new ReflectionMethod(Episciences_PapersManager::class, 'allPapersAssignedToRole');
        $docIds = array_map('intval', $this->db->fetchCol($query->invoke(null, $reviewer)));

        $this->assertContains(self::DOCID, $docIds);
    }

    /**
     * A reviewer with an account accepted, then a temporary reviewer with the same id was invited to the same paper
     */
    private function insertTemporaryReviewerWithTheSameId(): void
    {
        $reviewer = Episciences_User_Assignment::ROLE_REVIEWER;
        $this->insertAssignments($reviewer, [Episciences_User_Assignment::STATUS_ACTIVE]);
        $this->insertAssignments($reviewer, [Episciences_User_Assignment::STATUS_PENDING], self::UID, Episciences_User_Assignment::ITEM_PAPER, self::DOCID, RVID, 1);
    }

    public function testATemporaryReviewerDoesNotHideThePapersOfAReviewerWithTheSameId(): void
    {
        $this->insertTemporaryReviewerWithTheSameId();

        $reviewer = new Episciences_Reviewer();
        $reviewer->setUid(self::UID);
        $query = new ReflectionMethod(Episciences_Reviewer::class, 'fetchPaperReviewerAssignmentsQuery');

        $this->assertSame([Episciences_User_Assignment::STATUS_ACTIVE], array_column($this->db->fetchAll($query->invoke($reviewer)), 'STATUS'));
    }

    public function testATemporaryReviewerDoesNotHideTheAssignmentsOfAReviewerWithTheSameId(): void
    {
        $this->insertTemporaryReviewerWithTheSameId();

        $reviewer = new Episciences_Reviewer();
        $reviewer->setUid(self::UID);
        $reviewer->loadAssignments();
        $statuses = array_map(static fn(Episciences_User_Assignment $assignment): string => $assignment->getStatus(), $reviewer->getAssignments());

        $this->assertSame([self::DOCID => Episciences_User_Assignment::STATUS_ACTIVE], $statuses);
    }
}
