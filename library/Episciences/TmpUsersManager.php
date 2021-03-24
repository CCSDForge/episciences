<?php

class Episciences_TmpUsersManager 
{

    /**
     * @param int $id
     * @return Episciences_User_Tmp|false
     */
	public static function findById($id)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(T_TMP_USER)->where('ID = ?', (int) $id);
		$result = $db->fetchRow($sql);
		
		if ($result) {
            return new Episciences_User_Tmp($result);
		}
		
		return false;
	} 
	
}