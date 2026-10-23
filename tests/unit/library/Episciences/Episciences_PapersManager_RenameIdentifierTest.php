<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Paper;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_PapersManager::renameIdentifier() frees the identifier of a refused paper submitted again:
 * the same document submitted to another journal (or as another paper) must keep its identifier.
 * The previous versions of the refused paper that share its identifier (e.g. arXiv, HAL) are renamed too,
 * as they have always been: this is existing behaviour, documented here, not a new rule.
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

    private function insertPaper(int $rvid, int $paperId, int $status, float $version = 1): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => $paperId,
            'RVID' => $rvid,
            'UID' => 1,
            'STATUS' => $status,
            'IDENTIFIER' => self::IDENTIFIER,
            'VERSION' => $version,
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

    public function testOnlyThePaperInItsJournalIsRenamed(): void
    {
        $paperId = 999001;
        // A revised paper: version 1 made obsolete by version 2, which is refused
        $obsolete = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_OBSOLETE, 1);
        $refused = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_REFUSED, 2);
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

    public function testRefusedPaperGetsItsIdentifierBackWhenItsNewSubmissionIsNotSaved(): void
    {
        $paperId = 999001;
        $obsolete = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_OBSOLETE, 1);
        $refused = $this->insertPaper(RVID, $paperId, Episciences_Paper::STATUS_REFUSED, 2);
        $otherJournal = $this->insertPaper(RVID + 1, $paperId, Episciences_Paper::STATUS_REFUSED);

        Episciences_PapersManager::renameIdentifier(
            self::IDENTIFIER,
            self::IDENTIFIER . Episciences_Paper::REFUSED_IDENTIFIER_SUFFIX,
            $paperId,
            RVID
        );

        $paper = new Episciences_Paper([
            'docid' => $refused,
            'paperid' => $paperId,
            'rvid' => RVID,
            'status' => Episciences_Paper::STATUS_REFUSED,
            'identifier' => self::IDENTIFIER . Episciences_Paper::REFUSED_IDENTIFIER_SUFFIX,
        ]);

        self::assertTrue($paper->restoreRefusedIdentifier());
        self::assertSame(self::IDENTIFIER, $this->identifierOf($refused));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($obsolete));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($otherJournal));
    }

    public function testNothingIsRenamedWithoutAPaperOrAJournal(): void
    {
        $docId = $this->insertPaper(RVID, 999001, Episciences_Paper::STATUS_REFUSED);

        self::assertFalse(Episciences_PapersManager::renameIdentifier(self::IDENTIFIER, 'x', 0, RVID));
        self::assertFalse(Episciences_PapersManager::renameIdentifier(self::IDENTIFIER, 'x', 999001, 0));
        self::assertSame(self::IDENTIFIER, $this->identifierOf($docId));
    }
}
