<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Paper;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_PapersManager::renameIdentifier() renames the versions of a refused paper submitted again:
 * the same document submitted to another journal (or as another paper) must keep its identifier.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::renameIdentifier
 */
final class Episciences_PapersManager_RenameIdentifierTest extends TestCase
{
    private const IDENTIFIER = 'rename-identifier-test';

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

    private function insertPaper(int $rvid, int $paperId, int $status): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => $paperId,
            'RVID' => $rvid,
            'UID' => 1,
            'STATUS' => $status,
            'IDENTIFIER' => self::IDENTIFIER,
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function identifierOf(int $docId): string
    {
        return (string)$this->db->fetchOne(
            $this->db->select()->from(T_PAPERS, ['IDENTIFIER'])->where('DOCID = ?', $docId)
        );
    }

    public function testOnlyTheVersionsOfThePaperInItsJournalAreRenamed(): void
    {
        $paperId = 999001;
        $refused = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_REFUSED);
        $obsolete = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_OBSOLETE);
        $otherJournal = $this->insertPaper(RVID + 1, 999002, Episciences_Paper::STATUS_SUBMITTED);
        $otherPaper = $this->insertPaper(RVID, 999003, Episciences_Paper::STATUS_SUBMITTED);

        $renamed = Episciences_PapersManager::renameIdentifier(
            self::IDENTIFIER,
            self::IDENTIFIER . '-REFUSED',
            $paperId,
            RVID
        );

        self::assertSame(2, $renamed);
        self::assertSame(self::IDENTIFIER . '-REFUSED', $this->identifierOf($refused));
        self::assertSame(self::IDENTIFIER . '-REFUSED', $this->identifierOf($obsolete));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($otherJournal));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($otherPaper));
    }

    public function testNothingIsRenamedWithoutAPaperOrAJournal(): void
    {
        $docId = $this->insertPaper(RVID, 999001, Episciences_Paper::STATUS_REFUSED);

        self::assertFalse(Episciences_PapersManager::renameIdentifier(self::IDENTIFIER, 'x', 0, RVID));
        self::assertFalse(Episciences_PapersManager::renameIdentifier(self::IDENTIFIER, 'x', 999001, 0));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($docId));
    }
}
