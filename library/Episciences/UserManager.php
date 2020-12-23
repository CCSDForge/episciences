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
     * Retourne le nombre de rapports de relecture deposés
     * @param $uid
     * @return mixed
     */

    public static function countRatings($uid){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_REVIEWER_REPORTS, ['stats_ratings_nbr' => 'COUNT(*)'])
            ->where('UID = ?', $uid)
            ->where('STATUS = ?', Episciences_Rating_Report::STATUS_COMPLETED);
        return $db->fetchRow($select);

    }

    /**
     * retourne le nombre d'invitation envoyées à un utilisateur
     * @param $uid
     * @return mixed
     */

    public static function countInvitations($uid){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->distinct('UID')
            ->from(['ua' => T_ASSIGNMENTS], ['stats_invitations_nbr' => 'COUNT(UID)'])
            ->join(['ui' => T_USER_INVITATIONS], 'ua.ID = ui.AID' )
            ->where('ua.UID = ?', $uid)
            ->where('ua.RVID = ?', RVID);
        return $db->fetchRow($select);
    }

    /**
     * get submitted DOCID query
     * @param int $uid
     * @return Zend_Db_Select
     */
    public static function getSubmittedPapersQuery(int $uid) : Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->select()
            ->from(T_PAPERS, 'DOCID')
            ->where('UID = ?', $uid)
            ->where('RVID = ?', RVID);
    }
}

