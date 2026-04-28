<?php

class Episciences_User_InvitationAnswersManager
{
	/**
	 * Récupère la réponse à une invitation
	 * @param int $id (de l'invitation)
	 * @return boolean|Episciences_User_InvitationAnswer
	 */
	public static function findById($id)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		// Load answers
		$sql = $db->select()->from(T_USER_INVITATION_ANSWER, '*')->where('ID = ?', $id);
		$data = $db->fetchRow($sql);
		
		if (empty($data)) {
			return false;
		}
		
		// Load details
		$sql = $db->select()->from(T_USER_INVITATION_ANSWER_DETAIL, array('name', 'value'))->where('ID = ?', $id);
		$details = $db->fetchPairs($sql);
		$data['details'] = $details;
		
		$oInvitation = new Episciences_User_InvitationAnswer($data);
		return $oInvitation;
	}
	
	public static function find(array $params)
	{
		if (empty($params)) {
			return false;
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$sql = $db->select()->from(T_USER_INVITATION_ANSWER, '*');
		foreach ($params as $param=>$value) {
			$sql->where("$param = ?", $value);
		}
		
		$data = $db->fetchRow($sql);
		
		if (empty($data)) {
			return false;
		}
		
		$oInvitation = new Episciences_User_InvitationAnswer($data);
		return $oInvitation;		
	}
	
}