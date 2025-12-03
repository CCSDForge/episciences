<?php

class Episciences_UsersManager
{
    public const VALID_USER = 1;
    /**
     * fetch review user list
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getAllUsers()
    {
        $localUsers = self::getLocalUsers();
        $casUsers = (!empty($localUsers)) ? self::getCasUsers(array_keys($localUsers)) : [];

        foreach ($localUsers as $key => $user) {
            if (array_key_exists($key, $casUsers)) {
                $localUsers[$key]['CAS'] = $casUsers[$key];
                $localUsers[$key]['isCasUserValid'] = (bool)$casUsers[$key]['VALID'];
                unset ($casUsers[$key]);
            }
        }

        return array('episciences' => $localUsers, 'CAS' => $casUsers);
    }

    /**
     * fetch review user list, filtered by role
     * @param null $with
     * @param null $without
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getUsersWithRoles($with = null, $without = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $users = [];

      $select = self::getUsersWithRolesQuery($with, $without);

        $result = $db->fetchCol($select);

        foreach ($result as $uid) {

            $uid = (int)$uid;

            $oUser = new Episciences_User();

            if (!$oUser->findWithCAS($uid)) {
                continue;
            }

            $oUser->loadRoles();
            $users[$uid] = $oUser;
        }

        return $users;
    }

    /**
     * @param null $with
     * @param null $without
     * @param bool $strict  (default: true: only activated accounts)
     * @return Zend_Db_Select
     */

    public static function getUsersWithRolesQuery($with = null, $without = null, bool $strict = true): \Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->distinct()
            ->from(array('u' => T_USERS), ['UID', 'SCREEN_NAME', 'LASTNAME']);

        if ($strict) {
            $select->where('IS_VALID = ?', self::VALID_USER);
        }

        $select->joinUsing(T_USER_ROLES, 'UID', array())
            ->where('RVID = ?', Episciences_Review::$_currentReviewId);

        if (is_array($with) && !empty($with)) {
            $select->where('ROLEID IN (?)', $with);
        } else if (!empty($with)) {
            $select->where('ROLEID = ?', $with);
        }

        if (is_array($without) && !empty($without)) {
            $select->where('ROLEID NOT IN (?)', $without);
        } else if (!empty($without)) {
            $select->where('ROLEID != ?', $without);
        }

        if ($with === Episciences_Acl::ROLE_REVIEWER) {
            $select->order('LASTNAME');
        } else {
            $select->order('SCREEN_NAME ASC');
        }

        return $select;
    }


    /**
     * fetch a list of all review users (users who have at least one role attached to the review)
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getLocalUsers(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $users = [];

        $select = $db->select()->distinct()->from(T_USER_ROLES, 'UID')->where('RVID = ?', RVID);
        $result = $db->fetchCol($select);

        foreach ($result as $uid) {

            $oUser = new Episciences_User();
            if (!$oUser->find($uid) || !$oUser->getIs_valid()) {
                continue;
            }

            $oUser->loadRoles();
            $users[$uid] = $oUser->toArray();
        }

        return ($users);
    }

    /**
     * fetch CAS users from db (can be filtered by uid)
     * @param null $uids
     * @return array
     */
    public static function getCasUsers($uids = null)
    {
        $casDb = Ccsd_Db_Adapter_Cas::getAdapter();
        $select = $casDb->select()->from('T_UTILISATEURS');

        if (is_array($uids) && !empty($uids)) {
            $select->where('UID IN (?)', $uids);
        }

        return ($casDb->fetchAssoc($select));
    }

    /**
     * assign users (reviewers or editors) to an item (paper, section or volume)
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public static function assign($ids, array $params)
    {
        return self::saveAssignment(Episciences_User_Assignment::STATUS_ACTIVE, $ids, $params);
    }

    /**
     * unassign users (reviewers or editors) from an item (paper, volume, or section)
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public static function unassign($ids, array $params)
    {
        return self::saveAssignment(Episciences_User_Assignment::STATUS_INACTIVE, $ids, $params);
    }

    private static function saveAssignment($status, $ids, array $params)
    {
        $assignments = array();

        // if no ids, do nothing
        if (empty($ids)) {
            return $assignments;
        }

        // prepare assignment values
        $assignmentValues = [
            'rvid' => $params['rvid'] ?? RVID,
            'itemid' => $params['itemid'],
            'item' => $params['item'],
            'roleid' => $params['roleid'],
            'tmp_user' => $params['tmp_user'] ??  0,
            'status' => $params['status'] ?? $status
        ];

        if ($status === Episciences_User_Assignment::STATUS_ACTIVE) {
            $assignmentValues['deadline'] = $params['deadline'] ?? null;
        }

        // if only one id was given, push it in an array for processing
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $uid) {
            $assignmentValues['uid'] = $uid;
            $oAssignment = new Episciences_User_Assignment($assignmentValues);
            $oAssignment->save();
            $assignments[] = $oAssignment;
        }

        return $assignments;
    }


    /**
     * sort an array of users according to their name
     * @param Episciences_User[] $users
     * @return array
     */
    public static function sortByName(array $users)
    {
        usort($users, static function ($a, $b) {
            /**
             * @var Episciences_User $a
             * @var Episciences_User $b
             */
            if ($a->getLastname() == $b->getLastname()) {
                if ($a->getFirstname() == $b->getFirstname()) {
                    return 0;
                }

                return ($a->getFirstname() > $b->getFirstname()) ? -1 : 1; // LOL
            }
            return ($a->getLastname() < $b->getLastname()) ? -1 : 1;
        });

        return $users;
    }

    /**
     * Met à jour l'UID du l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
     * @throws Zend_Db_Statement_Exception
     */

    public static function updateRolesUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }

        $values = [];
        $insert = null;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $mergerObject = new Episciences_User();
        $mergerObject->find($oldUid);
        $mergerRoles = $mergerObject->getAllRoles();

        foreach ($mergerRoles as $rvId => $roles) {
            $count = count($roles);
            foreach ($roles as $roleId) {
                if($count > 1 && $roleId === Episciences_Acl::ROLE_MEMBER){
                    continue;
                }
                $values[] = '(' . $newUid . ',' . $rvId . ',' . $db->quote($roleId) . ')';
            }
        }

        // update keeper roles
        if(!empty($values)){

            // delete merger roles
            $where['UID = ?'] = $oldUid;
            $db->delete(T_USER_ROLES, $where);

            $sql = 'INSERT INTO ';
            $sql .= $db->quoteIdentifier(T_USER_ROLES);
            $sql .= ' (`UID`, `RVID`, `ROLEID`) VALUES ';
            $sql .= implode(',', $values);
            $sql .= ' ON DUPLICATE KEY UPDATE ROLEID = VALUES(ROLEID)';

            $insert = $db->prepare($sql);

            try {
                $insert->execute();
            } catch (Exception $e) {
                $insert = null;
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return ($insert) ? $insert->rowCount() : 0;
    }


    /**
     * Supprime un utilisateur
     * @param int $uid
     * @return int
     */

    public static function removeUserUid(int $uid)
    {

        if ($uid == 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['UID = ?'] = $uid;
        return $db->delete(T_USERS, $where);
    }

    /**
     * Ajoute un user
     * @param int $uid
     * @param string $languageId
     * @param string $screenName
     * @return int
     */
    public static function insertLocalUser(int $uid = 0, string $languageId = '', string $screenName = '')
    {
        if (0 >= $uid || '' === $languageId || '' === $screenName) {
            return 0;
        }

        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            return $db->insert(T_USERS, ['UID' => $uid, 'LANGUEID' => $languageId, 'SCREEN_NAME' => $screenName]);

        } catch (Exception $e) {
            error_log('Insert In T_USERS : ' . $e->getMessage());
            return 0;
        }

    }

    /**
     * @param Episciences_User[] $users
     * @return array
     */
    public static function skipRootFullName(array $users)
    {
        $tmp = [];
        foreach ($users as $user) {
            if ($user->getUid() == 1) {
                continue;
            }
            $tmp[$user->getUid()] = $user->getFullName();
        }
        return $tmp;
    }

    /**
     * Checks if an editor is available
     * @param int $uid
     * @param int $rvid
     * @return bool
     */
    public static function isEditorAvailable(int $uid, int $rvid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_USER_ROLES, ['IS_AVAILABLE'])
            ->where('UID = ?', $uid)
            ->where('RVID = ?', $rvid)
            ->where('ROLEID IN (?)', [
                Episciences_Acl::ROLE_EDITOR,
                Episciences_Acl::ROLE_CHIEF_EDITOR,
                Episciences_Acl::ROLE_GUEST_EDITOR
            ]);

        $result = $db->fetchOne($select);

        // If IS_AVAILABLE is NULL or 1, consider as available
        // Only return false if IS_AVAILABLE is explicitly set to 0
        return $result !== '0' && $result !== 0;
    }

    /**
     * Sets the availability of an editor
     * @param int $uid
     * @param int $rvid
     * @param bool $isAvailable
     * @return int Number of affected rows
     * @throws Zend_Db_Adapter_Exception
     */
    public static function setEditorAvailability(int $uid, int $rvid, bool $isAvailable): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $data = ['IS_AVAILABLE' => $isAvailable ? 1 : 0];
        $where = [
            'UID = ?' => $uid,
            'RVID = ?' => $rvid,
            'ROLEID IN (?)' => [
                Episciences_Acl::ROLE_EDITOR,
                Episciences_Acl::ROLE_CHIEF_EDITOR,
                Episciences_Acl::ROLE_GUEST_EDITOR
            ]
        ];

        return $db->update(T_USER_ROLES, $data, $where);
    }
}
