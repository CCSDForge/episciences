<?php

class Episciences_Paper_DatasetsManager
{
    /**
     * @param int $docId
     * @return array [Episciences_Paper_Dataset]
     */
    public static function findByDocId(int $docId): array
    {

        $oResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(array('DS' => T_PAPER_DATASETS,['DS.id']))
            ->joinLeft(array('DM' => T_PAPER_DATASETS_META), 'DS.id_paper_datasets_meta = DM.id',['DM.metatext'])
            ->where('DS.doc_id = ?', $docId);
        $rows = $db->fetchAll($sql);
        $iRow = count($rows)-1;

        foreach ($rows as $key => $value) {
            $oResult[] = new Episciences_Paper_Dataset($value);
            if ($key === $iRow && !is_null($oResult[$key]->getIdPaperDatasetsMeta())){
                $oResult['metatext'] = Episciences_Paper_DatasetsMetadataManager::decodeJsonMetatext($value['metatext']);
            }
        }
        return $oResult;
    }

    /**
     *
     * @param int $docId
     * @param string $value
     * @return Episciences_Paper_File | null
     */
    public static function findByValue(int $docId, string $value): ?\Episciences_Paper_Dataset
    {
        $oDataset = null;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPER_DATASETS)
            ->where('doc_id = ?', $docId)
            ->where('value = ?', $value);
        $row = $db->fetchRow($sql);

        if ($row) {
            $oDataset = new Episciences_Paper_Dataset($row);
        }

        return $oDataset;
    }

    /**
     * @param int $docId
     * @return bool
     */
    public static function deleteByDocId(int $docId): bool
    {
        if ($docId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $docId]) > 0);

    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteById(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_DATASETS, ['id = ?' => $id]) > 0);

    }


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

            if (!($dataset instanceof Episciences_Paper_Dataset)) {
                $dataset = new Episciences_Paper_Dataset($dataset);
            }

            $values[] = '(' . $db->quote($dataset->getDocId()) . ',' . $db->quote($dataset->getCode()) . ',' . $db->quote($dataset->getName()) . ',' . $db->quote($dataset->getValue()) . ',' . $db->quote($dataset->getLink()) . ',' . $db->quote($dataset->getSourceId()) . ',' . $db->quote($dataset->getRelationship()) . ',' . $db->quote($dataset->getIdPaperDatasetsMeta()) . ')';
        }

        $sql = 'INSERT IGNORE INTO ' . $db->quoteIdentifier(T_PAPER_DATASETS) . ' (`doc_id`, `code`, `name`, `value`, `link`, `source_id`, `relationship`, `id_paper_datasets_meta`) VALUES ';

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

        return $affectedRows;

    }

    /**
     * @param Episciences_Paper_Dataset $dataset
     * @return int
     */
    public static function update(Episciences_Paper_Dataset $dataset): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['id = ?'] = $dataset->getId();

        $values = [
            'docId' => $dataset->getDocId(),
            'code' => $dataset->getCode(),
            'name' => $dataset->getName(),
            'value' => $dataset->getValue(),
            'link' => $dataset->getLink(),
            'sourceId' => $dataset->getSourceId(),
            'relationship' => $dataset->getRelationship(),
            'id_paper_datasets_meta' => $dataset->getIdPaperDatasetsMeta()
        ];

        try {
            $resUpdate = $db->update(T_PAPER_DATASETS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
        return $resUpdate;
    }

}