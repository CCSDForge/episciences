<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use JsonException;
use LogicException;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Review request types of the journals (table review_request_type)
 */
class RequestTypeRepository
{
    public const TABLE_NAME = 'review_request_type';
    private const ASSIGNMENTS_TABLE = 'USER_ASSIGNMENT';

    private ?Zend_Db_Adapter_Abstract $db;

    /** @var array<int, int|null> id of the review report type, by journal (cache of fullReportIdOf()) */
    private static array $fullReportIds = [];

    /** @var array<int, bool> whether the type is a review report type, by type id (cache of isFullReportId()) */
    private static array $isFullReport = [];

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

    public function findByCode(int $rvid, string $code): ?RequestType
    {
        $db = $this->getDb();
        $row = $db->fetchRow(
            $db->select()->from(self::TABLE_NAME)->where('rvid = ?', $rvid)->where('code = ?', $code)
        );

        return $row ? RequestType::fromRow($row) : null;
    }

    /**
     * The review report type of the journal: the type of the requests made before the types existed
     */
    public function findFullReport(int $rvid): ?RequestType
    {
        return $this->findByCode($rvid, RequestType::CODE_FULL_REPORT);
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
     * Forgets the values cached by fullReportIdOf() and isFullReportId() (tests, creation of the default types)
     */
    public static function clearCache(): void
    {
        self::$fullReportIds = [];
        self::$isFullReport = [];
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
     * @throws LogicException when the code is already used in the journal, or the type belongs to another journal
     */
    public function save(RequestType $type): RequestType
    {
        $db = $this->getDb();
        $row = $type->toRow();
        $id = $type->getId();

        $sameCode = $this->findByCode($type->getRvid(), $type->getCode());

        if ($sameCode !== null && $sameCode->getId() !== $id) {
            throw new LogicException(sprintf('The code "%s" is already used in this journal', $type->getCode()));
        }

        if ($id === null) {
            $db->insert(self::TABLE_NAME, $row);
            return $type->withId((int)$db->lastInsertId(self::TABLE_NAME, 'id'));
        }

        $existing = $this->findById($id);

        if ($existing === null || $existing->getRvid() !== $type->getRvid()) {
            throw new LogicException(sprintf('Unknown review request type #%d in this journal', $id));
        }

        // the code identifies the mail template of the type: it never changes
        unset($row['code'], $row['rvid']);
        $db->update(self::TABLE_NAME, $row, ['id = ?' => $id]);

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
            if ($this->findByCode($rvid, $type->getCode()) === null) {
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
     * Deletes a type of the journal that was never used; a used type can only be disabled
     * @return bool false when the type is unknown, used, or the review report type
     */
    public function delete(int $id, int $rvid): bool
    {
        $type = $this->findById($id);

        if ($type === null || $type->getRvid() !== $rvid || $type->isFullReport() || $this->isUsed($id)) {
            return false;
        }

        return $this->getDb()->delete(self::TABLE_NAME, ['id = ?' => $id, 'rvid = ?' => $rvid]) === 1;
    }

    private function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
    }
}
