<?php

use Episciences\Paper\WriteFailure;

class Episciences_Paper_DatasetsMetadataManager
{
    /**
     * @param array<Episciences_Paper_DatasetMetadata|string> $datasets
     * @return int|null id of the inserted row, null when nothing was inserted: callers store it as
     *                  paper_datasets.id_paper_datasets_meta, where NULL means "no metadata"
     *                  (0 would break the foreign key, and INSERT IGNORE would drop the dataset)
     */
    public static function insert(array $datasets): ?int
    {
        // Reported, not fatal: the caller stores its dataset without metadata
        return WriteFailure::reportAsWarning(__METHOD__, static fn(): ?int => self::insertOrFail($datasets), null);
    }

    /**
     * Same as insert(), but a database failure is thrown: the processing that follows the save of a paper
     * reports it and still stores the dataset, without metadata (Episciences_Submit::processDatasets())
     *
     * @param array<Episciences_Paper_DatasetMetadata|string> $datasets
     * @return int|null id of the inserted row, null when there was nothing to insert
     * @throws Zend_Db_Exception
     */
    public static function insertOrFail(array $datasets): ?int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        foreach ($datasets as $dataset) {

            if (!($dataset instanceof Episciences_Paper_DatasetMetadata)) {

                $dataset = new Episciences_Paper_DatasetMetadata([
                    "metatext"=> (string) $dataset,
                ]);
            }

            $values[] = '(' . $db->quote($dataset->getMetatext()) . ')';

        }

        // Without an insert, lastInsertId() would return the id of an earlier insert of the connection
        if ($values === []) {
            return null;
        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_DATASETS_META) . ' (`metatext`) VALUES ';

        $db->query($sql . implode(', ', $values));

        return (int)$db->lastInsertId();
    }
    /**
     * @param int $id
     * @return bool
     */

    public static function deleteMetaDataAndDatasetsByIdMd(int $id): bool{

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {
            $db->delete(T_PAPER_DATASETS, ['id_paper_datasets_meta = ?' => $id]);
            $db->delete(T_PAPER_DATASETS_META, ['id = ?' => $id]);
            return true;
        } catch (Zend_Db_Statement_Exception $exception) {
            return false;
        }
    }

    /**
     * Only for ScholeXplorer MetaData
     * @return string
     * @throws JsonException
     */
    public static function getTypeLdMetadata(string $metadata) {
        try {
            $metadata = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
            if(array_key_exists("objectType",$metadata)) {
                return $metadata['objectType'];
            }
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
        }
        return '';
    }

}