<?php

/**
 * Array utility helper class for common array operations
 *
 * Provides reusable methods for array manipulation, particularly
 * for database operations that require chunking or batching.
 */
class Episciences_Tools_ArrayHelper
{
    /**
     * Default chunk size for SQL WHERE IN() clauses
     * PDO/MySQL have a practical limit of ~500 parameters
     */
    const DEFAULT_CHUNK_SIZE = 500;

    /**
     * Maximum allowed chunk size to prevent memory issues
     */
    const MAX_CHUNK_SIZE = 1000;

    /**
     * Chunk array for safe use in SQL WHERE IN() clauses
     *
     * PDO and MySQL have limitations on the number of parameters that can be
     * used in a single query. This method splits arrays into manageable chunks
     * to avoid hitting these limits while maintaining performance.
     *
     * Usage example:
     * ```php
     * $uids = range(1, 2000); // 2000 user IDs
     * $chunks = Episciences_Tools_ArrayHelper::chunkForSql($uids);
     * foreach ($chunks as $chunkUids) {
     *     $select = $db->select()
     *         ->from('users')
     *         ->where('uid IN (?)', $chunkUids);
     *     $results = $db->fetchAll($select);
     * }
     * ```
     *
     * @param array $items Items to chunk (typically IDs for WHERE IN clause)
     * @param int $size Chunk size (default 500, max 1000)
     * @return array Array of chunks, or empty array if input is empty
     * @throws InvalidArgumentException If chunk size is invalid
     */
    public static function chunkForSql(array $items, int $size = self::DEFAULT_CHUNK_SIZE): array
    {
        // Return early for empty arrays
        if (empty($items)) {
            return [];
        }

        // Validate chunk size
        if ($size < 1 || $size > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException(
                sprintf(
                    'Chunk size must be between 1 and %d, got %d',
                    self::MAX_CHUNK_SIZE,
                    $size
                )
            );
        }

        return array_chunk($items, $size);
    }

    /**
     * Chunk array and preserve keys
     *
     * Similar to chunkForSql() but preserves array keys in the chunks.
     * Useful when keys represent important IDs or identifiers.
     *
     * @param array $items Items to chunk
     * @param int $size Chunk size (default 500, max 1000)
     * @return array Array of chunks with preserved keys
     * @throws InvalidArgumentException If chunk size is invalid
     */
    public static function chunkForSqlPreserveKeys(array $items, int $size = self::DEFAULT_CHUNK_SIZE): array
    {
        if (empty($items)) {
            return [];
        }

        if ($size < 1 || $size > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException(
                sprintf(
                    'Chunk size must be between 1 and %d, got %d',
                    self::MAX_CHUNK_SIZE,
                    $size
                )
            );
        }

        return array_chunk($items, $size, true);
    }
}
