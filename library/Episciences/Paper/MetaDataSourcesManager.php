<?php


class Episciences_Paper_MetaDataSourcesManager
{
    public static function all(): array
    {

        $oMetaDataSources = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $rows = $db
            ->fetchAssoc(
                $db->select()
                    ->from(T_PAPER_METADATA_SOURCES)
            );

        foreach ($rows as $id => $value) {
            $oMetaDataSources [$id] = new Episciences_Paper_MetaDataSource($value);
        }

        return $oMetaDataSources;

    }
}






