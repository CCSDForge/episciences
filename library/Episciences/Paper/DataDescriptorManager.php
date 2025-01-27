<?php

namespace Episciences\Paper;


use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

class DataDescriptorManager
{
    public const TABLE = T_PAPER_DATA_DESCRIPTOR;


    public static function getByDocId(int $docId): ?DataDescriptor
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = ($db?->fetchRow(self::getByDocIdQuery($docId)));

        if ($select) {
            $dd = new DataDescriptor($select);
            $dd->loadFile();
            return $dd;
        }
        return null;
    }

    public static function getByDocIdQuery(int $docId): ?Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(static::TABLE)->where('docid = ?', $docId);
    }

}