<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 13/04/18
 * Time: 17:08
 */
// La classe Report.php contient plus de 20 méthodes...
//TODO certaines methodes de cette dernière à déplacer ICI ?

class Episciences_Rating_ReportManager
{

    /**
     * Met à jour les UIDs de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */

    public static function updateUidS(int $oldUid = 0, int $newUid = 0)
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->update(T_REVIEWER_REPORTS, ['UID' => (int)$newUid], ['UID = ?' => (int)$oldUid]);
        $rows += $db->update(T_REVIEWER_REPORTS, ['ONBEHALF_UID' => (int)$newUid], ['ONBEHALF_UID = ?' => (int)$oldUid]);
        return $rows;
    }

    /**
     * delete rating report
     * @param int $uid : reveiwer UID
     * @return int
     */
    public static function deleteByUid(int $uid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->delete(T_REVIEWER_REPORTS, ['UID = ?' => $uid]);
    }


    /**
     * @param int $docId
     * @param int $uid
     * @return bool
     */
    public  static function renameGrid(int $docId, int $uid): bool
    {
        $gridPath = REVIEW_FILES_PATH . $docId . '/reports/';
        $nameDir = $gridPath . $uid;

        if (!is_dir($nameDir)) {
            error_log('The filename' . $nameDir . 'not exists or is not a directory');
            return false;
        }

        $newName = $gridPath . 'unassigned_reviewer_' . $uid . '_' . date("Y-m-d") . '_' . date("H:i:s") . '.save';

        if($result = !rename($nameDir, $newName)){
            error_log('Failed to rename ' . $nameDir . ' to ' . $newName);
            return $result;
        }

        return !$result;
    }
}

