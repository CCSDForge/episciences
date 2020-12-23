<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 13/04/18
 * Time: 18:17
 */

class Episciences_Reviewer_AliasManager
{
    const TABLE = T_ALIAS;

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */

    public static function updateUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data['UID'] = $newUid;
        $where['UID = ?'] = $oldUid;
        return $db->update(self::TABLE, $data, $where);
    }

    /**
     * delete reviewer(s) alias(es)
     * @param int $docId
     * @param int|null $uid [$uid === null : remove all reviewers aliases]
     * @return int
     */
    public static function delete(int $docId, int $uid = null): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = ['DOCID = ?' => $docId];

        if (null !== $uid) {
            $where = array_merge($where, [' UID = ?' => $uid]);
        }

        return $db->delete(self::TABLE, $where);
    }

}