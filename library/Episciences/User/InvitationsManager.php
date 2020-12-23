<?php

class Episciences_User_InvitationsManager
{
	/**
	 * Récupère l'invitation faite à un utilisateur
	 * @param int $id (de l'invitation)
	 * @return boolean|Episciences_User_Invitation
	 */
	public static function findById($id)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		// Load from DB
		$sql = $db->select()
		->from(T_USER_INVITATIONS, '*')
		->where('ID = ?', $id);
		
		// Gets the result
		$data = $db->fetchRow($sql);
		
		if (empty($data)) {
			return false;
		}
		
		return new Episciences_User_Invitation($data);
	}
	
	public static function find(array $params)
	{
		if (empty($params)) {
			return false;
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$sql = $db->select()->from(T_USER_INVITATIONS, '*');
		foreach ($params as $param=>$value) {
			$sql->where("$param = ?", $value);
		}
		
		$data = $db->fetchRow($sql);
		
		if (empty($data)) {
			return false;
		}
		
		$oInvitation = new Episciences_User_Invitation($data);
		return $oInvitation;		
	}

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     */

    public static function updateSenderUid($oldUid = 0, $newUid = 0)
    {
        try{
            if($oldUid == 0 || $newUid == 0){
                return 0;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['UID'] = (int)$newUid;
            $where['UID = ?'] = (int)$oldUid;
            return $db->update(T_USER_INVITATIONS, $data, $where);
        } catch (Exception $e){
            return 0;
        }
    }


	
}