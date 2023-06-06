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

    public const TABLE = T_REVIEWER_REPORTS;

    /**
     * Met à jour les UIDs de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
     * @throws Zend_Db_Statement_Exception
     */

    public static function updateUidS(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }

        $uidValues = [];
        $onBehalfUidValues = [];

        $rowInsert = 0;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $mUidSql = $db->select()->from(self::TABLE)->where('UID = ?', $oldUid);

        foreach ($db->fetchAll($mUidSql) as $row) {
            $uidValues[] = '(' . $row['ID'] . ',' . $newUid . ',' . $db->quote($row['ONBEHALF_UID']) . ',' . $row['DOCID'] . ',' . $row['STATUS'] . ',' . $db->quote($row['CREATION_DATE']) . ',' . $db->quote($row['UPDATE_DATE']) . ')';
        }

        if (!empty($uidValues)) {
            $rowInsert += self::updateProcessing($oldUid, $uidValues);
        }

        $mOnBehalfUidSql = $db->select()->from(self::TABLE)->where('ONBEHALF_UID = ?', $oldUid);

        foreach ($db->fetchAll($mOnBehalfUidSql) as $row) {
            $onBehalfUidValues[] = '(' . $row['ID'] . ',' . $row['UID'] . ',' . $newUid . ',' . $row['DOCID'] . ',' . $row['STATUS'] . ',' . $db->quote($row['CREATION_DATE']) . ',' . $db->quote($row['UPDATE_DATE']) . ')';
        }

        if (!empty($onBehalfUidValues)) {
            $rowInsert += self::updateProcessing($oldUid, $onBehalfUidValues, 'ONBEHALF_UID');
        }

        return $rowInsert;

    }

    /**
     * delete rating report
     * @param int $uid : reveiwer UID
     * @param int $docId
     * @return int
     */
    public static function deleteByUidAndDocId(int $uid, int $docId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->delete(T_REVIEWER_REPORTS, ['UID = ?' => $uid, 'DOCID = ?' => $docId]);
    }


    /**
     * @param int $docId
     * @param int $uid
     * @return bool
     */
    public static function renameGrid(int $docId, int $uid): bool
    {
        $gridPath = Episciences_PapersManager::buildDocumentPath($docId) . '/reports/';
        $nameDir = $gridPath . $uid;

        if (!is_dir($nameDir)) {
            error_log('The filename' . $nameDir . 'not exists or is not a directory');
            return false;
        }

        $newName = $gridPath . 'unassigned_reviewer_' . $uid . '_' . date("Y-m-d") . '_' . date("H:i:s") . '.save';

        if ($result = !rename($nameDir, $newName)) {
            error_log('Failed to rename ' . $nameDir . ' to ' . $newName);
            return $result;
        }

        return !$result;
    }

    /**
     * @param int $merger
     * @param array $values
     * @param string $whereFiled
     * @return int
     * @throws Zend_Db_Statement_Exception
     */
    private static function updateProcessing(int $merger, array $values, string $whereFiled = 'UID'): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where[$whereFiled . ' = ?'] = $merger;

        $db->delete(self::TABLE, $where);

        $sql = 'INSERT IGNORE INTO ';
        $sql .= $db->quoteIdentifier(self::TABLE);
        $sql .= ' (ID, UID, ONBEHALF_UID, DOCID, STATUS, CREATION_DATE, UPDATE_DATE) VALUES ';
        $sql .= implode(',', $values);

        $insert = $db->prepare($sql);

        try {
            $insert->execute();
        } catch (Exception $e) {
            $insert = null;
            trigger_error($e->getMessage(), E_USER_ERROR);
        }


        return ($insert) ? $insert->rowCount() : 0;

    }
}

