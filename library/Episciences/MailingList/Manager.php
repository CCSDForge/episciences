<?php
declare(strict_types=1);
namespace Episciences\MailingList;

use Zend_Db_Table_Abstract;

class Manager
{
    public const TABLE_MAILING_LISTS = 'mailing_lists';
    public const TABLE_MAILING_LIST_USERS = 'mailing_list_users';
    public const TABLE_MAILING_LIST_ROLES = 'mailing_list_roles';

    public const MAX_MAILING_LISTS = 5;
    public const MAX_ROLES = 50;
    public const MAX_USERS = 500;

    /**
     * Cast numeric columns returned as strings by the DB adapter.
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function castRow(array $row): array
    {
        if (isset($row['id']))     $row['id']     = (int)$row['id'];
        if (isset($row['rvid']))   $row['rvid']   = (int)$row['rvid'];
        if (isset($row['status'])) $row['status'] = (int)$row['status'];
        return $row;
    }

    /**
     * @param int $rvid
     * @return MailingList[]
     */
    public static function getList(int $rvid): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(self::TABLE_MAILING_LISTS)
            ->where('rvid = ?', $rvid)
            ->order('name ASC');

        $rows = $db->fetchAll($select);
        $lists = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $lists[] = new MailingList(self::castRow($row));
        }
        return $lists;
    }

    /**
     * @param int $id
     * @return MailingList|null
     */
    public static function getById(int $id): ?MailingList
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Single round-trip: correlated JSON_ARRAYAGG subqueries avoid both the
        // N+1 pattern and the GROUP_CONCAT truncation risk (default max_len=1024).
        // Requires MySQL 5.7.22+ (available since MySQL 8.0 is the project baseline).
        // We use raw SQL here because ZF1's query builder has no native support for
        // correlated subqueries in the SELECT clause.
        $sql = 'SELECT ml.*,
                    (SELECT JSON_ARRAYAGG(uid)  FROM ' . self::TABLE_MAILING_LIST_USERS . ' WHERE list_id = ml.id) AS users_json,
                    (SELECT JSON_ARRAYAGG(role) FROM ' . self::TABLE_MAILING_LIST_ROLES . ' WHERE list_id = ml.id) AS roles_json
                FROM ' . self::TABLE_MAILING_LISTS . ' ml
                WHERE ml.id = ?';

        /** @var array<string, mixed>|false $row */
        $row = $db->fetchRow($sql, [$id]);
        if (!$row) {
            return null;
        }

        $list = new MailingList(self::castRow($row));

        /** @var array<int>|null $users */
        $users = json_decode((string)($row['users_json'] ?? 'null'), true);
        $list->setUsers(array_map('intval', $users ?? []));

        /** @var array<string>|null $roles */
        $roles = json_decode((string)($row['roles_json'] ?? 'null'), true);
        $list->setRoles(array_map('strval', $roles ?? []));

        return $list;
    }

    /**
     * @param MailingList $list
     * @return int
     * @throws \Exception
     */
    public static function save(MailingList $list): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();

        try {
            $data = $list->toArray();
            unset($data['id']);

            if ($list->getId()) {
                $db->update(self::TABLE_MAILING_LISTS, $data, ['id = ?' => $list->getId()]);
                $id = (int)$list->getId();
            } else {
                // Enforce list limit atomically within the transaction to prevent race conditions
                $currentCount = (int)$db->fetchOne(
                    $db->select()->from(self::TABLE_MAILING_LISTS, ['COUNT(*)'])->where('rvid = ?', $list->getRvid())
                );
                if ($currentCount >= self::MAX_MAILING_LISTS) {
                    throw new \OverflowException('Maximum number of mailing lists reached for this journal.');
                }
                $db->insert(self::TABLE_MAILING_LISTS, $data);
                $id = (int)$db->lastInsertId();
                if ($id === 0) {
                    throw new \RuntimeException('Failed to retrieve last insert ID after creating mailing list.');
                }
                $list->setId($id);
            }

            // Save users — delete then batch insert
            $db->delete(self::TABLE_MAILING_LIST_USERS, ['list_id = ?' => $id]);
            $users = $list->getUsers();
            if (!empty($users)) {
                $placeholders = implode(', ', array_fill(0, count($users), '(?, ?)'));
                $params = [];
                foreach ($users as $uid) {
                    $params[] = $id;
                    $params[] = (int)$uid;
                }
                $db->query(
                    'INSERT INTO ' . self::TABLE_MAILING_LIST_USERS . ' (list_id, uid) VALUES ' . $placeholders,
                    $params
                );
            }

            // Save roles — delete then batch insert
            $db->delete(self::TABLE_MAILING_LIST_ROLES, ['list_id = ?' => $id]);
            $roles = $list->getRoles();
            if (!empty($roles)) {
                $placeholders = implode(', ', array_fill(0, count($roles), '(?, ?)'));
                $params = [];
                foreach ($roles as $role) {
                    $params[] = $id;
                    $params[] = (string)$role;
                }
                $db->query(
                    'INSERT INTO ' . self::TABLE_MAILING_LIST_ROLES . ' (list_id, role) VALUES ' . $placeholders,
                    $params
                );
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $id;
    }

    /**
     * @param int $id
     * @return int Number of deleted mailing_lists rows (0 if not found, 1 on success)
     * @throws \Exception
     */
    public static function delete(int $id): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $db->delete(self::TABLE_MAILING_LIST_USERS, ['list_id = ?' => $id]);
            $db->delete(self::TABLE_MAILING_LIST_ROLES, ['list_id = ?' => $id]);
            $affected = $db->delete(self::TABLE_MAILING_LISTS, ['id = ?' => $id]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return $affected;
    }

    /**
     * Get the number of users for each role in a given journal.
     * @param int $rvid
     * @return array<string, int> Map of [role_id => user_count]
     */
    public static function getUserCountByRole(int $rvid): array
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_USER_ROLES, ['ROLEID', 'user_count' => 'COUNT(DISTINCT UID)'])
            ->where('RVID = ?', $rvid)
            ->group('ROLEID');

        $rows = $db->fetchAll($select);
        $counts = [];
        foreach ($rows as $row) {
            /** @var array{ROLEID: string, user_count: int|string} $row */
            $counts[$row['ROLEID']] = (int)$row['user_count'];
        }
        return $counts;
    }

    /**
     * Get member counts for all lists in a journal
     * @param int $rvid
     * @return array<int, int> Map of [list_id => member_count]
     */
    public static function getMemberCounts(int $rvid): array
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        
        // We use a subquery to resolve unique UIDs from both individual and role-based assignments
        $subSelect = $db->select()
            ->union([
                // Individual members
                $db->select()
                    ->from(self::TABLE_MAILING_LIST_USERS, ['list_id', 'uid']),
                // Role members
                $db->select()
                    ->from(['mlr' => self::TABLE_MAILING_LIST_ROLES], ['list_id'])
                    ->join(['ml' => self::TABLE_MAILING_LISTS], 'ml.id = mlr.list_id', [])
                    ->join(['ur' => T_USER_ROLES], 'ur.ROLEID = mlr.role AND ur.RVID = ml.rvid', ['uid' => 'ur.UID'])
            ]);

        $select = $db->select()
            ->from(['ml' => self::TABLE_MAILING_LISTS], ['id', 'member_count' => 'COUNT(DISTINCT members.uid)'])
            ->joinLeft(['members' => $subSelect], 'ml.id = members.list_id', [])
            ->where('ml.rvid = ?', $rvid)
            ->group('ml.id');

        $rows = $db->fetchAll($select);
        $counts = [];
        foreach ($rows as $row) {
            /** @var array{id: int|string, member_count: int|string} $row */
            $counts[(int)$row['id']] = (int)$row['member_count'];
        }
        return $counts;
    }

    /**
     * Get a mailing list by its full name (global check)
     * @param string $name
     * @return MailingList|null
     */
    public static function getByName(string $name): ?MailingList
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(self::TABLE_MAILING_LISTS)
            ->where('name = ?', $name);

        $row = $db->fetchRow($select);
        if (!$row) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return self::getById((int)$row['id']);
    }

    /**
     * Check if a journal exists with the given code
     * @param string $code
     * @param int|null $excludeRvid
     * @return bool
     */
    public static function journalCodeExists(string $code, ?int $excludeRvid = null): bool
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_REVIEW, ['RVID'])
            ->where('CODE = ?', $code);
        
        if ($excludeRvid !== null) {
            $select->where('RVID != ?', $excludeRvid);
        }

        return (bool)$db->fetchOne($select);
    }

    /**
     * Return all UIDs that have at least one role in the given journal.
     * Used to scope preview/manage actions to the current journal.
     * @param int $rvid
     * @return array<int>
     */
    public static function getJournalUids(int $rvid): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->distinct()
            ->from(T_USER_ROLES, ['UID'])
            ->where('RVID = ?', $rvid);

        /** @var array<int> $uids */
        $uids = array_map('intval', $db->fetchCol($select));
        return $uids;
    }

    /**
     * Ensure the mandatory journal list (rvcode@domain) exists, creating it if absent.
     * Safe to call on every page load: performs only one SELECT when the list already exists.
     * @param int $rvid
     * @param string $mandatoryName Full list name e.g. "dev@episciences.org"
     * @throws \Exception
     */
    public static function ensureMandatoryList(int $rvid, string $mandatoryName): void
    {
        if (self::getByName($mandatoryName) !== null) {
            return;
        }

        $list = new MailingList();
        $list->setRvid($rvid)
             ->setName($mandatoryName)
             ->setType('mailing_list_type_open')
             ->setStatus(1);

        self::save($list);
    }

    /**
     * Resolve all unique users in the mailing list (individual + roles)
     * @param MailingList $list
     * @return array<int, array<string, mixed>> Array of user data (firstname, lastname, email)
     */
    public static function resolveMembers(MailingList $list): array
    {
        $rvid = $list->getRvid();
        if ($rvid === null) {
            return [];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $uids = $list->getUsers();
        $roles = $list->getRoles();

        if (!empty($roles)) {
            $selectRoles = $db->select()
                ->distinct()
                ->from(T_USER_ROLES, ['UID'])
                ->where('RVID = ?', $rvid)
                ->where('ROLEID IN (?)', $roles);
            /** @var array<int> $roleUids */
            $roleUids = $db->fetchCol($selectRoles);
            $uids = array_unique(array_merge($uids, $roleUids));
        }

        if (empty($uids)) {
            return [];
        }

        // Fetch user details from local USER table
        // Note: we might need to join with CAS users if some info is missing, 
        // but typically Episciences USER table has cache of these fields.
        $selectUsers = $db->select()
            ->from(T_USERS, ['UID', 'FIRSTNAME', 'LASTNAME', 'EMAIL'])
            ->where('UID IN (?)', $uids)
            ->where('IS_VALID = 1')
            ->order(['LASTNAME ASC', 'FIRSTNAME ASC']);

        /** @var array<int, array<string, mixed>> $result */
        $result = $db->fetchAll($selectUsers);
        return $result;
    }
}
