<?php

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class Episciences_User_AssignmentsManager
{
    private static ?CacheItemPoolInterface $_cachePool = null;

    /**
     * Set the cache pool (useful for dependency injection in tests)
     */
    public static function setCachePool(CacheItemPoolInterface $cachePool): void
    {
        self::$_cachePool = $cachePool;
    }

    /**
     * Get the cache pool (ArrayAdapter by default)
     */
    public static function getCachePool(): CacheItemPoolInterface
    {
        if (self::$_cachePool === null) {
            self::$_cachePool = new ArrayAdapter();
        }
        return self::$_cachePool;
    }

    /**
     * fetch user assignments list (default: only fetch most recent assignment for each user)
     * @param array $params
     * @param bool $fetchLastOnly
     * @return array|Episciences_User_Assignment[]
     */
    public static function getList(array $params, $fetchLastOnly = true)
    {
        $cachePool = self::getCachePool();
        $cacheKey = 'assignments_list_' . md5(serialize($params) . '_' . ($fetchLastOnly ? '1' : '0'));
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            // the latest assignment to each item (highest ID: two assignments can have the same date);
            // a reviewer may hold one request of each type on a paper (#319): the latest row of each request
            $subquery = $db->select()
                ->from(T_ASSIGNMENTS, array('LAST_ID' => new Zend_Db_Expr('MAX(ID)')))
                ->group(self::isReviewerQuery($params) ? ['ITEMID', 'REQUEST_TYPE_ID'] : 'ITEMID');

            $select = $db->select()
                ->from(array('a' => T_ASSIGNMENTS), '*');


            foreach ($params as $param => $value) {
                if (is_array($value)) {
                    if (strtolower($param) !== 'status') {
                        $subquery->where("$param IN (?)", $value);
                    }
                    $select->where("$param IN (?)", $value);
                } else {
                    if (strtolower($param) !== 'status') {
                        $subquery->where("$param = ?", $value);
                    }
                    $select->where("$param = ?", $value);
                }
            }

            if ($fetchLastOnly) {
                $select->join(array('b' => $subquery), 'a.ID = b.LAST_ID', array());
            }

            $result = array();
            $data = $db->fetchAssoc($select);

            foreach ($data as $assignment) {
                $oAssignment = new Episciences_User_Assignment($assignment);
                $itemId = $oAssignment->getItemid();

                // several requests on the same item: the most advanced one represents it
                if (!isset($result[$itemId]) || self::statusRank($oAssignment) > self::statusRank($result[$itemId])) {
                    $result[$itemId] = $oAssignment;
                }
            }

            $cacheItem->set($result);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function isReviewerQuery(array $params): bool
    {
        foreach ($params as $param => $value) {
            if (strtolower((string)$param) === 'roleid') {
                return $value === Episciences_User_Assignment::ROLE_REVIEWER;
            }
        }

        return false;
    }

    private static function statusRank(Episciences_User_Assignment $assignment): int
    {
        return match ($assignment->getStatus()) {
            Episciences_User_Assignment::STATUS_ACTIVE => 2,
            Episciences_User_Assignment::STATUS_PENDING => 1,
            default => 0,
        };
    }

    /**
     * Types of the requests a reviewer holds on a paper: those whose latest row is pending or active (#319)
     * @param bool $withExpiredInvitations false: a pending request whose invitation has expired is not in progress
     *                                     (the reviewer can be invited again)
     * @return list<int|null> null for a request saved without a type
     */
    public static function currentReviewerRequestTypeIds(int $uid, int $docId, bool $isTmpUser = false, bool $withExpiredInvitations = true): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = self::requestsInProgressQuery($db, $docId, $isTmpUser, $withExpiredInvitations, $uid);

        return array_map(
            static fn(array $row): ?int => $row['REQUEST_TYPE_ID'] === null ? null : (int)$row['REQUEST_TYPE_ID'],
            $db->fetchAll($select)
        );
    }

    /**
     * Types of the requests in progress on a paper, for each reviewer with an account (#319): a single query, so that
     * the invitation form does not depend on the (heavier) list of the invitations of the paper.
     * A pending request whose invitation has expired is not in progress.
     * @return array<int, list<int>> request type ids, by reviewer UID
     */
    public static function requestTypesInProgressByReviewer(int $docId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $result = [];

        foreach ($db->fetchAll(self::requestsInProgressQuery($db, $docId, false, false)) as $row) {
            if ($row['REQUEST_TYPE_ID'] !== null) {
                $result[(int)$row['UID']][] = (int)$row['REQUEST_TYPE_ID'];
            }
        }

        return $result;
    }

    /**
     * Latest row of each reviewer request on a paper, when it is pending or active
     */
    private static function requestsInProgressQuery(
        Zend_Db_Adapter_Abstract $db,
        int $docId,
        bool $isTmpUser,
        bool $withExpiredInvitations,
        ?int $uid = null
    ): Zend_Db_Select {
        $latest = $db->select()
            ->from(T_ASSIGNMENTS, ['LAST_ID' => new Zend_Db_Expr('MAX(ID)')])
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('ITEMID = ?', $docId)
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->where('TMP_USER = ?', (int)$isTmpUser)
            ->group(['UID', 'REQUEST_TYPE_ID']);

        if ($uid !== null) {
            $latest->where('UID = ?', $uid);
        }

        $select = $db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'REQUEST_TYPE_ID'])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', []);

        if ($withExpiredInvitations) {
            return $select->where('a.STATUS IN (?)', [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING]);
        }

        // an assignment without invitation (added by an editor) has no expiry
        return $select
            ->joinLeft(['i' => T_USER_INVITATIONS], 'i.ID = a.INVITATION_ID', [])
            ->where(sprintf(
                '(%s OR (%s AND (i.EXPIRATION_DATE IS NULL OR i.EXPIRATION_DATE > NOW())))',
                $db->quoteInto('a.STATUS = ?', Episciences_User_Assignment::STATUS_ACTIVE),
                $db->quoteInto('a.STATUS = ?', Episciences_User_Assignment::STATUS_PENDING)
            ));
    }

    /**
     * @param $id
     * @return bool|Episciences_User_Assignment
     */
    public static function findById($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        return self::find(array('ID' => $id));
    }

    /**
     * @param array $params
     * @return bool|Episciences_User_Assignment
     */
    public static function find(array $params)
    {
        $cachePool = self::getCachePool();
        $cacheKey = 'assignments_find_' . md5(serialize($params));
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $sql = self::findQuery($params, $db = Zend_Db_Table_Abstract::getDefaultAdapter());

            if ($sql === null) {
                $result = false;
            } else {
                $data = $db->fetchRow($sql);
                $result = empty($data) ? false : new Episciences_User_Assignment($data);
            }

            $cacheItem->set($result);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */

    public static function updateUid(int $oldUid = 0, int $newUid = 0)
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data['UID'] = $newUid;
        $where['UID = ?'] = $oldUid;
        $affected = $db->update(T_ASSIGNMENTS, $data, $where);
        if ($affected > 0) {
            self::getCachePool()->clear();
        }
        return $affected;
    }

    /**
     * Retourne toutes les assignations
     * @param $params
     * @return array|bool
     */
    public static function findAll($params)
    {
        $cachePool = self::getCachePool();
        $cacheKey = 'assignments_findall_' . md5(serialize($params));
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $sql = self::findQuery($params, $db = Zend_Db_Table_Abstract::getDefaultAdapter());

            if ($sql === null) {
                $result = false;
            } else {
                /** @var Episciences_User_Assignment[] $result */
                $result = [];
                foreach ($db->fetchAll($sql) as $value) {
                    $result[] = new Episciences_User_Assignment($value);
                }
            }

            $cacheItem->set($result);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * @param array $params
     * @param Zend_Db_Adapter_Abstract $db
     * @return Zend_Db_Select|null
     */
    private static function findQuery(array $params, Zend_Db_Adapter_Abstract $db)
    {
        if (null === $db || !is_array($params) || empty($params)) {
            return null;
        }

        $sql = $db->select()->from(T_ASSIGNMENTS, '*');

        foreach ($params as $param => $value) {
            if (is_array($value)) {
                $sql->where("$param IN (?)", $value);
            } else {
                $sql->where("$param = ?", $value);
            }

        }

        $sql->order('ID DESC');

        return $sql;
    }

    /**
     * Delete assignments by ID or by criteria
     *
     * Usage examples:
     *   - By ID: removeAssignment(123)
     *   - By criteria: removeAssignment([
     *       'ITEM = ?' => Episciences_User_Assignment::ITEM_SECTION,
     *       'ITEMID = ?' => 5,
     *       'RVID = ?' => 3
     *     ])
     *
     * @param int|array $criteria Assignment ID (int) or WHERE conditions (array)
     * @return bool true if at least one row was deleted, false otherwise
     */
    public static function removeAssignment(int|array $criteria): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Delete by assignment ID
        if (is_int($criteria)) {
            if ($criteria < 1) {
                return false;
            }
            $where = ['ID = ?' => $criteria];
        } else {
            // Delete by criteria (e.g., item type, item ID, review ID)
            if (empty($criteria)) {
                return false;
            }
            $where = $criteria;
        }

        $affected = $db->delete(T_ASSIGNMENTS, $where) > 0;
        if ($affected) {
            self::getCachePool()->clear();
        }
        return $affected;
    }

    public static function reassignPaperCoAuthors(array $coAuthors, $newPaper) {
        if (!empty($coAuthors)) {
            foreach ($coAuthors as $coAuthor) {
                /** @var Episciences_User $coAuthor */
                /** @var Episciences_Paper $newPaper */
                $assignment = new Episciences_User_Assignment();
                // Not the RVID constant: it is not defined in CLI (COAR Notify inbox)
                $assignment->setRvid($newPaper->getRvid());
                $assignment->setItemid($newPaper->getDocid());
                $assignment->setItem('paper');
                $assignment->setUid($coAuthor->getUid());
                $assignment->setRoleid(Episciences_Acl::ROLE_CO_AUTHOR);
                $assignment->setStatus(Episciences_User_Assignment::STATUS_ACTIVE);
                $assignment->save();
            }
        }
    }

}
