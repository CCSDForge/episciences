<?php

class Episciences_User_AssignmentsManager
{
    /**
     * fetch user assignments list (default: only fetch most recent assignment for each user)
     * @param array $params
     * @param bool $fetchLastOnly
     * @return array|Episciences_User_Assignment[]
     */
    public static function getList(array $params, $fetchLastOnly = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $subquery = $db->select()
            ->from(T_ASSIGNMENTS, array('ITEMID', 'MAX(`WHEN`) AS WHEN'))
            ->group('ITEMID');

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
            $select->join(array('b' => $subquery), 'a.ITEMID = b.ITEMID AND a.`WHEN` = b.`WHEN`', array());
        }

        $result = array();
        $data = $db->fetchAssoc($select);

        foreach ($data as $assignment) {
            $oAssignment = new Episciences_User_Assignment($assignment);
            $result[$oAssignment->getItemid()] = $oAssignment;
        }

        return $result;
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

        if (null == $sql = self::findQuery($params, $db = Zend_Db_Table_Abstract::getDefaultAdapter())) {
            return false;
        }

        $data = $db->fetchRow($sql);

        if (empty($data)) {
            return false;
        }

        return new Episciences_User_Assignment($data);
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
        return $db->update(T_ASSIGNMENTS, $data, $where);
    }

    /**
     * Retourne toutes les assignations
     * @param $params
     * @return array|bool
     */
    public static function findAll($params)
    {
        $sql = self::findQuery($params, $db = Zend_Db_Table_Abstract::getDefaultAdapter());

        /** @var  Episciences_User_Assignment[] $assignments */
        $assignments = [];

        if (null === $sql) {
            return false;
        }

        foreach ($db->fetchAll($sql) as $value) {
            $assignments[] = new Episciences_User_Assignment($value);
        }

        return $assignments;
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

        // Returns true if at least one row was deleted
        return $db->delete(T_ASSIGNMENTS, $where) > 0;
    }

    public static function reassignPaperCoAuthors(array $coAuthors, $newPaper) {
        if (!empty($coAuthors)) {
            foreach ($coAuthors as $coAuthor) {
                /** @var Episciences_User $coAuthor */
                /** @var Episciences_Paper $newPaper */
                $assignment = new Episciences_User_Assignment();
                $assignment->setRvid(RVID);
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
