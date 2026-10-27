<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\RevalidationService;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Exception;
use Zend_Db_Table_Abstract;

/**
 * Episciences_PapersManager::delete() removes every row belonging to the deleted paper version,
 * and the rows shared by every version (keyed by paperid) with the last version only.
 * delete() runs its own transaction, so the rows cannot be rolled back: they are removed in tearDown().
 *
 * @covers Episciences_PapersManager::delete
 * @covers Episciences_PapersManager::deletePaper
 */
final class Episciences_PapersManager_DeleteTest extends TestCase
{
    use RestoresStaticQueuePort;

    private const PAPER_ID = 999201;
    private const VOLUME_ID = 999201;
    private const DATE = '2020-01-02 03:04:05';

    private const VERSION_TABLES = [
        'files',
        'data_descriptor',
        'paper_files',
        'paper_datasets',
        'paper_datasets_meta',
        'paper_license_code',
        'mail_log',
        'paper_citations',
        'paper_classifications',
        'assignments',
        'invitations',
        'answers',
        'answer details',
        'temporary reviewers',
    ];

    /**
     * Tables referencing a paper that deletePaper() leaves alone on purpose.
     */
    private const KEPT_TABLES = [
        // Failed Solr enqueues, retried later: the deletion of the paper from the index may be one of them
        'solr_enqueue_failures',
    ];

    private const SHARED_TABLES = [
        'authors',
        'doi_queue',
        'volume position',
        'projects',
        'conflicts',
    ];

    private Zend_Db_Adapter_Abstract $db;

    private int $sourceId;

    /** @var int[] */
    private array $docIds = [];

    /** @var array<int, int> dataset metadata of each paper version */
    private array $datasetsMetaIds = [];

    /** @var array<int, int> reviewer invitation of each paper version, kept once its assignment is deleted */
    private array $invitationIds = [];

    /** @var array<int, int> temporary reviewer of each paper version */
    private array $tmpUserIds = [];

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;

        // Citations and projects reference a metadata source
        $this->db->insert(T_PAPER_METADATA_SOURCES, [
            'name' => 'delete-paper-test',
            'type' => 'metadataRepository',
            'doi_prefix' => '',
            'api_url' => '',
            'doc_url' => '',
            'paper_url' => '',
        ]);
        $this->sourceId = (int)$this->db->lastInsertId();

        $this->captureStaticQueuePort(SolrIndexing::class);
        $this->captureStaticQueuePort(RevalidationService::class);
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));
    }

    protected function tearDown(): void
    {
        foreach ($this->docIds as $docId) {
            $this->deleteAssignmentRows($docId);
            $this->db->delete(T_PAPER_DATASETS_META, ['id = ?' => $this->datasetsMetaIds[$docId] ?? 0]);
            $this->db->delete(T_PAPER_LICENSE_CODE, ['docid = ?' => $docId]);
            $this->db->delete(T_MAIL_LOG, ['DOCID = ?' => $docId]);
            $this->db->delete(T_PAPER_DATA_DESCRIPTOR, ['docid = ?' => $docId]);
            $this->db->delete(T_FILES, ['docid = ?' => $docId]);
            $this->db->delete(T_PAPER_FILES, ['doc_id = ?' => $docId]);
            $this->db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $docId]);
            $this->db->delete(T_PAPER_CITATIONS, ['docid = ?' => $docId]);
            $this->db->delete(T_PAPER_CLASSIFICATIONS, ['docid = ?' => $docId]);
            $this->db->delete(T_PAPERS, ['DOCID = ?' => $docId]);
        }
        $this->db->delete(T_PAPER_AUTHORS, ['paperid = ?' => self::PAPER_ID]);
        $this->db->delete(T_DOI_QUEUE, ['paperid = ?' => self::PAPER_ID]);
        $this->db->delete(T_VOLUME_PAPER_POSITION, ['PAPERID = ?' => self::PAPER_ID]);
        $this->db->delete(T_PAPER_PROJECTS, ['paperid = ?' => self::PAPER_ID]);
        $this->db->delete(T_PAPER_CONFLICTS, ['paper_id = ?' => self::PAPER_ID]);
        if ($this->tmpUserIds !== []) {
            $this->db->delete(T_TMP_USER, ['ID IN (?)' => array_values($this->tmpUserIds)]);
        }
        $this->db->delete(T_PAPER_METADATA_SOURCES, ['id = ?' => $this->sourceId]);

        $this->restoreStaticQueuePort(RevalidationService::class);
        $this->restoreStaticQueuePort(SolrIndexing::class);
    }

    public function testDeletingTheOnlyVersionRemovesEveryRelatedRow(): void
    {
        $docId = $this->insertPaper(1);
        $this->insertVersionRows($docId);
        $this->insertSharedRows();

        self::assertTrue(Episciences_PapersManager::delete($docId));

        self::assertSame(array_fill_keys(self::VERSION_TABLES, 0), $this->countVersionRows($docId));
        self::assertSame(array_fill_keys(self::SHARED_TABLES, 0), $this->countSharedRows());
    }

    public function testDeletingOneVersionKeepsTheRowsSharedWithTheOtherVersions(): void
    {
        $firstVersion = $this->insertPaper(1);
        $secondVersion = $this->insertPaper(2);
        $this->insertVersionRows($firstVersion);
        $this->insertVersionRows($secondVersion);
        $this->insertSharedRows();

        self::assertTrue(Episciences_PapersManager::delete($secondVersion));

        self::assertSame(array_fill_keys(self::VERSION_TABLES, 0), $this->countVersionRows($secondVersion));
        // The rows of the remaining version, and those shared by every version, are untouched
        self::assertSame(array_fill_keys(self::VERSION_TABLES, 1), $this->countVersionRows($firstVersion));
        self::assertSame(array_fill_keys(self::SHARED_TABLES, 1), $this->countSharedRows());
    }

    public function testEveryTableReferencingAPaperIsPurged(): void
    {
        // Any table with a docid or paperid column (whatever its case and underscores)
        $tables = $this->db->fetchCol(
            "SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND LOWER(REPLACE(COLUMN_NAME, '_', '')) IN ('docid', 'paperid')
             ORDER BY TABLE_NAME"
        );
        // Ad hoc copies of a table (e.g. PAPERS-YYYY-MM-DD backups) are not application tables
        $tables = preg_grep('/^\w+$/', $tables);

        $purged = array_merge(
            array_keys(Episciences_PapersManager::VERSION_TABLES),
            array_keys(Episciences_PapersManager::PAPER_TABLES),
            self::KEPT_TABLES
        );

        self::assertSame(
            [],
            array_values(array_diff($tables, $purged)),
            'Tables referencing a paper that Episciences_PapersManager::deletePaper() does not purge: ' .
            'add them to VERSION_TABLES (keyed by DOCID) or PAPER_TABLES (keyed by PAPERID)'
        );
    }

    public function testATemporaryReviewerStillAssignedToAnotherPaperIsKept(): void
    {
        $firstVersion = $this->insertPaper(1);
        $secondVersion = $this->insertPaper(2);
        $tmpUserId = $this->insertTmpUser($firstVersion);
        $this->insertAssignment($firstVersion, $tmpUserId, true);
        $this->insertAssignment($secondVersion, $tmpUserId, true);

        self::assertTrue(Episciences_PapersManager::delete($secondVersion));

        self::assertSame(1, $this->countWhere(T_TMP_USER, 'ID = ?', $tmpUserId));
    }

    public function testAFailedDeletionDeletesNothing(): void
    {
        $docId = $this->insertPaper(1);
        $this->insertVersionRows($docId);
        $paper = Episciences_PapersManager::get($docId, false);
        self::assertInstanceOf(Episciences_Paper::class, $paper);
        // Make the purge fail once the version rows are deleted: a temporary table without a paperid column hides doi_queue
        $this->db->query('CREATE TEMPORARY TABLE ' . T_DOI_QUEUE . ' (x INT)');

        try {
            Episciences_PapersManager::deletePaper($paper);
            self::fail('The deletion should have failed');
        } catch (Zend_Db_Exception) {
            // expected
        } finally {
            $this->db->query('DROP TEMPORARY TABLE ' . T_DOI_QUEUE);
        }

        self::assertSame(1, $this->countWhere(T_PAPERS, 'DOCID = ?', $docId));
        self::assertSame(array_fill_keys(self::VERSION_TABLES, 1), $this->countVersionRows($docId));
    }

    private function insertPaper(float $version): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => self::PAPER_ID,
            'RVID' => RVID,
            'UID' => 1,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'delete-paper-test',
            'VERSION' => $version,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => self::DATE,
            'SUBMISSION_DATE' => self::DATE,
        ]);
        $docId = (int)$this->db->lastInsertId();
        $this->docIds[] = $docId;

        return $docId;
    }

    /**
     * One row in each table keyed by docid.
     */
    private function insertVersionRows(int $docId): void
    {
        $this->db->insert(T_FILES, [
            'docid' => $docId,
            'name' => 'data-descriptor.pdf',
            'extension' => 'pdf',
            'type_mime' => 'application/pdf',
            'size' => 1,
            'md5' => str_repeat('0', 32),
            'uploaded_date' => self::DATE,
        ]);
        $this->db->insert(T_PAPER_DATA_DESCRIPTOR, [
            'uid' => 1,
            'docid' => $docId,
            'fileid' => (int)$this->db->lastInsertId(),
            'submission_date' => self::DATE,
        ]);

        $this->db->insert(T_PAPER_FILES, [
            'doc_id' => $docId,
            'file_name' => 'article.pdf',
            'checksum' => str_repeat('0', 32),
            'file_size' => 1,
            'file_type' => 'pdf',
        ]);
        $this->db->insert(T_PAPER_DATASETS_META, ['metatext' => '{}']);
        $this->datasetsMetaIds[$docId] = (int)$this->db->lastInsertId();
        $this->db->insert(T_PAPER_DATASETS, [
            'doc_id' => $docId,
            'code' => 'swhidId',
            'name' => 'software',
            'value' => 'delete-paper-test',
            'link' => 'https://example.org',
            'source_id' => $this->sourceId,
            'id_paper_datasets_meta' => $this->datasetsMetaIds[$docId],
        ]);
        $this->db->insert(T_PAPER_LICENSE_CODE, ['docid' => $docId, 'code' => 'CC-BY-4.0']);
        $this->db->insert(T_MAIL_LOG, ['RVID' => RVID, 'DOCID' => $docId, 'TO' => 'author@example.org', 'WHEN' => self::DATE]);
        $this->db->insert(T_PAPER_CITATIONS, ['docid' => $docId, 'citation' => '[]', 'source_id' => $this->sourceId]);
        $this->db->insert(T_PAPER_CLASSIFICATIONS, [
            'docid' => $docId,
            'classification_code' => 'A10',
            'classification_name' => 'jel',
            'source_id' => $this->sourceId,
        ]);

        // The reviewer has no account
        $this->db->insert(T_USER_INVITATIONS, [
            'AID' => $this->insertAssignment($docId, $this->insertTmpUser($docId), true),
            'SENDING_DATE' => self::DATE,
            'EXPIRATION_DATE' => self::DATE,
        ]);
        $invitationId = (int)$this->db->lastInsertId();
        $this->invitationIds[$docId] = $invitationId;
        $this->db->insert(T_USER_INVITATION_ANSWER, ['ID' => $invitationId, 'ANSWER' => 'yes', 'ANSWER_DATE' => self::DATE]);
        $this->db->insert(T_USER_INVITATION_ANSWER_DETAIL, ['ID' => $invitationId, 'NAME' => 'comment', 'VALUE' => 'ok']);
    }

    /**
     * A reviewer without an account, invited to review the paper version.
     */
    private function insertTmpUser(int $docId): int
    {
        $this->db->insert(T_TMP_USER, ['EMAIL' => 'reviewer@example.org', 'FIRSTNAME' => 'Temporary', 'LASTNAME' => 'Reviewer']);
        $this->tmpUserIds[$docId] = (int)$this->db->lastInsertId();

        return $this->tmpUserIds[$docId];
    }

    private function insertAssignment(int $docId, int $uid, bool $tmpUser): int
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => RVID,
            'ITEM' => Episciences_User_Assignment::ITEM_PAPER,
            'ITEMID' => $docId,
            'UID' => $uid,
            'TMP_USER' => (int)$tmpUser,
            'ROLEID' => Episciences_User_Assignment::ROLE_REVIEWER,
            'STATUS' => Episciences_User_Assignment::STATUS_ACTIVE,
            'WHEN' => self::DATE,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * One row in each table keyed by paperid, shared by every version.
     */
    private function insertSharedRows(): void
    {
        $this->db->insert(T_PAPER_AUTHORS, ['paperid' => self::PAPER_ID, 'authors' => '[]']);
        $this->db->insert(T_DOI_QUEUE, ['paperid' => self::PAPER_ID, 'date_init' => self::DATE]);
        $this->db->insert(T_VOLUME_PAPER_POSITION, ['VID' => self::VOLUME_ID, 'PAPERID' => self::PAPER_ID, 'POSITION' => 1]);
        $this->db->insert(T_PAPER_PROJECTS, ['paperid' => self::PAPER_ID, 'funding' => '[]', 'source_id' => $this->sourceId]);
        $this->db->insert(T_PAPER_CONFLICTS, ['paper_id' => self::PAPER_ID, 'by' => 1, 'answer' => 'no']);
    }

    /**
     * @return array<string, int>
     */
    private function countVersionRows(int $docId): array
    {
        $invitationId = $this->invitationIds[$docId] ?? 0;

        return [
            'files' => $this->countWhere(T_FILES, 'docid = ?', $docId),
            'data_descriptor' => $this->countWhere(T_PAPER_DATA_DESCRIPTOR, 'docid = ?', $docId),
            'paper_files' => $this->countWhere(T_PAPER_FILES, 'doc_id = ?', $docId),
            'paper_datasets' => $this->countWhere(T_PAPER_DATASETS, 'doc_id = ?', $docId),
            'paper_datasets_meta' => $this->countWhere(T_PAPER_DATASETS_META, 'id = ?', $this->datasetsMetaIds[$docId] ?? 0),
            'paper_license_code' => $this->countWhere(T_PAPER_LICENSE_CODE, 'docid = ?', $docId),
            'mail_log' => $this->countWhere(T_MAIL_LOG, 'DOCID = ?', $docId),
            'paper_citations' => $this->countWhere(T_PAPER_CITATIONS, 'docid = ?', $docId),
            'paper_classifications' => $this->countWhere(T_PAPER_CLASSIFICATIONS, 'docid = ?', $docId),
            'assignments' => count($this->assignmentIds($docId)),
            'invitations' => $this->countWhere(T_USER_INVITATIONS, 'ID = ?', $invitationId),
            'answers' => $this->countWhere(T_USER_INVITATION_ANSWER, 'ID = ?', $invitationId),
            'answer details' => $this->countWhere(T_USER_INVITATION_ANSWER_DETAIL, 'ID = ?', $invitationId),
            'temporary reviewers' => $this->countWhere(T_TMP_USER, 'ID = ?', $this->tmpUserIds[$docId] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countSharedRows(): array
    {
        return [
            'authors' => $this->countWhere(T_PAPER_AUTHORS, 'paperid = ?', self::PAPER_ID),
            'doi_queue' => $this->countWhere(T_DOI_QUEUE, 'paperid = ?', self::PAPER_ID),
            'volume position' => $this->countWhere(T_VOLUME_PAPER_POSITION, 'PAPERID = ?', self::PAPER_ID),
            'projects' => $this->countWhere(T_PAPER_PROJECTS, 'paperid = ?', self::PAPER_ID),
            'conflicts' => $this->countWhere(T_PAPER_CONFLICTS, 'paper_id = ?', self::PAPER_ID),
        ];
    }

    private function countWhere(string $table, string $condition, mixed $value): int
    {
        return (int)$this->db->fetchOne($this->db->select()->from($table, ['COUNT(*)'])->where($condition, $value));
    }

    /**
     * @return array<int|string>
     */
    private function assignmentIds(int $docId): array
    {
        return $this->db->fetchCol(
            $this->db->select()
                ->from(T_ASSIGNMENTS, ['ID'])
                ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
                ->where('ITEMID = ?', $docId)
        );
    }

    private function deleteAssignmentRows(int $docId): void
    {
        $invitationId = $this->invitationIds[$docId] ?? 0;
        $this->db->delete(T_USER_INVITATION_ANSWER_DETAIL, ['ID = ?' => $invitationId]);
        $this->db->delete(T_USER_INVITATION_ANSWER, ['ID = ?' => $invitationId]);
        $this->db->delete(T_USER_INVITATIONS, ['ID = ?' => $invitationId]);

        $assignmentIds = $this->assignmentIds($docId);
        if ($assignmentIds !== []) {
            $this->db->delete(T_ASSIGNMENTS, ['ID IN (?)' => $assignmentIds]);
        }
    }
}
