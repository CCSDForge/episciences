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
        $select = $db->select()
            ->from(self::TABLE_MAILING_LISTS)
            ->where('id = ?', $id);

        $row = $db->fetchRow($select);
        if (!$row) {
            return null;
        }

        /** @var array<string, mixed> $row */
        $list = new MailingList(self::castRow($row));

        // Three separate queries instead of a JOIN+aggregation: intentional.
        // A single JOIN would require GROUP_CONCAT or subqueries to reconstruct
        // two independent arrays (users, roles), which is harder to read and maintain
        // in ZF1's query builder. Given the volume cap (MAX_MAILING_LISTS=5 per journal),
        // the extra round-trips have no measurable impact.

        // Load users
        $selectUsers = $db->select()
            ->from(self::TABLE_MAILING_LIST_USERS, ['uid'])
            ->where('list_id = ?', $id);
        /** @var array<int> $users */
        $users = $db->fetchCol($selectUsers);
        $list->setUsers($users);

        // Load roles
        $selectRoles = $db->select()
            ->from(self::TABLE_MAILING_LIST_ROLES, ['role'])
            ->where('list_id = ?', $id);
        /** @var array<string> $roles */
        $roles = $db->fetchCol($selectRoles);
        $list->setRoles($roles);

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
