<?php

namespace Episciences\MailingList;

use Zend_Db_Table_Abstract;

class Manager
{
    public const TABLE_MAILING_LISTS = 'mailing_lists';
    public const TABLE_MAILING_LIST_USERS = 'mailing_list_users';
    public const TABLE_MAILING_LIST_ROLES = 'mailing_list_roles';

    public const MAX_MAILING_LISTS = 5;

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
            $lists[] = new MailingList($row);
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
        $list = new MailingList($row);
        
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
     */
    public static function save(MailingList $list): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data = $list->toArray();
        unset($data['id']);

        if ($list->getId()) {
            $db->update(self::TABLE_MAILING_LISTS, $data, ['id = ?' => $list->getId()]);
            $id = (int)$list->getId();
        } else {
            $db->insert(self::TABLE_MAILING_LISTS, $data);
            $id = (int)$db->lastInsertId();
            $list->setId($id);
        }

        // Save users
        $db->delete(self::TABLE_MAILING_LIST_USERS, ['list_id = ?' => $id]);
        foreach ($list->getUsers() as $uid) {
            $db->insert(self::TABLE_MAILING_LIST_USERS, [
                'list_id' => $id,
                'uid' => $uid
            ]);
        }

        // Save roles
        $db->delete(self::TABLE_MAILING_LIST_ROLES, ['list_id = ?' => $id]);
        foreach ($list->getRoles() as $role) {
            $db->insert(self::TABLE_MAILING_LIST_ROLES, [
                'list_id' => $id,
                'role' => $role
            ]);
        }

        return $id;
    }

    /**
     * @param int $id
     * @return int
     */
    public static function delete(int $id): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete(self::TABLE_MAILING_LIST_USERS, ['list_id = ?' => $id]);
        $db->delete(self::TABLE_MAILING_LIST_ROLES, ['list_id = ?' => $id]);
        return $db->delete(self::TABLE_MAILING_LISTS, ['id = ?' => $id]);
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
     * Resolve all unique users in the mailing list (individual + roles)
     * @param MailingList $list
     * @return array<int, array<string, mixed>> Array of user data (firstname, lastname, email)
     */
    public static function resolveMembers(MailingList $list): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rvid = $list->getRvid();
        
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
