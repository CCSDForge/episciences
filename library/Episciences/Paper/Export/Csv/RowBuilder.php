<?php
declare(strict_types=1);

namespace Episciences\Paper\Export\Csv;

use Episciences\Paper\Import\Row;
use Episciences_Paper;
use Episciences_Section;
use Episciences_Volume;

/**
 * Builds an Episciences\Paper\Import\Row (the same 19-column DTO import:papers reads) from an
 * already-hydrated Episciences_Paper, plus its volume/section when known — reused directly by
 * export:papers so both commands share one CSV column layout instead of two.
 *
 * volume_id/section_id are always the source of truth for a later re-import
 * (Episciences\Paper\Import\VolumeSectionResolver reuses them as-is and only falls back to the
 * title columns when they're empty); the titles are exported purely for readability.
 */
final class RowBuilder
{
    public static function build(Episciences_Paper $paper, ?Episciences_Volume $volume, ?Episciences_Section $section): Row
    {
        $status = $paper->getStatus();
        $vid = $paper->getVid();
        $sid = $paper->getSid();
        $volumeTitles = $volume?->getTitles() ?? [];
        $sectionTitles = $section?->getTitles() ?? [];
        $editors = array_keys($paper->getEditors());

        return new Row(
            identifier: $paper->getIdentifier(),
            repoid: $paper->getRepoid(),
            version: self::formatVersion($paper->getVersion()),
            status: $status,
            rawStatus: (string)$status,
            volumeId: $vid !== 0 ? $vid : null,
            volumeTitleFr: $volumeTitles['fr'] ?? null,
            volumeTitleEn: $volumeTitles['en'] ?? null,
            volumeNum: self::stringOrNull($volume?->getVol_num()),
            volumeYear: self::stringOrNull($volume?->getVol_year()),
            sectionId: $sid !== 0 ? $sid : null,
            sectionTitleFr: $sectionTitles['fr'] ?? null,
            sectionTitleEn: $sectionTitles['en'] ?? null,
            uid: $paper->getUid() ?: null,
            publicationDate: self::stringOrNull($paper->getPublication_date()),
            editors: $editors !== [] ? implode('-', $editors) : null,
            doi: self::stringOrNull($paper->getDoi()),
            docid: (int)$paper->getDocid(),
            rvidOrCode: (string)$paper->getRvid(),
            submissionDate: self::stringOrNull($paper->getSubmission_date()),
        );
    }

    private static function formatVersion(float $version): string
    {
        return rtrim(rtrim(sprintf('%.6f', $version), '0'), '.');
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string)$value : null;
    }
}
