<?php

class Episciences_Paper_FilesManager
{
    /**
     * @param int $docId
     * @param string $fetch
     * @return array [Episciences_Paper_File]
     */
    public static function findByDocId(int $docId, string $fetch = 'object'): array
    {

        $oResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPER_FILES)
            ->where('doc_id = ?', $docId)
            ->order('file_size DESC');

        $rows = $db->fetchAssoc($sql);

        foreach ($rows as $value) {
            $file = new Episciences_Paper_File($value);
            $file->setDownloadLike();
            $oResult[$file->getId()] = $file;
        }

        return $oResult;
    }

    /**
     *
     * @param int $docId
     * @param string $fileName
     * @return Episciences_Paper_File | null
     */
    public static function findByName(int $docId, string $fileName): ?\Episciences_Paper_File
    {
        $oFile = null;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPER_FILES)
            ->where('doc_id = ?', $docId)
            ->where('file_name = ?', $fileName);
        $row = $db->fetchRow($sql);

        if ($row) {
            $oFile = new Episciences_Paper_File($row);
        } else {
            trigger_error(sprintf("%s %s Docid: %d Filename: %s: File not found", __CLASS__, __FUNCTION__, $docId, $fileName), E_USER_WARNING);
            return null;
        }

        $oFile->setDownloadLike();

        return $oFile;
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
        $sql = self::findByDocIdQuery($docId, ['id'], null);
        $hasFiles = $db?->fetchOne($sql) > 0;

        if (!$hasFiles) {
            return true;
        }

        $db?->beginTransaction();

        try {

            $deletedRows = $db?->delete(T_PAPER_FILES, ['doc_id = ?' => $docId]);

            if ($deletedRows < 1) {
                throw new RuntimeException (sprintf("Failure to delete paper's files[DOCID = #%s]", $docId));
            }

            $db?->commit();

        } catch (Exception $e) {
            $deletedRows = 0;
            Episciences_View_Helper_Log::log($e->getMessage());
            $db?->rollBack();
        }

        return ($deletedRows > 0);

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
        return ($db->delete(T_PAPER_FILES, ['id = ?' => $id]) > 0);

    }


    /**
     * @param array $files
     * @return int
     */

    public static function insert(array $files): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $affectedRows = 0;
        $values = [];

        foreach ($files as $file) {

            if (!($files instanceof Episciences_Paper_File)) {
                $file = new Episciences_Paper_File($file);
            }

            $values[] = '(' . $db->quote($file->getDocId()) . ',' . $db->quote($file->getSource()) . ',' . $db->quote($file->getFileName()) . ',' . $db->quote($file->getChecksum()) . ',' . $db->quote($file->getChecksumType()) . ',' . $db->quote($file->getSelfLink()) . ',' . $db->quote($file->getFileSize()) . ',' . $db->quote($file->getFileType()) . ')';
        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_FILES) . ' (`doc_id`, `source`, `file_name`, `checksum`, `checksum_type`, `self_link`, `file_size`, `file_type`) VALUES ';

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
     * @param Episciences_Paper_File $file
     * @return int
     */
    public static function update(Episciences_Paper_File $file): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['id = ?'] = $file->getId();

        $values = [
            'docId' => $file->getDocId(),
            'fileName' => $file->getFileName(),
            'checksum' => $file->getChecksum(),
            'checksumType' => $file->getChecksumType(),
            'selfLink' => $file->getSelfLink(),
            'fileSize' => $file->getFileSize(),
            'fileType' => $file->getFileSize()
        ];

        try {
            $resUpdate = $db->update(T_PAPER_FILES, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }
        return $resUpdate;
    }


    public static function findByDocIdQuery(int $docId, $cols = '*', $spec = 'file_size DESC'): ?Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db?->select()
            ->from(T_PAPER_FILES, $cols)
            ->where('doc_id = ?', $docId);

        if ($spec) {
            $sql->order($spec);
        }

        return $sql;
    }


}