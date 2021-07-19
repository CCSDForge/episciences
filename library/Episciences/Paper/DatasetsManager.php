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
            ->from(T_PAPER_DATASETS)
            ->where('doc_id = ?', $docId);

        $rows = $db->fetchAssoc($sql);

        foreach ($rows as $value) {
            $oResult[] = new Episciences_Paper_Dataset($value);
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
     * @return bool
     */

    public static function insert(array $datasets): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $success = false;
        $values = [];

        foreach ($datasets as $dataset) {

            if (!($dataset instanceof Episciences_Paper_Dataset)) {
                $dataset = new Episciences_Paper_Dataset($dataset);
            }

            $values[] = '(' . $db->quote($dataset->getDocId()) . ',' . $db->quote($dataset->getCode()) . ',' . $db->quote($dataset->getName()) . ',' . $db->quote($dataset->getValue()) . ',' . $db->quote($dataset->getLink()) . ',' . $db->quote($dataset->getSourceId())  . ')';
        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_DATASETS) . ' (`doc_id`, `code`, `name`, `value`, `link`, `source_id`) VALUES ';

        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                $db->query($sql . implode(', ', $values));
                $success = true;
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $success;

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
            'sourceId' => $dataset->getSourceId()
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