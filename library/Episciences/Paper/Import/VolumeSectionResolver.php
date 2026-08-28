<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Episciences_Section;
use Episciences_SectionsManager;
use Episciences_Volume;
use Episciences_VolumesManager;
use RuntimeException;

/**
 * Finds or creates the volume/section referenced by one papers import CSV row.
 *
 * A volume/section id from the CSV is reused as-is once verified to belong to the
 * journal being imported into. Without an id,
 * the given title(s) are matched (case/whitespace-insensitive, per language)
 * against the journal's existing volumes/sections; the first match's id is
 * reused, otherwise a new volume/section is created. Created ids (and, in
 * dry-run mode, negative placeholder ids) are cached for the lifetime of this
 * resolver so later rows referencing the same title reuse the same id instead
 * of creating duplicates.
 */
final class VolumeSectionResolver
{
    /** @var array<int, array<int, array<string, string>>> rvid => [id => (lang => title)] */
    private array $volumesByRvid = [];
    /** @var array<int, array<int, array<string, string>>> rvid => [id => (lang => title)] */
    private array $sectionsByRvid = [];
    private int $nextPlaceholderId = -1;

    public function __construct(private readonly bool $dryRun)
    {
    }

    public function resolveVolumeId(int $rvid, Row $row): ?int
    {
        if ($row->volumeId !== null) {
            if (!array_key_exists($row->volumeId, $this->loadVolumeIndex($rvid))) {
                throw new RuntimeException("Volume id {$row->volumeId} does not belong to review {$rvid}");
            }

            return $row->volumeId;
        }

        $titles = $row->volumeTitles();
        if ($titles === []) {
            return null;
        }

        $index = $this->loadVolumeIndex($rvid);
        $matchedId = self::findMatchingId($titles, $index);
        if ($matchedId !== null) {
            return $matchedId;
        }

        $vid = $this->dryRun ? $this->nextPlaceholderId-- : $this->createVolume($rvid, $row, $titles);
        $this->volumesByRvid[$rvid][$vid] = $titles;

        return $vid;
    }

    public function resolveSectionId(int $rvid, Row $row): ?int
    {
        if ($row->sectionId !== null) {
            if (!array_key_exists($row->sectionId, $this->loadSectionIndex($rvid))) {
                throw new RuntimeException("Section id {$row->sectionId} does not belong to review {$rvid}");
            }

            return $row->sectionId;
        }

        $titles = $row->sectionTitles();
        if ($titles === []) {
            return null;
        }

        $index = $this->loadSectionIndex($rvid);
        $matchedId = self::findMatchingId($titles, $index);
        if ($matchedId !== null) {
            return $matchedId;
        }

        $sid = $this->dryRun ? $this->nextPlaceholderId-- : $this->createSection($rvid, $titles);
        $this->sectionsByRvid[$rvid][$sid] = $titles;

        return $sid;
    }

    /**
     * @param array<string, string> $wantedTitles lang => title
     * @param array<int, array<string, string>> $existingTitlesById id => (lang => title)
     */
    public static function findMatchingId(array $wantedTitles, array $existingTitlesById): ?int
    {
        foreach ($existingTitlesById as $id => $existingTitles) {
            foreach ($wantedTitles as $lang => $title) {
                if (
                    array_key_exists($lang, $existingTitles)
                    && self::normalizeTitle($existingTitles[$lang]) === self::normalizeTitle($title)
                ) {
                    return $id;
                }
            }
        }

        return null;
    }

    public static function normalizeTitle(string $title): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($title)), 'UTF-8');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadVolumeIndex(int $rvid): array
    {
        if (!array_key_exists($rvid, $this->volumesByRvid)) {
            $this->volumesByRvid[$rvid] = [];
            foreach (Episciences_VolumesManager::getList(['where' => 'RVID = ' . $rvid]) as $volume) {
                $this->volumesByRvid[$rvid][$volume->getVid()] = $volume->getTitles() ?? [];
            }
        }

        return $this->volumesByRvid[$rvid];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadSectionIndex(int $rvid): array
    {
        if (!array_key_exists($rvid, $this->sectionsByRvid)) {
            $this->sectionsByRvid[$rvid] = [];
            foreach (Episciences_SectionsManager::getList(['where' => 'RVID = ' . $rvid]) as $section) {
                $this->sectionsByRvid[$rvid][$section->getSid()] = $section->getTitles() ?? [];
            }
        }

        return $this->sectionsByRvid[$rvid];
    }

    /**
     * @param array<string, string> $titles lang => title
     */
    private function createVolume(int $rvid, Row $row, array $titles): int
    {
        // Episciences_Volume::save() only picks up titles from flat 'title_{lang}' keys
        // (via Episciences_VolumesManager::revertVolumeTitleToTextArray()), not from a
        // nested 'title' => [lang => text] array.
        $data = ['status' => 1, 'current_issue' => 0, 'special_issue' => 0];
        foreach ($titles as $lang => $title) {
            $data[Episciences_Volume::VOLUME_PREFIX_TITLE . $lang] = $title;
        }
        if ($row->volumeNum !== null) {
            $data['num'] = $row->volumeNum;
        }
        if ($row->volumeYear !== null) {
            $data['year'] = $row->volumeYear;
        }

        $volume = new Episciences_Volume();
        $volume->setRvid($rvid);
        if (!$volume->save($data)) {
            throw new RuntimeException('Failed to create volume with title: ' . implode(' / ', $titles));
        }

        return $volume->getVid();
    }

    /**
     * @param array<string, string> $titles lang => title
     */
    private function createSection(int $rvid, array $titles): int
    {
        $section = new Episciences_Section();
        $section->setRvid($rvid);
        $section->setTitles($titles);
        $section->setDescriptions([]);
        $section->setSetting(Episciences_Section::SETTING_STATUS, Episciences_Section::SECTION_OPEN_STATUS);

        if (!$section->save()) {
            throw new RuntimeException('Failed to create section with title: ' . implode(' / ', $titles));
        }

        return $section->getSid();
    }
}
