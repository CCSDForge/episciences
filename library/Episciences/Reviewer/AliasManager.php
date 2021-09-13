<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 13/04/18
 * Time: 18:17
 */

class Episciences_Reviewer_AliasManager
{
    public const TABLE = T_ALIAS;

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
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

        $merger = new Episciences_User();
        $merger->find($oldUid);
        $mergerAliases = $merger->getAliases();

        foreach ($mergerAliases as $docId => $alias) {
            $values[] = '(' . $newUid . ',' . $docId . ',' . $alias . ')';
        }

        // update keeper aliases
        if (!empty($values)) {

            // delete merger aliases
            $where['UID = ?'] = $oldUid;
            $db->delete(self::TABLE, $where);

            $sql = 'INSERT INTO ';
            $sql .= $db->quoteIdentifier(self::TABLE);
            $sql .= ' (`UID`, `DOCID`, `ALIAS`) VALUES ';
            $sql .= implode(',', $values);
            $sql .= ' ON DUPLICATE KEY UPDATE ALIAS = VALUES(ALIAS)';

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