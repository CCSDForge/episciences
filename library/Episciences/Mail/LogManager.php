<?php

Class Episciences_Mail_LogManager
{

    /**
     * Delete all mail history of a paper by DOCID
     * @param int $docid
     * @return bool
     */
    public static function deleteByDocid(int $docid): bool
    {

        if ($docid < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        try {
            $db->delete(T_MAIL_LOG, ['DOCID = ?' => $docid]);
        } catch (Zend_Db_Statement_Exception $exception) {
            return false;
        }
        return true;
    }


}