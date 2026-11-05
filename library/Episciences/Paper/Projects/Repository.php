<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * DB read/write layer for the paper_projects table.
 * No HTTP, no HTML, no cache — single responsibility.
 */
class Episciences_Paper_Projects_Repository
{
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_MAX_DEPTH    = 512;

    /** Episciences_Paper::get() cache entries of the exports carrying fundings */
    private const FUNDING_EXPORT_CACHE_METHODS = ['getCrossref', 'getOpenaire', 'getDatacite'];

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
     * Retrieve all projects for a given paper ID, with source name joined.
     *
     * @return array<int|string, array<string, mixed>> raw rows
     */
    public static function getByPaperId(int $paperId): array
    {
        return self::fetchCached(self::paperCacheKey($paperId), static function () use ($paperId): Zend_Db_Select {
            return Zend_Db_Table_Abstract::getDefaultAdapter()->select()
                ->from(['project' => T_PAPER_PROJECTS])
                ->joinLeft(
                    ['source_paper' => T_PAPER_METADATA_SOURCES],
                    'project.source_id = source_paper.id',
                    ['source_id_name' => 'source_paper.name']
                )
                ->where('PAPERID = ?', $paperId);
        });
    }

    /**
     * Retrieve projects for a given paper ID and source ID.
     *
     * @return array<int|string, array<string, mixed>> raw rows
     */
    public static function getByPaperIdAndSourceId(int $paperId, int $sourceId): array
    {
        return self::fetchCached(self::paperSourceCacheKey($paperId, $sourceId), static function () use ($paperId, $sourceId): Zend_Db_Select {
            return Zend_Db_Table_Abstract::getDefaultAdapter()->select()
                ->from(T_PAPER_PROJECTS)
                ->where('PAPERID = ?', $paperId)
                ->where('source_id = ?', $sourceId);
        });
    }

    /**
     * Retrieve de-duplicated projects for a given paper ID.
     *
     * @return array<int, array<string, mixed>> de-duplicated projects
     * @throws JsonException
     */
    public static function getWithDuplicateRemoved(int $paperId): array
    {
        $allProjects = self::getByPaperId($paperId);
        $rawFunding = [];
        foreach ($allProjects as $project) {
            $rawFunding[] = json_decode(
                (string) $project['funding'],
                true,
                self::JSON_MAX_DEPTH,
                self::JSON_DECODE_FLAGS
            );
        }
        $rawFunding = array_unique($rawFunding, SORT_REGULAR);

        $finalFundingArray = [];
        foreach ($rawFunding as $fundings) {
            foreach ($fundings as $funding) {
                $finalFundingArray[] = $funding;
                ksort($finalFundingArray[array_key_last($finalFundingArray)]);
            }
        }

        return array_map(unserialize(...), array_unique(array_map(serialize(...), $finalFundingArray)));
    }

    /**
     * Insert a project row.
     * Uses alias syntax compatible with MySQL 8.0.20+:
     * AS new_row ON DUPLICATE KEY UPDATE funding = new_row.funding
     *
     * @throws Zend_Db_Statement_Exception
     */
    public static function insert(Episciences_Paper_Projects $project): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_PROJECTS)
            . ' (`funding`, `paperid`, `source_id`) VALUES ('
            . $db->quote($project->getFunding()) . ','
            . $db->quote($project->getPaperId()) . ','
            . $db->quote($project->getSourceId()) . ')'
            . ' AS new_row ON DUPLICATE KEY UPDATE funding = new_row.funding';

        /** @var Zend_Db_Statement_Interface $result */
        $result = $db->query($sql);
        $rowCount = $result->rowCount();

        self::invalidateCache($project);

        return $rowCount;
    }

    /**
     * Update the funding JSON for a project identified by paperId + sourceId.
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function update(Episciences_Paper_Projects $project): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = [
            'paperid = ?'   => $project->getPaperId(),
            'source_id = ?' => $project->getSourceId(),
        ];
        $values = ['funding' => $project->getFunding()];

        $affectedRows = (int) $db->update(T_PAPER_PROJECTS, $values, $where);

        self::invalidateCache($project);

        return $affectedRows;
    }

    /**
     * Delete the funding row of a paper for one source (e.g. the source no longer reports any funding).
     *
     * @return int number of deleted rows
     */
    public static function deleteByPaperIdAndSourceId(int $paperId, int $sourceId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $deletedRows = (int) $db->delete(T_PAPER_PROJECTS, [
            'paperid = ?'   => $paperId,
            'source_id = ?' => $sourceId,
        ]);

        self::invalidateCache((new Episciences_Paper_Projects())->setPaperId($paperId)->setSourceId($sourceId));

        return $deletedRows;
    }

    /**
     * Rows of a query, read from the cache pool or fetched (keyed by the first column) and cached.
     *
     * @param callable(): Zend_Db_Select $buildSelect
     * @return array<int|string, array<string, mixed>>
     */
    private static function fetchCached(string $cacheKey, callable $buildSelect): array
    {
        $cachePool = self::getCachePool();
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $cacheItem->set(Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAssoc($buildSelect()));
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * Drop the cached rows of the paper (all sources, and the written source) after a write.
     */
    private static function invalidateCache(Episciences_Paper_Projects $project): void
    {
        $paperId = $project->getPaperId();
        if ($paperId === null) {
            return;
        }

        $cachePool = self::getCachePool();
        $cachePool->deleteItem(self::paperCacheKey($paperId));

        $sourceId = $project->getSourceId();
        if ($sourceId !== null) {
            $cachePool->deleteItem(self::paperSourceCacheKey($paperId, $sourceId));
        }

        self::invalidateFundingExportCaches($paperId);
    }

    /**
     * Drop the Episciences_Paper::get() cache entries of the exports carrying fundings,
     * so that every funding writer refreshes them, not only the funding enrichment command.
     */
    public static function invalidateFundingExportCaches(int $paperId): void
    {
        (new FilesystemAdapter(Episciences_Paper::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA))
            ->deleteItems(array_map(
                static fn(string $method): string => $paperId . '-' . $method,
                self::FUNDING_EXPORT_CACHE_METHODS
            ));
    }

    private static function paperCacheKey(int $paperId): string
    {
        return 'projects_paper_' . $paperId;
    }

    private static function paperSourceCacheKey(int $paperId, int $sourceId): string
    {
        return 'projects_paper_' . $paperId . '_source_' . $sourceId;
    }
}
