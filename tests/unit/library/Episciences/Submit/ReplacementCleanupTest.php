<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences_Paper;
use Episciences_Paper_DocumentBackup;
use Episciences_ReviewsManager;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * The files of a replaced paper are removed only when the paper is replaced in place: a paper kept as a row
 * of its own (the new version is a new row) keeps them.
 * Episciences_Paper_FilesManager::deleteByDocId() opens its own transaction: the rows are removed in tearDown().
 */
final class ReplacementCleanupTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    /** @var int[] */
    private array $docIds = [];

    /** @var Episciences_Paper_DocumentBackup[] */
    private array $backups = [];

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
    }

    protected function tearDown(): void
    {
        foreach ($this->backups as $backup) {
            $backup->deleteDocumentBackupFile();
            @rmdir($backup->getPath());
            @rmdir(dirname($backup->getPath()));
        }

        if ($this->docIds !== []) {
            $this->db->delete(T_PAPER_FILES, ['doc_id IN (?)' => $this->docIds]);
            $this->db->delete(T_PAPERS, ['DOCID IN (?)' => $this->docIds]);
        }
    }

    private function insertPaperWithFile(): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => 1,
            'RVID' => RVID,
            'UID' => 4242,
            'STATUS' => Episciences_Paper::STATUS_CE_READY_TO_PUBLISH,
            'IDENTIFIER' => 'replacement-cleanup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);
        $docId = (int)$this->db->lastInsertId();
        $this->docIds[] = $docId;

        $this->db->insert(T_PAPER_FILES, [
            'doc_id' => $docId,
            'file_name' => 'article.pdf',
            'checksum' => md5('article'),
            'file_size' => 1,
            'file_type' => 'pdf',
        ]);

        return $docId;
    }

    private function makeBackup(int $docId): Episciences_Paper_DocumentBackup
    {
        $journal = Episciences_ReviewsManager::findByRvid(RVID);
        if (!$journal) {
            self::markTestSkipped('The test journal does not exist.');
        }

        $backup = new Episciences_Paper_DocumentBackup($docId, (string)$journal->getCode());
        $this->backups[] = $backup;
        self::assertTrue($backup->saveDocumentBackupFile('%PDF-1.4 previous version'));

        return $backup;
    }

    private function countFiles(int $docId): int
    {
        return (int)$this->db->fetchOne(
            $this->db->select()->from(T_PAPER_FILES, ['COUNT(*)'])->where('doc_id = ?', $docId)
        );
    }

    private function cleanupOldData(int $newDocId, int $oldDocId): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'cleanupOldData');
        $method->setAccessible(true);
        $method->invoke(
            new Episciences_Submit(),
            new Episciences_Paper(['docid' => $newDocId, 'repoId' => 1, 'rvid' => RVID]),
            ['can_replace' => '1', 'old_docid' => $oldDocId]
        );
    }

    public function testFilesOfAPaperKeptAsARowOfItsOwnAreKept(): void
    {
        $oldDocId = $this->insertPaperWithFile();
        $newDocId = $this->insertPaperWithFile();

        $this->cleanupOldData($newDocId, $oldDocId);

        self::assertSame(1, $this->countFiles($oldDocId));
    }

    public function testFilesOfAPaperReplacedInPlaceAreRemoved(): void
    {
        $docId = $this->insertPaperWithFile();

        $this->cleanupOldData($docId, $docId);

        self::assertSame(0, $this->countFiles($docId));
    }

    public function testBackupCopyOfAPaperReplacedInPlaceIsRemoved(): void
    {
        $docId = $this->insertPaperWithFile();
        $backup = $this->makeBackup($docId);

        $this->cleanupOldData($docId, $docId);

        self::assertFalse($backup->hasDocumentBackupFile());
    }

    public function testBackupCopyOfAPaperKeptAsARowOfItsOwnIsKept(): void
    {
        $oldDocId = $this->insertPaperWithFile();
        $newDocId = $this->insertPaperWithFile();
        $backup = $this->makeBackup($oldDocId);

        $this->cleanupOldData($newDocId, $oldDocId);

        self::assertTrue($backup->hasDocumentBackupFile());
    }
}
