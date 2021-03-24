<?php

class Episciences_TmpUsersManager 
{
	public static function findById($id)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(T_TMP_USER)->where('ID = ?', $id);
		$result = $db->fetchRow($sql);
		
		if ($result) {
			$user = new Episciences_User_Tmp($result);
			return $user;
		}
		
		return false;
	} 
	
}