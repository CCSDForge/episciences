<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The paper to replace is looked up in the database: it must belong to the current journal and to the
 * submitter. Rows are inserted in a transaction that is rolled back after each test.
 */
final class ReplacementLookupTest extends TestCase
{
    private const OWNER_UID = 4242;

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    private function insertPaper(int $rvid, int $uid, int $status): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => 1,
            'RVID' => $rvid,
            'UID' => $uid,
            'STATUS' => $status,
            'IDENTIFIER' => 'replacement-lookup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function testPaperOfTheCurrentJournalAndOwnerCanBeReplaced(): void
    {
        $docId = $this->insertPaper(RVID, self::OWNER_UID, Episciences_Paper::STATUS_SUBMITTED);

        $stored = Episciences_PapersManager::partialGet($docId, RVID);

        self::assertNull(Episciences_Submit::getReplacementError($stored, self::OWNER_UID, false));
    }

    public function testPaperOfAnotherJournalIsNotFound(): void
    {
        $docId = $this->insertPaper(RVID + 1, self::OWNER_UID, Episciences_Paper::STATUS_SUBMITTED);

        $stored = Episciences_PapersManager::partialGet($docId, RVID);

        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_FOUND,
            Episciences_Submit::getReplacementError($stored, self::OWNER_UID, false)
        );
    }

    public function testUnknownPaperIsNotFound(): void
    {
        $stored = Episciences_PapersManager::partialGet(0, RVID);

        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_FOUND,
            Episciences_Submit::getReplacementError($stored, self::OWNER_UID, false)
        );
    }

    public function testPaperOfAnotherAuthorIsRejected(): void
    {
        $docId = $this->insertPaper(RVID, self::OWNER_UID + 1, Episciences_Paper::STATUS_SUBMITTED);

        $stored = Episciences_PapersManager::partialGet($docId, RVID);

        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_OWNER,
            Episciences_Submit::getReplacementError($stored, self::OWNER_UID, false)
        );
    }

    public function testStoredValuesOverrideForgedOnesForAStoredPaper(): void
    {
        $docId = $this->insertPaper(RVID, self::OWNER_UID, Episciences_Paper::STATUS_REFUSED);

        $stored = Episciences_PapersManager::partialGet($docId, RVID);
        $values = Episciences_Submit::applyStoredPaperToReplacement(
            ['old_docid' => '1', 'old_submissiondate' => '1999-01-01 00:00:00'],
            $stored
        );

        self::assertSame($docId, $values['old_docid']);
        self::assertSame(Episciences_Paper::STATUS_REFUSED, $values['old_paper_status']);
        self::assertArrayNotHasKey('old_submissiondate', $values);
    }
}
