<?php

class Episciences_Paper_DatasetsManager
{

    public const RELATION_TYPE_SOFTWARE = 'references';
    public const URL_DOI = 'https://doi.org/';
    public const URL_ARXIV = 'https://arxiv.org/abs/';

    public const URL_SWH = 'https://archive.softwareheritage.org/';

    public const URL_HDL = 'https://hdl.handle.net/';

    public const URL_HAL = 'https://hal.science/';

    public const URL_PMID = 'https://pubmed.ncbi.nlm.nih.gov/';

    public const URL_PMC = 'https://www.ncbi.nlm.nih.gov/pmc/articles/';

    public const VALID_TYPES_OF_SWHID = ['dir','rev','snp'];

    /**
     * @param int $docId
     * @return array [Episciences_Paper_Dataset]
     */
    public static function findByDocId(int $docId): array
    {

        $oResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(array('DS' => T_PAPER_DATASETS, ['DS.id']))
            ->joinLeft(array('DM' => T_PAPER_DATASETS_META), 'DS.id_paper_datasets_meta = DM.id', ['DM.metatext'])
            ->where('DS.doc_id = ?', $docId)->order('source_id');
        $rows = $db->fetchAll($sql);
        foreach ($rows as $value) {
            switch ($value['link']):
                case 'doi':
                    $value['link'] = self::URL_DOI . $value['value'];
                    break;
                case 'arXiv':
                    $value['link'] = self::URL_ARXIV . $value['value'];
                    break;
                case 'SWHID':
                    $value['link'] = (Episciences_Tools::isSoftwareHeritageId($value['value'])) ?
                        self::URL_SWH . $value['link'] :
                        self::getUrlLinkedData($value['value'], Episciences_Tools::checkValueType($value['value']));
                    break;
                case 'handle':
                    $value['link'] = self::URL_HDL . $value['value'];
                    break;
                case 'hal':
                    $value['link'] = self::URL_HAL . $value['value'];
                    break;
                case 'pmid':
                    $value['link'] = self::URL_PMID . $value['value'];
                    break;
                case 'pmc':
                    $value['link'] = self::URL_PMC . $value['value'];
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

    public static function getUrlLinkedData(string $linkedValue, string $linkType): string
    {
        switch ($linkType):
            case 'doi':
                $url = self::URL_DOI . $linkedValue;
                break;
            case 'arXiv':
            case 'arxiv':
                $url = self::URL_ARXIV . $linkedValue;
                break;
            case 'SWHID':
                $url = (Episciences_Tools::isSoftwareHeritageId($linkedValue)) ?
                    self::URL_SWH . $linkedValue :
                    self::getUrlLinkedData($linkedValue, Episciences_Tools::checkValueType($linkedValue));
                break;
            case 'handle':
                $url = self::URL_HDL . $linkedValue;
                break;
            case 'hal':
                $url = self::URL_HAL . $linkedValue;
                break;
            case 'pmid':
                $url = self::URL_PMID . $linkedValue;
                break;
            case 'pmc':
                $url = self::URL_PMC . $linkedValue;
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
     * @param int $id
     * @return Episciences_Paper_Dataset|false
     */
    public static function findById(int $id): bool|Episciences_Paper_Dataset
    {
        if (!is_numeric($id)) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from(T_PAPER_DATASETS)
            ->where('id = ?', $id);

        $result = $db->fetchRow($query);
        return $result ? new Episciences_Paper_Dataset($result) : false;

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
        return $db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $docId]) > 0;

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

        return $db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $docId, 'source_id = ?' => $repoId]) > 0;


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
        return $db->delete(T_PAPER_DATASETS, ['id = ?' => $id]) > 0;

    }

    /**
     * @param int $docId
     * @param string $name
     * @param string $value
     * @param string $code
     * @param int|null $metaTextId
     * @param array $options
     * @return int
     */
    public static function addDatasetFromSubmission(
        int    $docId,
        string $name,
        string $value,
        string $code,
        int    $metaTextId = null,
        array  $options = []
    ): int
    {
        $dataset = new Episciences_Paper_Dataset();
        $dataset->setDocId($docId);
        $dataset->setCode($code);
        switch ($name):
            case 'arxiv':
                $dataset->setName("arXiv");
                $dataset->setLink("arXiv");
                break;
            case 'doi':
                $dataset->setName("doi");
                $dataset->setLink("doi");
                break;
            case 'hal':
                $dataset->setName("hal");
                $dataset->setLink("hal");
                break;
            case 'handle':
                $dataset->setName("handle");
                $dataset->setLink("handle");
                break;
            case 'software':
                $dataset->setCode("swhidId_s");
                $dataset->setName("software");
                $dataset->setLink("SWHID");
                break;
            case 'zbmath':
                $dataset->setName("zbmath");
                $dataset->setLink("url");
                break;
            case 'url':
                $dataset->setName("software");
                if ($code === 'dataset' || $code === 'publication') {
                    $dataset->setName("url");
                }
                $dataset->setLink("url");
                break;
            default:
                break;
        endswitch;
        $dataset->setValue($value);
        $dataset->setRelationship($options['relationship'] ?? self::RELATION_TYPE_SOFTWARE);
        $dataset->setSourceId($options['sourceId'] ?? (int)Episciences_Repositories::EPI_USER_ID);
        if ($metaTextId !== null) {
            $dataset->setIdPaperDatasetsMeta($metaTextId);
        }
        return self::insert([$dataset]);
    }

    /**
     * @param array $datasets
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

    public static function isTypeOfSwhid(string $swhid, string $type): bool
    {
        if (self::checkSwhidType($swhid) === $type) {
            return true;
        }
        return false;
    }

    public static function hasValidSwhidType(string $swhid): bool
    {
        if (!in_array(self::checkSwhidType($swhid), self::VALID_TYPES_OF_SWHID)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $swhid
     * @return string
     */
    public static function checkSwhidType(string $swhid): string
    {
        if (Episciences_Tools::isSoftwareHeritageId($swhid) === true) {
            $swhidEx = explode(':', $swhid);
            if (is_array($swhidEx)) {
                return $swhidEx[2];
            }
        }

        return '';
    }

    /**
     * @param array $arrayLd
     * @return array
     */
    public static function putUserLdFirst(array $arrayLd): array
    {
        foreach ($arrayLd as $key => $ld) {
            if (isset($ld[Episciences_Repositories::EPI_USER_ID])) {
                $epiUserLd = $ld[Episciences_Repositories::EPI_USER_ID];
                unset($ld[Episciences_Repositories::EPI_USER_ID]);
                $arrayLd[$key] = [Episciences_Repositories::EPI_USER_ID => $epiUserLd] + $ld;
            }
        }
        krsort($arrayLd, SORT_FLAG_CASE | SORT_STRING);
        return $arrayLd;
    }

    public static function updateRelationAndTypeById(Episciences_Paper_Dataset $epiDataset): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $linkkedData = $epiDataset->getId();
        $where = [];
        if ($linkkedData !== null) {
            $where['id = ?'] = $linkkedData;
        }

        $values = [
            'code' => $epiDataset->getCode(),
            'relationship' => $epiDataset->getRelationship(),
            'source_id' => $epiDataset->getSourceId(),
        ];
        try {
            $resUpdate = $db->update(T_PAPER_DATASETS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $resUpdate;
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

    public static function findByRelation(int $docId = null, string $relation = 'isDocumentedBy'): ?Episciences_Paper_Dataset
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(T_PAPER_DATASETS)
            ->where('doc_id = ?', $docId)
            ->where('relationship = ?', $relation);

        $result = $db->fetchRow($sql);
        return $result ? new Episciences_Paper_Dataset($result) : null;
    }

}