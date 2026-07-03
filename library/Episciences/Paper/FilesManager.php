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
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = self::findByDocIdQuery($docId, '*', 'file_size DESC');

        $rows = $db->fetchAssoc($sql);

        if ($fetch !== 'object') {
            return $rows;
        }

        return self::toArrayObject($rows);

    }

    /**
     *
     * @param int $docId
     * @param string $fileName
     * @return Episciences_Paper_File | null
     */
    public static function findByName(int $docId, string $fileName): ?Episciences_Paper_File
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = self::findByDocIdQuery($docId)->where('file_name = ?', $fileName);

        $row = $db->fetchRow($sql);

        if (!$row) {
            trigger_error(sprintf("%s %s Docid: %d Filename: %s: File not found", __CLASS__, __FUNCTION__, $docId, $fileName), E_USER_WARNING);
            return null;
        }

        $oFile = new Episciences_Paper_File($row);
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

        $affectedRows = 0;

        try {
            $result = self::syncFiles($files);

            foreach ($result as $count) {
                $affectedRows += $count;
            }

        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        return $affectedRows;
    }

    public static function findByDocIdQuery(int $docId, string|array|Zend_Db_Expr $cols = '*', ?string $spec = null): ?Zend_Db_Select
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

    private static function toArrayObject(array $rows): array
    {
        $oResult = [];

        foreach ($rows as $value) {
            $file = new Episciences_Paper_File($value);
            $file->setDownloadLike();
            $oResult[$file->getId()] = $file;
        }

        return $oResult;
    }

    /**
     * Batch Deletion Based on Composite Keys
     */
    private static function batchDelete(array $filesToDelete): int
    {
        if (empty($filesToDelete)) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $countDeleted = 0;

        /** @var Episciences_Paper_File $file */

        foreach ($filesToDelete as $file) {
            $where = [
                    $db->quoteInto('doc_id = ?', $file->getDocId()),
                    $db->quoteInto('self_link = ?', $file->getSelfLink()),
            ];

            if ($db->delete(T_PAPER_FILES, $where) > 0) {
                $countDeleted++;
            }
        }

        return $countDeleted;
    }

    /**
     * Batch Insertion
     * @throws Zend_Db_Statement_Exception
     */
    private static function batchInsert(array $files): int
    {
        if (empty($files)) {
            return 0;
        }

        $countInserted = 0;

        // Process in batches of 500 to avoid excessively large packets
        $chunkSize = 500;
        $chunks = array_chunk($files, $chunkSize);

        foreach ($chunks as $chunk) {
            $values = [];
            /** @var Episciences_Paper_File $file */
            foreach ($chunk as $file) {
                $values[] = [
                        'doc_id'       => $file->getDocId(),
                        'source'       => $file->getSource(),
                        'file_name'    => $file->getFileName(),
                        'checksum'     => $file->getChecksum(),
                        'checksum_type'=> $file->getChecksumType(),
                        'self_link'    => $file->getSelfLink(),
                        'file_size'    => $file->getFileSize(),
                        'file_type'    => $file->getFileType()
                ];
            }

            $countInserted += self::insertMultiple($values);
        }

        return $countInserted;
    }

    /**
     * @param array $files
     * @return array
     * @throws Exception
     */

    public static function syncFiles(array $files): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($files[0] instanceof Episciences_Paper_File) {
            $docId = $files[0]->getDocId();
        } else {
            $docId = (int)($files[0]['doc_id'] ?? 0);
        }

        if (!$docId) {
            throw new InvalidArgumentException('Files synchronization error: Empty docId');
        }

        $results = [
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0
        ];

        $db->beginTransaction();

        try {

            $processedFiles = [];

            foreach ($files as $file) {

                if (!($file instanceof Episciences_Paper_File)) {
                    $file = new Episciences_Paper_File($file);
                    $file->setDownloadLike();
                }

                // That wasn't supposed to happen; but since it did, here's what we'll do:
                // Generate a unique identifier based on a checksum + name + source
                // Fix the collision bug in MySQL inserts by ensuring that each file has a truly unique self_link, thereby enabling the safe use of UPSERT without silent data loss.

                if ($file->getSelfLink() === Episciences_Paper_File::DEFAULT_SELF_LINK_VALUE) {
                    $uniqueHash = hash('sha256', $file->getDocId() . '|' . $file->getChecksum());
                    $file->setSelfLink(Episciences_Paper_File::DEFAULT_SELF_LINK_VALUE . substr($uniqueHash, 0, 16)); // Extend the truncate to reduce collisions
                }

                $processedFiles[] = $file;
            }

            // Retrieve existing files for comparison
            $existingFiles = self::findByDocId($docId);

            // Calculate the difference (DIFF) between the new batch and the existing one
            [$toInsert, $toUpdate, $toBeDeleted] = self::diffFiles($processedFiles, $existingFiles);

            // Perform the operations in the correct order

            if (!empty($toBeDeleted)) {  // Deletions first (releases any constraints)
                $results['deleted'] = self::batchDelete($toBeDeleted);
            }

            if (!empty($toInsert)) { // Insertions
                $results['inserted'] = self::batchInsert($toInsert);
            }

            if (!empty($toUpdate)) { // Updates
                $updated = 0;
                foreach ($toUpdate as $file) {
                    $updated += self::update($file);
                }
                $results['updated'] = $updated;
            }

            $db->commit();
            return $results;

        } catch (Exception $e) {
            $db->rollBack();
            throw new RuntimeException('Files synchronization error [rollback]:' . $e->getMessage());
        }
    }


    /**
     * Automatically detects Insert/Update/Delete operations through indexed comparisons
     */
    private static function diffFiles(array $newFiles, array $existingFiles): array
    {
        //Creates an index of new entries by unique composite key
        $newIndex = [];
        foreach ($newFiles as $file) {
            $key = self::makeKey($file->getDocId(), $file->getSelfLink());
            $newIndex[$key] = $file;
        }

        // Creates an index of existing records from the DB
        $existingIndex = [];
        /** @var Episciences_Paper_File $existing */
        foreach ($existingFiles as $existing) {
            $key = self::makeKey($existing->getDocId(), $existing->getSelfLink());
            $existingIndex[$key] = $existing;
        }

        $toInsert = [];
        $toUpdate = [];
        $toBeDeleted = [];


        $allKeys = array_unique(array_merge(
                array_keys($newIndex),
                array_keys($existingIndex)
        ));

        foreach ($allKeys as $key) {
            $isNewOnly = isset($newIndex[$key]) && !isset($existingIndex[$key]);   // new only: included in the new set but not in BD
            $isOldOnly = !isset($newIndex[$key]) && isset($existingIndex[$key]);   // old only: file deleted locally
            $isBoth = isset($newIndex[$key], $existingIndex[$key]);                // This key exists in both

            if ($isNewOnly) {
                $toInsert[] = $newIndex[$key];

            } elseif ($isOldOnly) {
                $toBeDeleted[] = $existingIndex[$key];

            } elseif ($isBoth) {

                $newFile = $newIndex[$key];
                $oldData = $existingIndex[$key];


                if (!self::hasChanged($oldData, $newFile)) {
                    // All values are identical: no action required (this file is silently ignored)
                    continue;
                }

                $toUpdate[] = $newFile; // update required
            }
        }

        return [$toInsert, $toUpdate, $toBeDeleted];
    }

    /**
     * Creates a composite key for efficient indexing
     */
    private static function makeKey(string $docId, string $selfLink): string
    {
        return $docId . '|' . $selfLink;
    }

    /**
     * Check changes
     * "doc_id", and "self_link" are not validated, because they serve as unique identifiers
     * @param Episciences_Paper_File $newFile
     * @param Episciences_Paper_File $oldData
     * @return bool
     */

    private static function hasChanged(Episciences_Paper_File $newFile, Episciences_Paper_File $oldData): bool
    {

        return !(

                $newFile->getChecksum() === $oldData->getChecksum() &&
                $newFile->getFileSize() === $oldData->getFileSize() &&
                $newFile->getFileType() === $oldData->getFileType() &&
                $newFile->getFileName() === $oldData->getFileName() &&
                $newFile->getSource()   === $oldData->getSource()
        );
    }

    /**
     * @param array $rows
     * @return int
     * @throws Zend_Db_Statement_Exception
     */

    private static function insertMultiple(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $columns = array_keys(current($rows));
        $columnList = implode(', ', array_map([$db, 'quoteIdentifier'], $columns));

        $valueSets = [];
        foreach ($rows as $rowData) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = $db->quote($rowData[$col]);
            }
            $valueSets[] = '(' . implode(', ', $values) . ')';
        }

        $sql = sprintf(
                "INSERT INTO %s (%s) VALUES %s",
                $db->quoteIdentifier(T_PAPER_FILES),
                $columnList,
                implode(', ', $valueSets)
        );

        return $db->query($sql)->rowCount();
    }

    /**
     * Updates a Single File
     * @throws Zend_Db_Adapter_Exception
     */
    private static function update(Episciences_Paper_File $file): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data = [
                'file_size'     => $file->getFileSize(),
                'checksum'      => $file->getChecksum(),
                'checksum_type' => $file->getChecksumType(),
                'file_type'     => $file->getFileType(),
                'file_name'     => $file->getFileName()
        ];

        $where = [
                $db->quoteInto('doc_id = ?', $file->getDocId()),
                $db->quoteInto('self_link = ?', $file->getSelfLink())
        ];

        return $db->update(T_PAPER_FILES, $data, $where);
    }

}