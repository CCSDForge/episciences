<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 13/04/18
 * Time: 18:11
 */

class Episciences_Reviewer_PoolManager
{

    public const TABLE = T_REVIEWER_POOL;

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     * @throws Zend_Db_Statement_Exception
     */

    public static function updateUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }

        $values = [];
        $insert = null;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $mSql = $db->select()->from(self::TABLE)->where('UID = ?', $oldUid);

        foreach ($db->fetchAll($mSql) as $row) {
            $values[] = '(' . $row['RVID'] . ',' . $row['VID'] . ',' . $newUid . ')';
        }


        if (!empty($values)) {

            $where['UID = ?'] = $oldUid;
            $db->delete(self::TABLE, $where);

            $sql = 'INSERT IGNORE INTO ';
            $sql .= $db->quoteIdentifier(self::TABLE);
            $sql .= ' (RVID, VID, UID) VALUES ';
            $sql .= implode(',', $values);

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

}