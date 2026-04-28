<?php


class Episciences_Paper_MetaDataSourcesManager
{
    public static function all(bool $isObjEntries = true, bool $enabledOnly = true): array
    {

        $oMetaDataSources = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $query = $db->select()->from(T_PAPER_METADATA_SOURCES);

        if ($enabledOnly) {
            $query->where('status = ?', true);
        }

        $rows = $db->fetchAssoc($query);

        if(!$isObjEntries) {
            return $rows;
        }

        foreach ($rows as $id => $value) {
            $oMetaDataSources [$id] = new Episciences_Paper_MetaDataSource($value);
        }

        return $oMetaDataSources;

    }
}






