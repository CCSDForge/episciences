<?php

class Episciences_Paper_DatasetsMetadataManager
{
    /**
     * @param array $datasets
     * @return int
     */

    public static function insert(array $datasets): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;


        foreach ($datasets as $dataset) {

            if (!($dataset instanceof Episciences_Paper_DatasetMetadata)) {

                $dataset = new Episciences_Paper_DatasetMetadata([
                    "metatext"=> (string) $dataset,
                ]);
            }

            $values[] = '(' . $db->quote($dataset->getMetatext()) . ')';

        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_DATASETS_META) . ' (`metatext`) VALUES ';

        if (!empty($values)) {

            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values));

                $affectedRows = $result->rowCount();

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $db->lastInsertId();

    }
    /**
     * @param int $id
     * @return bool
     */

    public static function deleteMetaDataAndDatasetsByIdMd(int $id): bool{

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {
            $db->delete('paper_datasets_meta',['id = ?' => $id]);
            return true;
        } catch (Zend_Db_Statement_Exception $exception) {
            return false;
        }
    }
}