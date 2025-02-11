<?php

namespace Episciences\Paper;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;

class DataDescriptorManager
{
    public const TABLE = T_PAPER_DATA_DESCRIPTOR;


    public static function getByDocId(int $docId): ?array
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = ($db?->fetchAssoc(self::getByDocIdQuery($docId)));

        $result = [];

        foreach ($select as $id => $row) {
            $dd = new DataDescriptor($row);
            $dd->loadFile();
            $result[$id] = $dd;
        }

        return empty($result) ? null : $result;
    }

    public static function getByDocIdQuery(int $docId): ?Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(static::TABLE)->where('docid = ?', $docId)->order('submission_date DESC');
    }


    public static function getVersionsQuery(int $docId): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->select()
            ->from(self::TABLE, 'version')
            ->where('docid = ?', $docId)
            ->order('submission_date DESC');

    }

    public static function getLatestVersion(int $docId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return (int)$db->fetchOne(self::getVersionsQuery($docId));
    }

}