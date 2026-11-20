<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_User_Assignment;
use Episciences_User_Invitation;
use JsonException;
use LogicException;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * Review request types of the journals (table review_request_type)
 */
class RequestTypeRepository
{
    public const TABLE_NAME = 'review_request_type';
    private const ASSIGNMENTS_TABLE = 'USER_ASSIGNMENT';
    private const INVITATIONS_TABLE = 'USER_INVITATION';

    private ?Zend_Db_Adapter_Abstract $db;

    /** @var array<int, int|null> id of the review report type, by journal (cache of fullReportIdOf()) */
    private static array $fullReportIds = [];

    /** @var array<int, bool> whether the type is a review report type, by type id (cache of isFullReportId()) */
    private static array $isFullReport = [];

    /** @var array<int, bool> whether the reports of the type count as review reports, by type id (cache of countsAsReportId()) */
    private static array $countsAsReport = [];

    /** @var array<int, bool> whether the reports of the type are passed on to the author, by type id (cache of visibleToAuthorId()) */
    private static array $visibleToAuthor = [];

    /**
     * @param ?Zend_Db_Adapter_Abstract $db defaults to the application database
     */
    public function __construct(?Zend_Db_Adapter_Abstract $db = null)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?RequestType
    {
        $db = $this->getDb();
        $row = $db->fetchRow($db->select()->from(self::TABLE_NAME)->where('id = ?', $id));

        return $row ? RequestType::fromRow($row) : null;
    }

    /**
     * The default type of the journal (one of each per journal)
     */
    public function findDefault(int $rvid, DefaultRequestType $defaultType): ?RequestType
    {
        $db = $this->getDb();
        $row = $db->fetchRow(
            $db->select()->from(self::TABLE_NAME)->where('rvid = ?', $rvid)->where('default_type = ?', $defaultType->value)
        );

        return $row ? RequestType::fromRow($row) : null;
    }

    /**
     * The review report type of the journal: the type of the requests made before the types existed
     */
    public function findFullReport(int $rvid): ?RequestType
    {
        return $this->findDefault($rvid, DefaultRequestType::FullReport);
    }

    /**
     * Id of the review report type of the journal, read once per request
     */
    public static function fullReportIdOf(int $rvid, ?self $repository = null): ?int
    {
        if (!array_key_exists($rvid, self::$fullReportIds)) {
            self::$fullReportIds[$rvid] = ($repository ?? new self())->findFullReport($rvid)?->getId();
        }

        return self::$fullReportIds[$rvid];
    }

    /**
     * Whether the type is a review report type, read once per request
     */
    public static function isFullReportId(int $id, ?self $repository = null): bool
    {
        if (!array_key_exists($id, self::$isFullReport)) {
            self::$isFullReport[$id] = (bool)($repository ?? new self())->findById($id)?->isFullReport();
        }

        return self::$isFullReport[$id];
    }

    /**
     * Whether the reports of the type count like review reports, read once per request
     */
    public static function countsAsReportId(int $id, ?self $repository = null): bool
    {
        if (!array_key_exists($id, self::$countsAsReport)) {
            self::$countsAsReport[$id] = (bool)($repository ?? new self())->findById($id)?->countsAsReport();
        }

        return self::$countsAsReport[$id];
    }

    /**
     * Whether the reports of the type are passed on to the author, read once per request; an unknown type is not
     */
    public static function visibleToAuthorId(int $id, ?self $repository = null): bool
    {
        if (!array_key_exists($id, self::$visibleToAuthor)) {
            self::$visibleToAuthor[$id] = (bool)($repository ?? new self())->findById($id)?->isVisibleToAuthor();
        }

        return self::$visibleToAuthor[$id];
    }

    /**
     * Forgets the values cached by fullReportIdOf(), isFullReportId(), countsAsReportId() and visibleToAuthorId() (tests,
     * creation of the default types, update of a type)
     */
    public static function clearCache(): void
    {
        self::$fullReportIds = [];
        self::$isFullReport = [];
        self::$countsAsReport = [];
        self::$visibleToAuthor = [];
    }

    /**
     * A type an editor of the journal may use for a new request: it belongs to the journal and is enabled
     */
    public function findUsable(int $id, int $rvid): ?RequestType
    {
        $type = $this->findById($id);

        return ($type !== null && $type->getRvid() === $rvid && $type->isEnabled()) ? $type : null;
    }

    /**
     * @return list<RequestType> ordered by position
     */
    public function findAllForJournal(int $rvid, bool $enabledOnly = false): array
    {
        $db = $this->getDb();
        $select = $db->select()
            ->from(self::TABLE_NAME)
            ->where('rvid = ?', $rvid)
            ->order('position ASC')
            ->order('id ASC');

        if ($enabledOnly) {
            $select->where('enabled = 1');
        }

        return array_map(static fn(array $row): RequestType => RequestType::fromRow($row), $db->fetchAll($select));
    }

    /**
     * Inserts a new type, or updates the type with the same id in the same journal
     * @return RequestType the saved type, with its id
     * @throws JsonException
     * @throws LogicException when the journal already has this default type, or the type belongs to another journal
     */
    public function save(RequestType $type): RequestType
    {
        $db = $this->getDb();
        $row = $type->toRow();
        $id = $type->getId();

        $defaultType = $type->getDefaultType();
        $sameDefault = $defaultType === null ? null : $this->findDefault($type->getRvid(), $defaultType);

        if ($sameDefault !== null && $sameDefault->getId() !== $id) {
            throw new LogicException(sprintf('The journal already has the default type "%s"', $defaultType->code()));
        }

        if ($id === null) {
            $db->insert(self::TABLE_NAME, $row);
            return $type->withId((int)$db->lastInsertId(self::TABLE_NAME, 'id'));
        }

        $existing = $this->findById($id);

        if ($existing === null || $existing->getRvid() !== $type->getRvid()) {
            throw new LogicException(sprintf('Unknown review request type #%d in this journal', $id));
        }

        // a type never changes journal, nor becomes or stops being a default type
        unset($row['default_type'], $row['rvid']);
        $db->update(self::TABLE_NAME, $row, ['id = ?' => $id]);
        unset(self::$countsAsReport[$id], self::$visibleToAuthor[$id]);

        return $type;
    }

    /**
     * Creates the default types missing in the journal
     * @return int the number of created types
     * @throws JsonException
     */
    public function createDefaults(int $rvid): int
    {
        $created = 0;

        foreach (RequestType::defaults($rvid) as $type) {
            if ($this->findDefault($rvid, $type->getDefaultType() ?? DefaultRequestType::FullReport) === null) {
                $this->save($type);
                $created++;
            }
        }

        if ($created > 0) {
            unset(self::$fullReportIds[$rvid]);
        }

        return $created;
    }

    /**
     * Whether a request was ever made with this type
     */
    public function isUsed(int $id): bool
    {
        $db = $this->getDb();

        return (bool)$db->fetchOne(
            $db->select()->from(self::ASSIGNMENTS_TABLE, ['ID'])->where('REQUEST_TYPE_ID = ?', $id)->limit(1)
        );
    }

    /**
     * Invitations of this type still waiting for an answer (latest row pending, not expired at $now) and sent with the
     * given mode of answer: they keep it when the option of the type changes
     * @param string $now 'Y-m-d H:i:s'
     */
    public function countPendingInvitations(int $id, bool $lightAnswer, string $now): int
    {
        $db = $this->getDb();
        $latestRow = $db->select()
            ->from(['l' => self::INVITATIONS_TABLE], [new Zend_Db_Expr('MAX(l.ID)')])
            ->where('l.AID = o.ID');

        return (int)$db->fetchOne(
            $db->select()
                ->from(['o' => self::ASSIGNMENTS_TABLE], [new Zend_Db_Expr('COUNT(*)')])
                ->join(['i' => self::INVITATIONS_TABLE], 'i.AID = o.ID', [])
                ->where('o.REQUEST_TYPE_ID = ?', $id)
                ->where('o.ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
                ->where('o.LIGHT_ANSWER = ?', (int)$lightAnswer)
                ->where('i.ID = (' . $latestRow . ')')
                ->where('i.STATUS = ?', Episciences_User_Invitation::STATUS_PENDING)
                ->where('i.EXPIRATION_DATE > ?', $now)
        );
    }

    /**
     * Deletes a type created by the journal and never used; a default type or a used type can only be disabled
     * @return bool false when the type is unknown, a default type, or used
     */
    public function delete(int $id, int $rvid): bool
    {
        $type = $this->findById($id);

        // the default types stay available to every journal: they can be disabled, not deleted
        if ($type === null || $type->getRvid() !== $rvid || $type->isDefault() || $this->isUsed($id)) {
            return false;
        }

        return $this->getDb()->delete(self::TABLE_NAME, ['id = ?' => $id, 'rvid = ?' => $rvid]) === 1;
    }

    private function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
    }
}
