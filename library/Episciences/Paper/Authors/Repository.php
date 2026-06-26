<?php
declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class Episciences_Paper_Authors_Repository
{
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR;
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_MAX_DEPTH = 512;

    private static ?CacheItemPoolInterface $_cachePool = null;

    /**
     * Set the cache pool (useful for dependency injection in tests)
     */
    public static function setCachePool(CacheItemPoolInterface $cachePool): void
    {
        self::$_cachePool = $cachePool;
    }

    /**
     * Get the cache pool (ArrayAdapter by default)
     */
    public static function getCachePool(): CacheItemPoolInterface
    {
        if (self::$_cachePool === null) {
            self::$_cachePool = new ArrayAdapter();
        }
        return self::$_cachePool;
    }

    /**
     * @return array<int|string, array<string, mixed>> raw rows from the paper_authors table
     */
    public static function getAuthorByPaperId(int $paperId): array
    {
        $cachePool = self::getCachePool();
        $cacheKey = 'authors_paper_' . $paperId;
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()->from(T_PAPER_AUTHORS)->where('PAPERID = ?', $paperId);
            $data = $db->fetchAssoc($select);
            $cacheItem->set($data);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * Decode the authors JSON for a given paper
     *
     * @return array<int, array<string, mixed>> decoded authors data
     * @throws JsonException
     */
    public static function getDecodedAuthors(int $paperId): array
    {
        $decodedAuthors = [];

        // One row per paper expected; loop processes the single row
        foreach (self::getAuthorByPaperId($paperId) as $row) {
            $decodedAuthors = json_decode((string) $row['authors'], true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS);
        }

        return $decodedAuthors;
    }

    /**
     * Find the affiliations of a specific author within a paper
     *
     * @param int $authorIndex 0-based index of the author in the JSON array
     * @return array<int, mixed>|string affiliations array, or empty string if not found
     * @throws JsonException
     */
    public static function findAffiliationsOneAuthorByPaperId(int $paperId, int $authorIndex): array|string
    {
        $authorRows = self::getAuthorByPaperId($paperId);

        if ($authorRows === []) {
            return '';
        }

        $decodedAuthors = [];
        // One row per paper expected; loop processes the single row
        foreach ($authorRows as $row) {
            $decodedAuthors = json_decode((string) $row['authors'], true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS);
        }

        return $decodedAuthors[$authorIndex]['affiliation'] ?? '';
    }

    /**
     * Insert one or more author records
     *
     * @param array<int, array<string, mixed>|Episciences_Paper_Authors> $authors array of Episciences_Paper_Authors instances or associative arrays
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

        if ($quotedValues !== []) {
            try {
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $quotedValues));
                $affectedRows = $result->rowCount();
                // Invalidate cache for inserted papers
                $cachePool = self::getCachePool();
                foreach ($authors as $authorData) {
                    if ($authorData instanceof Episciences_Paper_Authors) {
                        $pId = $authorData->getPaperId();
                    } else {
                        $pId = $authorData['paperId'] ?? $authorData['paperid'] ?? null;
                    }
                    if ($pId !== null) {
                        $cachePool->deleteItem('authors_paper_' . $pId);
                    }
                }
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;
    }

    /**
     * Update an existing author record
     *
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
            $pId = $authorEntity->getPaperId();
            if ($pId !== null) {
                self::getCachePool()->deleteItem('authors_paper_' . $pId);
            }
        } catch (Zend_Db_Adapter_Exception $exception) {
            $affectedRows = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $affectedRows;
    }

    /**
     * Delete all author records for a given paper
     *
     * @return bool true if at least one row was deleted
     */
    public static function deleteAuthorsByPaperId(int $paperId): bool
    {
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $deleted = $db->delete(T_PAPER_AUTHORS, ['paperid = ?' => $paperId]) > 0;
        if ($deleted) {
            self::getCachePool()->deleteItem('authors_paper_' . $paperId);
        }
        return $deleted;
    }

    /**
     * Copy authors from paper metadata into the authors table
     *
     * @return int number of inserted rows
     */
    public static function insertFromPaper(Episciences_Paper $paper): int
    {
        $paperId = $paper->getPaperid();

        if (self::getAuthorByPaperId($paperId) !== []) {
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
                'family' => $nameParts[0]
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
     * @throws Zend_Db_Statement_Exception
     */
    public static function verifyExistOrInsert(int $docId, int $paperId): void
    {
        if (self::getAuthorByPaperId($paperId) !== []) {
            return;
        }

        $paper = Episciences_PapersManager::get($docId, false);
        self::insertFromPaper($paper);
    }
}
