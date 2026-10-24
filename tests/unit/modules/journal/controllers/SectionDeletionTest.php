<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Section\SectionHasPapersException;
use Episciences_PapersManager;
use Episciences_SectionsManager;
use unit\modules\ControllerTestCase;

/**
 * Deletion of a section that still has papers, and moves of papers between sections.
 *
 * Queries are built for real and recorded, nothing is executed.
 */
final class SectionDeletionTest extends ControllerTestCase
{
    private const SID = 7;

    /** Lookup of the papers attached to a section */
    private const PAPER_LOOKUP = '/^SELECT DISTINCT\b.*FROM `PAPERS`/';

    protected function setUp(): void
    {
        self::loadController('journal', 'SectionController');
    }

    /**
     * @param list<string> $paperIds PAPERIDs returned for the section
     */
    private function givenSectionWithPapers(array $paperIds): void
    {
        $this->db
            ->respondTo(self::PAPER_LOOKUP, array_map(static fn(string $id): array => ['PAPERID' => $id], $paperIds))
            ->respondTo('/FROM `SECTION`/', [['SID' => self::SID, 'RVID' => RVID, 'POSITION' => 1]]);
    }

    /**
     * @return list<string> SQL of the paper lookups sent to the database
     */
    private function paperLookups(): array
    {
        return array_values(preg_grep(self::PAPER_LOOKUP, $this->db->selects()) ?: []);
    }

    /**
     * @return list<mixed> tables rows were deleted from
     */
    private function deletedTables(): array
    {
        return array_column($this->db->deletes(), 'table');
    }

    /**
     * @return list<array{0: mixed, 1: array<string, mixed>, 2: mixed}> table, values and where of each update
     */
    private function updates(): array
    {
        return array_map(static fn(array $update): array => [$update['table'], $update['values'], $update['where']], $this->db->updates());
    }

    /**
     * @param mixed $id posted section id
     */
    private function dispatchDelete(mixed $id = self::SID): string
    {
        $request = $this->postRequest(['ajax' => '1', 'params' => ['id' => is_int($id) ? (string)$id : $id]], true);

        return $this->dispatch(\SectionController::class, 'delete', $request)->output;
    }

    public function testSectionWithPapersIsNotDeletedAndTheButtonListsThem(): void
    {
        $this->givenSectionWithPapers(['12', '15']);

        $output = $this->dispatchDelete();

        // The delete button shows the answer as the reason of the failure
        self::assertStringContainsString('#12, #15', $output);
        self::assertNotSame('1', $output);
        self::assertSame([], $this->db->deletes());
        self::assertSame([], $this->db->updates());
        self::assertSame(['begin', 'rollback'], $this->db->transactions());
    }

    public function testOnlyTheFirstPapersOfACrowdedSectionAreListed(): void
    {
        $this->givenSectionWithPapers(array_map('strval', range(1, 13)));

        $output = $this->dispatchDelete();

        self::assertStringContainsString('#1, #2, #3, #4, #5, #6, #7, #8, #9, #10', $output);
        self::assertStringNotContainsString('#11', $output);
        self::assertMatchesRegularExpression('/#10 (et|and) 3 /', $output);
    }

    public function testSectionWithoutPapersIsDeleted(): void
    {
        $this->givenSectionWithPapers([]);

        $output = $this->dispatchDelete();

        self::assertSame('1', $output);
        self::assertContains(T_SECTIONS, $this->deletedTables());
        self::assertSame(['begin', 'commit'], $this->db->transactions());
    }

    public function testMalformedSectionIdIsRejected(): void
    {
        self::assertSame('', $this->dispatchDelete(['foo']));

        self::assertSame([], $this->db->queries());
        self::assertSame([], $this->db->deletes());
    }

    public function testManagerKeepsASectionThatStillHasPapers(): void
    {
        $this->givenSectionWithPapers(['12']);

        try {
            Episciences_SectionsManager::delete(self::SID);
            self::fail('A section that still has papers must not be deleted');
        } catch (SectionHasPapersException $e) {
            self::assertSame([12], $e->getPaperIds());
        }

        self::assertSame([], $this->db->deletes());
        self::assertSame(['begin', 'rollback'], $this->db->transactions());
    }

    public function testPapersThatCanNoLongerBeMovedLeaveTheSectionWithIt(): void
    {
        $this->givenSectionWithPapers([]);

        self::assertTrue(Episciences_SectionsManager::delete(self::SID));

        $paperUpdates = array_values(array_filter($this->updates(), static fn(array $update) => $update[0] === T_PAPERS));
        $expectedWhere = ['SID = ?' => self::SID, 'RVID = ?' => (int)RVID];
        self::assertSame([[T_PAPERS, ['SID' => 0], $expectedWhere]], $paperUpdates);
    }

    public function testAttachedPapersAreTheMovablePapersOfTheSectionJournal(): void
    {
        $this->givenSectionWithPapers(['12', '15']);

        self::assertSame([12, 15], Episciences_SectionsManager::getAttachedPaperIds(self::SID, 3));

        self::assertCount(1, $this->paperLookups());
        $sql = $this->paperLookups()[0];
        self::assertMatchesRegularExpression('/^SELECT DISTINCT\b.*\bPAPERID\b/', $sql);
        self::assertMatchesRegularExpression('/\bSID = ' . self::SID . '\b/', $sql);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $sql);
        self::assertMatchesRegularExpression(
            sprintf(
                '/\bPAPERID NOT IN \(\(SELECT .*\bSTATUS IN \(%d, %d\)\)/',
                \Episciences_Paper::STATUS_DELETED,
                \Episciences_Paper::STATUS_REMOVED
            ),
            $sql
        );
        self::assertStringNotContainsString('FOR UPDATE', $sql);
    }

    public function testDeletionLocksTheAttachedPapers(): void
    {
        $this->givenSectionWithPapers([]);

        Episciences_SectionsManager::delete(self::SID);

        self::assertCount(1, $this->paperLookups());
        self::assertStringEndsWith('FOR UPDATE', $this->paperLookups()[0]);
    }

    public function testSectionLockedForPapersBelongsToTheJournal(): void
    {
        self::assertFalse(Episciences_SectionsManager::lockForPapers(self::SID, 3));

        self::assertMatchesRegularExpression('/\bSID = ' . self::SID . '\b.*\bRVID = 3\b.*FOR UPDATE$/', $this->db->selects()[0]);
    }

    public function testMovingAPaperMovesAllItsVersions(): void
    {
        Episciences_PapersManager::updateSection(100, 3, 8);

        self::assertSame([[T_PAPERS, ['SID' => 8], ['PAPERID = ?' => 100, 'RVID = ?' => 3]]], $this->updates());
    }
}
