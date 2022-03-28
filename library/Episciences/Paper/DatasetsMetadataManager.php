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
//
//    /**
//     * @param Episciences_Paper_Dataset $dataset
//     * @return int
//     */
//    public static function update(Episciences_Paper_Dataset $dataset): int
//    {
//        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
//        $where['id = ?'] = $dataset->getId();
//
//        $values = [
//            'docId' => $dataset->getDocId(),
//            'code' => $dataset->getCode(),
//            'name' => $dataset->getName(),
//            'value' => $dataset->getValue(),
//            'link' => $dataset->getLink(),
//            'sourceId' => $dataset->getSourceId()
//        ];
//
//        try {
//            $resUpdate = $db->update(T_PAPER_DATASETS, $values, $where);
//        } catch (Zend_Db_Adapter_Exception $exception) {
//            $resUpdate = 0;
//            trigger_error($exception->getMessage(), E_USER_ERROR);
//        }
//        return $resUpdate;
//    }

}