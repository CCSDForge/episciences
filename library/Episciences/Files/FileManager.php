<?php

namespace Episciences\Files;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;

class FileManager
{
    public const TABLE = T_FILES;
    public const DD_SOURCE = 'dd';


    public static function getById(int $fileId) : ?File {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = ($db?->fetchRow(self::getByFileIdQuery($fileId)));
        return $select ? new File($select) : null;
    }

    public static function getByFileIdQuery(int $fileId): ?Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(static::TABLE)->where('id= ?', $fileId);
    }

}