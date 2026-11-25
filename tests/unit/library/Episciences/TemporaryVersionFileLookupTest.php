<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_CommentsManager;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Queries behind the access checks on the files of the temporary versions (see FileController): the versions
 * of a paper in a journal, and the files of the answers already saved with a temporary version.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getDocIdsByPaperIdInJournal
 * @covers Episciences_CommentsManager::isTemporaryVersionAnswerAttachment
 */
final class TemporaryVersionFileLookupTest extends TestCase
{
    private const OWNER_UID = 4242;

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

    /**
     * @param array<string, mixed> $columns columns overriding the defaults
     */
    private function insertVersion(int $paperId, string $when, array $columns = []): int
    {
        $this->db->insert(T_PAPERS, $columns + [
            'PAPERID' => $paperId,
            'RVID' => RVID,
            'UID' => self::OWNER_UID,
            'STATUS' => 0,
            'IDENTIFIER' => 'temporary-version-file-lookup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => $when,
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * First version of a new paper: its docid is the paper id
     */
    private function insertPaper(string $when): int
    {
        $docId = $this->insertVersion(0, $when);
        $this->db->update(T_PAPERS, ['PAPERID' => $docId], ['DOCID = ?' => $docId]);

        return $docId;
    }

    private function insertComment(int $docId, int $type, ?string $file): void
    {
        $this->db->insert(T_PAPER_COMMENTS, [
            'TYPE' => $type,
            'DOCID' => $docId,
            'UID' => self::OWNER_UID,
            'MESSAGE' => '',
            'FILE' => $file,
            'WHEN' => '2020-01-02 03:04:05',
        ]);
    }

    public function testVersionsOfThePaperAreListedTheLatestFirst(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $temporaryVersion = $this->insertVersion($paperId, '2020-03-01 00:00:00', ['VERSION' => 1.01]);
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00', ['VERSION' => 2]);

        self::assertSame(
            [$temporaryVersion, $secondVersion, $paperId],
            Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID)
        );
    }

    public function testVersionsInAnotherJournalAreNotListed(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertVersion($paperId, '2020-02-01 00:00:00', ['RVID' => RVID + 1]);

        self::assertSame([$paperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID));
        self::assertSame([], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID + 2));
    }

    public function testVersionsOfAnotherPaperAreNotListed(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $otherPaperId = $this->insertPaper('2020-02-01 00:00:00');

        self::assertSame([$paperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID));
        self::assertSame([$otherPaperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($otherPaperId, RVID));
    }

    public function testFileListedAmongTheFilesOfAnAnswerIsFound(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment(
            $docId,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
            json_encode(['article.pdf', 'résumé (v2).pdf'])
        );

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'résumé (v2).pdf'));
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'résumé.pdf'));
    }

    public function testSingleFileOfAnAnswerIsFound(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, 'article.pdf');

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'article.pdf'));
    }

    public function testAnswerSavedOnAnyVersionOfThePaperIsLookedUp(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00', ['VERSION' => 2]);
        $this->insertComment(
            $secondVersion,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
            json_encode(['article.pdf'])
        );

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$paperId, $secondVersion], 'article.pdf'));
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$paperId], 'article.pdf'));
    }

    public function testFilesOfOtherCommentsAreIgnored(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT, json_encode(['article.pdf']));
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_INFO_REQUEST, 'article.pdf');

        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'article.pdf'));
    }

    public function testAnswerWithoutFileIsIgnored(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, null);
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, '');

        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], ''));
    }

    public function testNoVersionMeansNoAnswer(): void
    {
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([], 'article.pdf'));
    }
}
