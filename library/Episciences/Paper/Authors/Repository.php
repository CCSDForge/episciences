<?php

class Episciences_Paper_Authors_Repository
{
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR;
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_MAX_DEPTH = 512;

    /**
     * @param int|string $paperId
     * @return array raw rows from the paper_authors table
     */
    public static function getAuthorByPaperId($paperId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPER_AUTHORS)->where('PAPERID = ?', $paperId);
        return $db->fetchAssoc($select);
    }

    /**
     * Decode the authors JSON for a given paper
     *
     * @param int $paperId
     * @return array decoded authors data
     * @throws JsonException
     */
    public static function getDecodedAuthors(int $paperId): array
    {
        $decodedAuthors = [];

        // One row per paper expected; loop processes the single row
        foreach (self::getAuthorByPaperId($paperId) as $row) {
            $decodedAuthors = json_decode($row['authors'], true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS);
        }

        return $decodedAuthors;
    }

    /**
     * Find the affiliations of a specific author within a paper
     *
     * @param int $paperId
     * @param int $authorIndex 0-based index of the author in the JSON array
     * @return array|string affiliations array, or empty string if not found
     * @throws JsonException
     */
    public static function findAffiliationsOneAuthorByPaperId(int $paperId, int $authorIndex): array|string
    {
        $authorRows = self::getAuthorByPaperId($paperId);

        if (empty($authorRows)) {
            return '';
        }

        $decodedAuthors = [];
        // One row per paper expected; loop processes the single row
        foreach ($authorRows as $row) {
            $decodedAuthors = json_decode($row['authors'], true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS);
        }

        if (isset($decodedAuthors[$authorIndex]['affiliation'])) {
            return $decodedAuthors[$authorIndex]['affiliation'];
        }

        return '';
    }

    /**
     * Insert one or more author records
     *
     * @param array $authors array of Episciences_Paper_Authors instances or associative arrays
     * @return int number of affected rows
     */
    public static function insert(array $authors): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $quotedValues = [];
        $affectedRows = 0;

        foreach ($authors as $authorData) {
            if (!($authorData instanceof Episciences_Paper_Authors)) {
                $authorData = new Episciences_Paper_Authors($authorData);
            }
            $quotedValues[] = '(' . $db->quote($authorData->getAuthors()) . ',' . $db->quote($authorData->getPaperId()) . ')';
        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_AUTHORS) . ' (`authors`,`paperId`) VALUES ';

        if (!empty($quotedValues)) {
            try {
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $quotedValues));
                $affectedRows = $result->rowCount();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;
    }

    /**
     * Update an existing author record
     *
     * @param Episciences_Paper_Authors $authorEntity
     * @return int number of affected rows
     */
    public static function update(Episciences_Paper_Authors $authorEntity): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = [];

        $authorId = $authorEntity->getAuthorId();
        if ($authorId !== null) {
            $where['idauthors = ?'] = $authorId;
        }
        $where['paperid = ?'] = $authorEntity->getPaperId();

        $values = [
            'authors' => $authorEntity->getAuthors()
        ];

        try {
            $affectedRows = $db->update(T_PAPER_AUTHORS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $affectedRows = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $affectedRows;
    }

    /**
     * Delete all author records for a given paper
     *
     * @param int $paperId
     * @return bool true if at least one row was deleted
     */
    public static function deleteAuthorsByPaperId(int $paperId): bool
    {
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_AUTHORS, ['paperid = ?' => $paperId]) > 0);
    }

    /**
     * Copy authors from paper metadata into the authors table
     *
     * @param Episciences_Paper $paper
     * @return int number of inserted rows
     */
    public static function insertFromPaper(Episciences_Paper $paper): int
    {
        $paperId = $paper->getPaperid();

        if (!empty(self::getAuthorByPaperId($paperId))) {
            return 0;
        }

        $metadataAuthors = $paper->getMetadata('authors');
        $formattedAuthors = [];

        foreach ($metadataAuthors as $rawAuthor) {
            $fullname = Episciences_Tools::reformatOaiDcAuthor($rawAuthor);
            $nameParts = explode(', ', $rawAuthor);

            $formattedAuthors[] = [
                'fullname' => $fullname,
                'given' => $nameParts[1] ?? null,
                'family' => $nameParts[0] ?? null
            ];
        }

        return self::insert([
            [
                'authors' => json_encode($formattedAuthors, self::JSON_ENCODE_FLAGS),
                'paperId' => $paperId
            ]
        ]);
    }

    /**
     * Ensure an author record exists for a paper; insert from metadata if missing
     *
     * @param int $docId
     * @param int $paperId
     * @return void
     * @throws Zend_Db_Statement_Exception
     */
    public static function verifyExistOrInsert(int $docId, int $paperId): void
    {
        if (!empty(self::getAuthorByPaperId($paperId))) {
            return;
        }

        $paper = Episciences_PapersManager::get($docId, false);
        self::insertFromPaper($paper);
    }
}
