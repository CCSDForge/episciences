<?php

class Episciences_Website_Navigation_NavigationManager
{
    public static function fetchByClassName(string $className): string{
        $db = Zend_Db_Table_Abstract::getDefaultAdapter ();
        $sql = $db->select()->from('WEBSITE_NAVIGATION', 'PARAMS' )->where('SID = ?', RVID)->where('TYPE_PAGE = ?', $className);
        return $db->fetchOne($sql);
}

}
