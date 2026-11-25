<?php
declare(strict_types=1);

namespace Episciences\Section\Import;

use Episciences_Section;
use Episciences_SectionsManager;
use RuntimeException;
use Zend_Db_Table_Abstract;

/**
 * Creates a journal section from one CSV row.
 */
final class Importer
{
    public function __construct(private readonly bool $dryRun)
    {
    }

    /**
     * @param array<string, string> $titles lang => title
     * @param array<string, string> $descriptions lang => description
     * @return int|null the created section id (or a dry-run placeholder), or null if the row
     *                   must be skipped because the requested position is already taken
     * @throws RuntimeException on failure
     */
    public function import(int $rvid, ?int $requestedPosition, array $titles, array $descriptions, int $status): ?int
    {
        $position = $this->resolvePosition($rvid, $requestedPosition);
        if ($position === null) {
            return null;
        }

        if ($this->dryRun) {
            return -1;
        }

        $section = new Episciences_Section();
        $section->setRvid($rvid);
        $section->setPosition($position);
        $section->setTitles($titles);
        $section->setDescriptions($descriptions);
        $section->setSetting(Episciences_Section::SETTING_STATUS, $status);

        if (!$section->save()) {
            throw new RuntimeException('Failed to save section');
        }

        return $section->getSid();
    }

    private function resolvePosition(int $rvid, ?int $requestedPosition): ?int
    {
        if ($requestedPosition === null) {
            return $this->getNextPosition($rvid);
        }

        if ($this->sectionExists($rvid, $requestedPosition)) {
            return null;
        }

        return $requestedPosition;
    }

    private function sectionExists(int $rvid, int $position): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int)$db->fetchOne(
            $db->select()
                ->from(Episciences_SectionsManager::TABLE, 'COUNT(*)')
                ->where('RVID = ?', $rvid)
                ->where('POSITION = ?', $position)
        ) > 0;
    }

    private function getNextPosition(int $rvid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int)$db->fetchOne(
            $db->select()
                ->from(Episciences_SectionsManager::TABLE, 'MAX(POSITION)')
                ->where('RVID = ?', $rvid)
        ) + 1;
    }
}
