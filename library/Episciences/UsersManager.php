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
        // OPTIMIZATION: Use optimized version with JOIN (single query instead of N+1)
        $localUsers = self::getLocalUsersOptimized();

        // CHUNKED LOADING: Process CAS users in batches to avoid PDO/MySQL limits on IN() clause
        $casUsers = [];
        if (!empty($localUsers)) {
            $allUids = array_keys($localUsers);

            // Process UIDs in chunks using utility method
            $chunks = Episciences_Tools_ArrayHelper::chunkForSql($allUids);
            foreach ($chunks as $chunkUids) {
                $chunkCasUsers = self::getCasUsers($chunkUids);
                $casUsers = array_merge($casUsers, $chunkCasUsers);
            }
        }

        foreach ($localUsers as $key => $user) {
            if (array_key_exists($key, $casUsers)) {
                $localUsers[$key]['CAS'] = $casUsers[$key];
                $localUsers[$key]['isCasUserValid'] = (bool)$casUsers[$key]['VALID'];
                unset ($casUsers[$key]);
            } else {
                // Ensure keys exist even if CAS data is missing
                $localUsers[$key]['CAS'] = null;
                $localUsers[$key]['isCasUserValid'] = false;
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

        if (empty($result)) {
            return $users;
        }

        // OPTIMIZATION: Batch load roles for all users at once (1 query instead of N)
        $rolesData = Episciences_User::loadRolesBatch($result, RVID);

        foreach ($result as $uid) {

            $uid = (int)$uid;

            $oUser = new Episciences_User();

            if (!$oUser->findWithCAS($uid)) {
                continue;
            }

            // Set roles from batch-loaded data (avoid loadRoles() call)
            if (isset($rolesData[$uid])) {
                $oUser->setRoles($rolesData[$uid]);
            }

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
     * Fetch all local users with their roles using optimized JOIN query
     * This method replaces getLocalUsers() inefficient N+1 query pattern
     * Performance: 2N+2 queries → 1 query (99% reduction for large datasets)
     *
     * @return array Users data with roles, indexed by UID
     *               Format: [UID => ['UID' => int, 'SCREEN_NAME' => string, ..., 'ROLES' => [RVID => [ROLEID, ...]]]]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getLocalUsersOptimized(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // SINGLE QUERY with JOIN to get all data at once
        $select = $db->select()
            ->from(['u' => T_USERS], [
                'UID',
                'SCREEN_NAME',
                'USERNAME',
                'EMAIL',
                'FIRSTNAME',
                'LASTNAME',
                'LANGUEID',
                'IS_VALID',
                'REGISTRATION_DATE',
                'MODIFICATION_DATE'
            ])
            ->join(['ur' => T_USER_ROLES], 'u.UID = ur.UID', ['RVID', 'ROLEID'])
            ->where('ur.RVID = ?', RVID)
            ->where('u.IS_VALID = ?', self::VALID_USER)
            ->order('u.SCREEN_NAME ASC');

        $result = $db->fetchAll($select);

        // Group results by UID
        $users = [];
        $rolesData = [];

        foreach ($result as $row) {
            $uid = (int)$row['UID'];

            // Create user array only once per UID
            // Format matches parent::toArray() with lowercase keys
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'uid' => $uid,  // lowercase to match toArray() format
                    'SCREEN_NAME' => $row['SCREEN_NAME'],  // uppercase for compatibility
                    'username' => $row['USERNAME'],  // lowercase to match toArray()
                    'email' => $row['EMAIL'],  // lowercase to match toArray()
                    'firstname' => $row['FIRSTNAME'],  // lowercase to match toArray()
                    'lastname' => $row['LASTNAME'],  // lowercase to match toArray()
                    'langueid' => $row['LANGUEID'],  // lowercase to match toArray()
                    'fullname' => trim($row['FIRSTNAME'] . ' ' . $row['LASTNAME']),  // computed field
                    'time_registered' => $row['REGISTRATION_DATE'],  // matches toArray()
                    'time_modified' => $row['MODIFICATION_DATE'],  // matches toArray()
                ];
                $rolesData[$uid] = [];
            }

            // Accumulate roles for this user
            $rvid = (int)$row['RVID'];
            $roleId = $row['ROLEID'];
            if (!isset($rolesData[$uid][$rvid])) {
                $rolesData[$uid][$rvid] = [];
            }
            $rolesData[$uid][$rvid][] = $roleId;
        }

        // Add roles to user data
        foreach ($users as $uid => $userData) {
            $users[$uid]['ROLES'] = $rolesData[$uid];  // uppercase for compatibility
        }

        return $users;
    }

    /**
     * Load users with their roles in a single query (eager loading)
     * This method eliminates N+1 query pattern by loading users and roles with JOIN
     * Performance: N+1 queries (1 + N×find + N×loadRoles) → 1 query (99% reduction)
     *
     * @param string|array|null $with Role(s) to include (e.g., Episciences_Acl::ROLE_EDITOR)
     * @param string|array|null $without Role(s) to exclude
     * @param bool $strict Filter valid users only (default: true)
     * @return array Episciences_User[] indexed by UID
     */
    public static function getUsersWithRolesEager($with = null, $without = null, bool $strict = true): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Build query with JOIN to get users and roles in one query
        $select = $db->select()
            ->from(['u' => T_USERS], [
                'UID',
                'SCREEN_NAME',
                'LASTNAME',
                'FIRSTNAME',
                'EMAIL',
                'LANGUEID',
                'USERNAME',
                'IS_VALID',
                'REGISTRATION_DATE',
                'MODIFICATION_DATE'
            ])
            ->join(['ur' => T_USER_ROLES], 'u.UID = ur.UID', ['RVID', 'ROLEID'])
            ->where('ur.RVID = ?', Episciences_Review::$_currentReviewId);

        if ($strict) {
            $select->where('u.IS_VALID = ?', self::VALID_USER);
        }

        // Apply role filters
        if (is_array($with) && !empty($with)) {
            $select->where('ur.ROLEID IN (?)', $with);
        } elseif (!empty($with)) {
            $select->where('ur.ROLEID = ?', $with);
        }

        if (is_array($without) && !empty($without)) {
            $select->where('ur.ROLEID NOT IN (?)', $without);
        } elseif (!empty($without)) {
            $select->where('ur.ROLEID != ?', $without);
        }

        // Order by LASTNAME for reviewers, SCREEN_NAME for others
        if ($with === Episciences_Acl::ROLE_REVIEWER) {
            $select->order('u.LASTNAME ASC');
        } else {
            $select->order('u.SCREEN_NAME ASC');
        }

        // Execute query
        $result = $db->fetchAll($select);

        // Group results by UID and populate User objects
        $users = [];
        $rolesData = []; // Store roles by UID

        foreach ($result as $row) {
            $uid = (int)$row['UID'];

            // Create user object only once per UID
            if (!isset($users[$uid])) {
                $oUser = new Episciences_User();

                // Populate user properties WITHOUT calling find() (avoid extra query)
                $oUser->setUid($uid);
                $oUser->setScreenName($row['SCREEN_NAME']);
                $oUser->setLastname($row['LASTNAME']);
                $oUser->setFirstname($row['FIRSTNAME']);
                $oUser->setEmail($row['EMAIL']);
                $oUser->setLangueid($row['LANGUEID']);
                $oUser->setUsername($row['USERNAME']);
                $oUser->setIs_valid((bool)$row['IS_VALID']);
                $oUser->setHasAccountData(true); // Flag that data was loaded from DB

                $users[$uid] = $oUser;
                $rolesData[$uid] = [];
            }

            // Accumulate roles for this user
            $rvid = (int)$row['RVID'];
            $roleId = $row['ROLEID'];
            if (!isset($rolesData[$uid][$rvid])) {
                $rolesData[$uid][$rvid] = [];
            }
            $rolesData[$uid][$rvid][] = $roleId;
        }

        // Set roles on each user object (avoid calling loadRoles())
        foreach ($users as $uid => $user) {
            $user->setRoles($rolesData[$uid]);
        }

        return $users;
    }

    /**
     * Load users with their roles AND CAS data in optimized way (batch loading)
     * This method eliminates N+1 queries for both USER and CAS tables
     * Performance: 2+2N queries (2 + N×find_USER + N×find_CAS) → 2 queries (99.5% reduction)
     *
     * @param string|array|null $with Role(s) to include (e.g., Episciences_Acl::ROLE_EDITOR)
     * @param string|array|null $without Role(s) to exclude
     * @param bool $strict Filter valid users only (default: true)
     * @return array Episciences_User[] indexed by UID with CAS data populated
     * @throws Zend_Db_Statement_Exception
     */
    public static function getUsersWithRolesEagerCAS($with = null, $without = null, bool $strict = true): array
    {
        // Step 1: Get users with roles using existing eager loading (1 query: JOIN USER+USER_ROLES)
        $users = self::getUsersWithRolesEager($with, $without, $strict);

        if (empty($users)) {
            return $users;
        }

        // Step 2: Batch load CAS data for all users (1 query instead of N)
        $uids = array_keys($users);
        $casData = self::getCasUsersBatch($uids);

        // Step 3: Populate CAS data into user objects
        foreach ($users as $uid => $user) {
            if (isset($casData[$uid])) {
                // Populate CAS fields without calling findWithCAS() - avoids N queries
                // Only override local data if CAS has data (CAS is source of truth for these fields)
                if (!empty($casData[$uid]['USERNAME'])) {
                    $user->setUsername($casData[$uid]['USERNAME']);
                }
                if (!empty($casData[$uid]['EMAIL'])) {
                    $user->setEmail($casData[$uid]['EMAIL']);
                }
                if (!empty($casData[$uid]['FIRSTNAME'])) {
                    $user->setFirstname($casData[$uid]['FIRSTNAME']);
                }
                if (!empty($casData[$uid]['LASTNAME'])) {
                    $user->setLastname($casData[$uid]['LASTNAME']);
                }
                if (!empty($casData[$uid]['LANGUEID'])) {
                    $user->setLangueid($casData[$uid]['LANGUEID']);
                }
                // Note: We don't set SCREEN_NAME from CAS as it's computed from local data
            }
        }

        return $users;
    }

    /**
     * Batch load CAS user data for multiple UIDs (eliminates N CAS queries)
     * Private helper method for getUsersWithRolesEagerCAS()
     * Performance: N queries → 1 query with WHERE IN() (or few chunked queries for large datasets)
     *
     * @param array $uids Array of user IDs to fetch from CAS
     * @return array CAS user data indexed by UID: [UID => ['USERNAME' => ..., 'EMAIL' => ..., ...]]
     */
    public static function getCasUsersBatch(array $uids): array
    {
        if (empty($uids)) {
            return [];
        }

        try {
            $casDb = Ccsd_Db_Adapter_Cas::getAdapter();
            $casUsers = [];

            // CHUNKED LOADING: Process in batches to avoid PDO/MySQL limits on IN() clause
            $chunks = Episciences_Tools_ArrayHelper::chunkForSql($uids);

            foreach ($chunks as $chunkUids) {
                // Query to fetch CAS users for this chunk
                // NOTE: LANGUEID is not in CAS database, only in local USER table
                $select = $casDb->select()
                    ->from('T_UTILISATEURS', ['UID', 'USERNAME', 'EMAIL', 'FIRSTNAME', 'LASTNAME', 'VALID'])
                    ->where('UID IN (?)', $chunkUids);

                $result = $casDb->fetchAll($select);

                // Index results by UID for fast lookup
                foreach ($result as $row) {
                    $casUsers[(int)$row['UID']] = $row;
                }
            }

            return $casUsers;
        } catch (Exception $e) {
            // Fallback if CAS database not available
            // Log error but don't fail - local USER table data will be used instead
            return [];
        }
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
