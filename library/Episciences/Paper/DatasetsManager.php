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
            ->where('DS.doc_id = ?', $docId)->order('source_id');
        $rows = $db->fetchAll($sql);
        foreach ($rows as $value) {
            switch ($value['link']):
                case 'doi':
                    $value['link'] = "https://doi.org/".$value['value'];
                    break;
                case 'arXiv':
                    $value['link'] = "https://arxiv.org/abs/".$value['value'];
                    break;
                case 'SWHID':
                    $value['link'] = "https://archive.softwareheritage.org/".$value['value'];
                    break;
                case 'handle':
                    $value['link'] = "https://hdl.handle.net/".$value['value'];
                    break;
                default:
                    break;
            endswitch;
            $oResult[] = new Episciences_Paper_Dataset($value);
        }
        return $oResult;
    }

    public static function getByDocId(int $docId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPER_DATASETS)
            ->where('doc_id = ?', $docId)->order('source_id');
        return $db->fetchAll($sql);
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
     * @param int $docId
     * @param int $repoId
     * @return bool
     */
    public static function deleteByDocIdAndRepoId(int $docId, int $repoId): bool
    {
        if ($docId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (
            $db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $docId, 'source_id = ?' => $repoId]) > 0
        );

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
     * @param array | Episciences_Paper_Dataset[] $datasets
     * @return int
     */
    public static function insert(array $datasets = []): int
    {
        $affectedRows = 0;

        if (empty($datasets)) {
            return $affectedRows;
        }

        $separator = ',';

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];


        foreach ($datasets as $dataset) {

            $oDataset = !($dataset instanceof Episciences_Paper_Dataset) ? new Episciences_Paper_Dataset($dataset) : $dataset;

            $currentValue = '(';
            $currentValue .= $oDataset->getDocId();
            $currentValue .= $separator;
            $currentValue .= $db->quote($oDataset->getCode());
            $currentValue .= $separator;
            $currentValue .= $db->quote($oDataset->getName());
            $currentValue .= $separator;
            $currentValue .= $db->quote($oDataset->getValue());
            $currentValue .= $separator;
            $currentValue .= $db->quote($oDataset->getLink());
            $currentValue .= $separator;
            $currentValue .= $db->quote($oDataset->getSourceId());
            $currentValue .= $separator;
            $currentValue .= $oDataset->getRelationship() === null ? 'NULL' : $db->quote($oDataset->getRelationship()); // Insert NULL rather than empty string
            $currentValue .= $separator;
            $currentValue .= $oDataset->getIdPaperDatasetsMeta() === null ? 'NULL' : $db->quote($oDataset->getIdPaperDatasetsMeta()); // Insert NULL rather than empty string: MySql insert 0 instead of empty string
            $currentValue .= ')';

            $values[] = $currentValue;

        }


        $sql = 'INSERT IGNORE INTO ';
        $sql .= $db->quoteIdentifier(T_PAPER_DATASETS);
        $sql .= ' (`doc_id`, `code`, `name`, `value`, `link`, `source_id`, `relationship`, `id_paper_datasets_meta`) VALUES ';

        try {
            //Prepares and executes an SQL
            /** @var Zend_Db_Statement_Interface $result */
            $result = $db->query($sql . implode(', ', $values));

            $affectedRows = $result->rowCount();

        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
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

    public static function getUrlLinkedData(string $linkedValue, string $linkType) : string
    {
        switch ($linkType):
            case 'doi':
                $url = "https://doi.org/".$linkedValue;
                break;
            case 'arXiv':
                $url = "https://arxiv.org/abs/".$linkedValue;
                break;
            case 'SWHID':
                $url = "https://archive.softwareheritage.org/".$linkedValue;
                break;
            case 'handle':
                $url = "https://hdl.handle.net/".$linkedValue;
                break;
            default:
                $url = '';
                break;
        endswitch;
        return $url;
    }


}