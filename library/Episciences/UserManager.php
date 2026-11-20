<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 12/09/18
 * Time: 17:17
 */

class Episciences_UserManager
{
    /**
     * get submitted DOCID query
     * @param int $uid
     * @return Zend_Db_Select
     */
    public static function getSubmittedPapersQuery(int $uid): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()
            ->from(T_PAPERS, 'DOCID')
            ->where('UID = ?', $uid)
            ->where('RVID = ?', RVID);
    }

    public static function getCorrespondingUidFromUuid(string $uuid = '', ?int $rvId = null): int
    {

        if (empty($uuid)){
            return 0;
        }

        if (!$rvId && !Ccsd_Tools::isFromCli()) {
            $rvId = RVID;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $query = $db?->select()
            ->distinct()
            ->from(['u' => T_USERS], 'u.UID')
            ->join(['ur' => T_USER_ROLES], 'u.UID = ur.UID', 'ur.UID')
            ->where('u.uuid = ?', $uuid);

        if ($rvId) {
            $query->where('ur.RVID = ?', $rvId);
        }

        return (int)$db?->fetchone($query);

    }



    public static function getUuidFromUid(int $uid, ?int $rvId = null): ?string
    {

        if (empty($uid)){
            return null;
        }

        if (!$rvId && !Ccsd_Tools::isFromCli()) {
            $rvId = RVID;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $query = $db?->select()
            ->from(['u' => T_USERS], 'u.uuid')
            ->join(['ur' => T_USER_ROLES], 'u.UID = ur.UID', 'ur.UID')
            ->where('u.UID = ?', $uid);

        if ($rvId) {
            $query->where('ur.RVID = ?', $rvId);
        }

        return $db?->fetchone($query);

    }
}

