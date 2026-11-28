<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Paper_ConflictsManager;
use Episciences_PapersManager;
use Episciences_User_Assignment;
use Zend_Db_Adapter_Abstract;

/**
 * Paper facts for the logged user, read from the database.
 */
final class AuthPaperAccessProvider implements PaperAccessProvider
{
    /** @var array<int, Episciences_Paper|false> */
    private array $papers = [];

    /**
     * @param \Closure(Episciences_Paper): bool $conflictDetector same rule as the paper management page
     */
    public function __construct(
        private readonly Zend_Db_Adapter_Abstract $db,
        private readonly int $rvid,
        private readonly int $uid,
        private readonly \Closure $conflictDetector
    ) {
    }

    public function findPaperId(int $docId): ?int
    {
        return self::paperIdOf($this->db, $this->rvid, $docId);
    }

    /**
     * @return ?int PAPERID of the document, null when it does not belong to the journal
     */
    public static function paperIdOf(Zend_Db_Adapter_Abstract $db, int $rvid, int $docId): ?int
    {
        $select = $db->select()
            ->from(T_PAPERS, ['PAPERID'])
            ->where('DOCID = ?', $docId)
            ->where('RVID = ?', $rvid);

        $paperId = $db->fetchOne($select);

        return $paperId === false || $paperId === null ? null : (int)$paperId;
    }

    public function isAssigned(int $docId): bool
    {
        $paper = $this->paper($docId);

        return $paper !== false && $paper->isAssignedEditorOrCopyEditor($this->uid);
    }

    public function isAuthor(int $docId): bool
    {
        $paper = $this->paper($docId);

        return $paper === false || $paper->isOwnerOrCoAuthor();
    }

    public function hasConflict(int $docId): bool
    {
        $paper = $this->paper($docId);

        return $paper === false || ($this->conflictDetector)($paper);
    }

    public function assignedPaperIds(): array
    {
        $latest = RecipientSearchService::latestPaperAssignmentsQuery($this->db, $this->rvid, [Episciences_Acl::ROLE_EDITOR])
            ->where('UID = ?', $this->uid);

        $select = $this->db->select()
            ->distinct()
            ->from(['a' => T_ASSIGNMENTS], [])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', [])
            ->join(['p' => T_PAPERS], 'p.DOCID = a.ITEMID', ['PAPERID'])
            ->where('a.STATUS = ?', Episciences_User_Assignment::STATUS_ACTIVE);

        return array_map('intval', $this->db->fetchCol($select));
    }

    public function paperIdsWithoutConflict(): array
    {
        $rows = Episciences_Paper_ConflictsManager::findByUidAndAnswer(
            $this->uid,
            Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'],
            'array'
        );

        return array_values(array_unique(array_map(static fn(array $row): int => (int)$row['paper_id'], $rows)));
    }

    private function paper(int $docId): Episciences_Paper|false
    {
        if (!array_key_exists($docId, $this->papers)) {
            $paper = Episciences_PapersManager::get($docId, false, $this->rvid);
            $this->papers[$docId] = $paper instanceof Episciences_Paper ? $paper : false;
        }

        return $this->papers[$docId];
    }
}
