<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The papers assigned to the selected editors (filter of the papers lists, search by name of the editors): a paper is
 * assigned to an editor whose latest assignment to it is active, whatever the assignments of the other editors.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::fetchLastPaperAssignmentForSelectedRoleQuery
 * @covers Episciences_PapersManager::getAssignedPapersForSelectedRoleQuery
 */
final class Episciences_PapersManager_AssignedPapersTest extends TestCase
{
    private const DOCID = 999401;
    private const FIRST_EDITOR = 999402;
    private const SECOND_EDITOR = 999403;
    private const THIRD_EDITOR = 999404;

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    private function insertAssignment(int $uid, string $status, string $date): void
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => RVID,
            'ITEMID' => self::DOCID,
            'ITEM' => Episciences_User_Assignment::ITEM_PAPER,
            'UID' => $uid,
            'TMP_USER' => 0,
            'ROLEID' => Episciences_User_Assignment::ROLE_EDITOR,
            'STATUS' => $status,
            'WHEN' => $date,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>> the rows of the paper
     */
    private function assignedPapers(array $uid): array
    {
        $query = new ReflectionMethod(Episciences_PapersManager::class, 'fetchLastPaperAssignmentForSelectedRoleQuery');
        $rows = $this->db->fetchAll($query->invoke(null, $uid, Episciences_User_Assignment::ROLE_EDITOR));

        return array_values(array_filter($rows, static fn(array $row): bool => (int)$row['DOCID'] === self::DOCID));
    }

    /**
     * @return int[] the editors of the paper found by the search
     */
    private function searchedEditors(): array
    {
        $editors = array_map('intval', array_column($this->assignedPapers([]), 'UID_ROLE'));
        sort($editors);

        return $editors;
    }

    private function insertEditorAssignedFirstThenAnotherOneRemoved(): void
    {
        $this->insertAssignment(self::FIRST_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-01-01 10:00:00');
        $this->insertAssignment(self::SECOND_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-02-01 10:00:00');
        $this->insertAssignment(self::SECOND_EDITOR, Episciences_User_Assignment::STATUS_INACTIVE, '2024-03-01 10:00:00');
    }

    public function testThePaperOfAnEditorAssignedBeforeAnotherOneIsFilteredOnBothEditors(): void
    {
        $this->insertEditorAssignedFirstThenAnotherOneRemoved();

        $this->assertCount(1, $this->assignedPapers([self::FIRST_EDITOR, self::SECOND_EDITOR]));
        $this->assertCount(1, $this->assignedPapers([self::FIRST_EDITOR]));
        $this->assertSame([], $this->assignedPapers([self::SECOND_EDITOR]));
    }

    public function testTheSearchFindsEveryActiveEditorOfThePaper(): void
    {
        $this->insertEditorAssignedFirstThenAnotherOneRemoved();
        $this->insertAssignment(self::THIRD_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-04-01 10:00:00');

        $this->assertSame([self::FIRST_EDITOR, self::THIRD_EDITOR], $this->searchedEditors());
    }

    public function testTheEditorsAssignedTogetherAreAllFound(): void
    {
        $this->insertAssignment(self::FIRST_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-01-01 10:00:00');
        $this->insertAssignment(self::SECOND_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-01-01 10:00:00');

        $this->assertSame([self::FIRST_EDITOR, self::SECOND_EDITOR], $this->searchedEditors());
    }

    public function testAnEditorUnassignedInTheSameSecondIsNotFound(): void
    {
        $this->insertAssignment(self::FIRST_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2024-01-01 10:00:00');
        $this->insertAssignment(self::FIRST_EDITOR, Episciences_User_Assignment::STATUS_INACTIVE, '2024-01-01 10:00:00');

        $this->assertSame([], $this->searchedEditors());
    }
}
