<?php

class Episciences_Paper_DatasetsManager
{

    public CONST RELATION_TYPE_SOFTWARE = 'references';
    public CONST URL_DOI = 'https://doi.org/';
    public CONST URL_ARXIV = 'https://arxiv.org/abs/';

    public CONST URL_SWH = 'https://archive.softwareheritage.org/';

    public CONST URL_HDL = 'https://hdl.handle.net/';

    public CONST URL_HAL = 'https://hal.science/';

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
                    $value['link'] = self::URL_DOI.$value['value'];
                    break;
                case 'arXiv':
                    $value['link'] = self::URL_ARXIV.$value['value'];
                    break;
                case 'SWHID':
                    $value['link'] = self::URL_SWH.$value['value'];
                    break;
                case 'handle':
                    $value['link'] = self::URL_HDL.$value['value'];
                    break;
                case 'hal':
                    $value['link'] = self::URL_HAL.$value['value'];
                    break;
                case 'url':
                    $value['link'] = $value['value'];
                    break;
                default:
                    break;
            endswitch;
            $oResult[] = new Episciences_Paper_Dataset($value);
        }
        return $oResult;
    }

    /**
     * @param int $id
     * @return Episciences_Paper_Dataset|false
     */
    public static function findById(int $id){
        if (!is_numeric($id)) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from(T_PAPER_DATASETS)
            ->where('id = ?', $id);

        return new Episciences_Paper_Dataset($db->fetchRow($query));

    }


    /**
     * @param int $docId
     * @return array
     */
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
                $url = self::URL_DOI.$linkedValue;
                break;
            case 'arXiv':
                $url = self::URL_ARXIV.$linkedValue;
                break;
            case 'SWHID':
                $url = self::URL_SWH.$linkedValue;
                break;
            case 'handle':
                $url = self::URL_HDL.$linkedValue;
                break;
            case 'hal':
                $url = self::URL_HAL.$linkedValue;
                break;
            case 'url':
                $url = $linkedValue;
                break;
            default:
                $url = '';
                break;
        endswitch;
        return $url;
    }

    /**
     * @param int $docId
     * @param string $name
     * @param string $value
     * @return int
     */
    public static function addDatasetFromSubmission(int $docId,string $name, string $value): int {
        $dataset = new Episciences_Paper_Dataset();
        $dataset->setDocId($docId);
        switch ($name):
            case 'arxiv':
                $dataset->setCode("null");
                $dataset->setName("arXiv");
                $dataset->setLink("arXiv");
                break;
            case 'doi':
                $dataset->setCode("null");
                $dataset->setName("doi");
                $dataset->setLink("doi");
                break;
            case 'hal':
                $dataset->setCode("null");
                $dataset->setName("hal");
                $dataset->setLink("hal");
                break;
            case 'handle':
                $dataset->setCode("null");
                $dataset->setName("handle");
                $dataset->setLink("handle");
                break;
            case 'software':
                $dataset->setCode("swhidId_s");
                $dataset->setName("software");
                $dataset->setLink("SWHID");
                break;
            case 'url':
                $dataset->setCode("url");
                $dataset->setName("software");
                $dataset->setLink("url");
                break;
            default:
                break;
        endswitch;
        $dataset->setValue($value);
        $dataset->setRelationship(self::RELATION_TYPE_SOFTWARE);
        $dataset->setSourceId((int)Episciences_Repositories::EPI_USER_ID);
        return self::insert([$dataset]);
    }

    /**
     * @param Episciences_Paper $paper
     * @return void
     */
    public static function updateAllByDocId(Episciences_Paper $paper): void
    {
        $docIdsInfos = $paper->getPreviousVersions(true);
        $newDocId = $paper->getDocid();
        if ($docIdsInfos !== null) {
            $lastDocId = array_key_first($docIdsInfos);
            $allLdArray = self::getByDocId($lastDocId);
            if (!empty($allLdArray)) {
                foreach ($allLdArray as $ld) {
                    $ld = new Episciences_Paper_Dataset($ld);
                    $ld->setDocId($newDocId);
                    self::insert([$ld]);
                }
            }
        }
    }
}