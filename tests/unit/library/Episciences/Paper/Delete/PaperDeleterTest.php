<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Delete;

use Episciences\Paper\Delete\DeletionOutcome;
use Episciences\Paper\Delete\PaperDeleter;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * PaperDeleter deletes the paper versions of its journal only.
 * The deletion itself is replaced by a spy (Episciences_PapersManager::delete() has its own test),
 * so the rows can be inserted in a transaction rolled back after each test.
 *
 * @covers \Episciences\Paper\Delete\PaperDeleter
 * @covers \Episciences\Paper\Delete\DeletionOutcome
 */
final class PaperDeleterTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    /** @var int[] */
    private array $deleted = [];

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

    public function testDeletesAPaperOfTheJournal(): void
    {
        $docId = $this->insertPaper(RVID);

        $outcome = $this->deleter(true)->delete($docId);

        self::assertSame(DeletionOutcome::Deleted, $outcome);
        self::assertTrue($outcome->isSuccess());
        self::assertSame([$docId], $this->deleted);
    }

    public function testReportsAFailedDeletion(): void
    {
        $docId = $this->insertPaper(RVID);

        $outcome = $this->deleter(false)->delete($docId);

        self::assertSame(DeletionOutcome::Failed, $outcome);
        self::assertFalse($outcome->isSuccess());
    }

    public function testDryRunDeletesNothing(): void
    {
        $docId = $this->insertPaper(RVID);

        $outcome = $this->deleter(true)->delete($docId, true);

        self::assertSame(DeletionOutcome::WouldBeDeleted, $outcome);
        self::assertTrue($outcome->isSuccess());
        self::assertSame([], $this->deleted);
    }

    public function testRefusesAPaperOfAnotherJournal(): void
    {
        $docId = $this->insertPaper(RVID + 1);

        $outcome = $this->deleter(true)->delete($docId);

        self::assertSame(DeletionOutcome::OtherJournal, $outcome);
        self::assertFalse($outcome->isSuccess());
        self::assertSame([], $this->deleted);
    }

    public function testReportsAMissingPaper(): void
    {
        $missingDocId = $this->insertPaper(RVID) + 1000;

        $outcome = $this->deleter(true)->delete($missingDocId);

        self::assertSame(DeletionOutcome::NotFound, $outcome);
        self::assertFalse($outcome->isSuccess());
        self::assertSame([], $this->deleted);
    }

    private function deleter(bool $deletionSucceeds): PaperDeleter
    {
        return new PaperDeleter(RVID, function (int $docId) use ($deletionSucceeds): bool {
            $this->deleted[] = $docId;
            return $deletionSucceeds;
        });
    }

    private function insertPaper(int $rvid): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => 999301,
            'RVID' => $rvid,
            'UID' => 1,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'paper-deleter-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }
}
