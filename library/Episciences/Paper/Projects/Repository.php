<?php

declare(strict_types=1);

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * DB read/write layer for the paper_projects table.
 * No HTTP, no HTML, no cache — single responsibility.
 */
class Episciences_Paper_Projects_Repository
{
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_MAX_DEPTH    = 512;

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
        $cachePool = self::getCachePool();
        $cacheKey = 'projects_paper_' . $paperId;
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(['project' => T_PAPER_PROJECTS])
                ->joinLeft(
                    ['source_paper' => T_PAPER_METADATA_SOURCES],
                    'project.source_id = source_paper.id',
                    ['source_id_name' => 'source_paper.name']
                )
                ->where('PAPERID = ?', $paperId);
            $data = $db->fetchAssoc($select);
            $cacheItem->set($data);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
    }

    /**
     * Retrieve projects for a given paper ID and source ID.
     *
     * @return array<int|string, array<string, mixed>> raw rows
     */
    public static function getByPaperIdAndSourceId(int $paperId, int $sourceId): array
    {
        $cachePool = self::getCachePool();
        $cacheKey = 'projects_paper_' . $paperId . '_source_' . $sourceId;
        $cacheItem = $cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(T_PAPER_PROJECTS)
                ->where('PAPERID = ?', $paperId)
                ->where('source_id = ?', $sourceId);
            $data = $db->fetchAssoc($select);
            $cacheItem->set($data);
            $cachePool->save($cacheItem);
        }

        return $cacheItem->get();
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

        // Invalidate cache
        $pId = $project->getPaperId();
        if ($pId !== null) {
            $cachePool = self::getCachePool();
            $cachePool->deleteItem('projects_paper_' . $pId);
            $sId = $project->getSourceId();
            if ($sId !== null) {
                $cachePool->deleteItem('projects_paper_' . $pId . '_source_' . $sId);
            }
        }

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

        // Invalidate cache
        $pId = $project->getPaperId();
        if ($pId !== null) {
            $cachePool = self::getCachePool();
            $cachePool->deleteItem('projects_paper_' . $pId);
            $sId = $project->getSourceId();
            if ($sId !== null) {
                $cachePool->deleteItem('projects_paper_' . $pId . '_source_' . $sId);
            }
        }

        return $affectedRows;
    }
}
