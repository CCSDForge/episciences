<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 13/04/18
 * Time: 18:11
 */

class Episciences_Reviewer_PoolManager
{
    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */

    public static function updateUid(int $oldUid = 0, int $newUid = 0)
    {

            if($oldUid == 0 || $newUid == 0){
                return 0;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['UID'] = (int)$newUid;
            $where['UID = ?'] = (int)$oldUid;
            return $db->update(T_REVIEWER_POOL, $data, $where);
    }

}