<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Episciences_Paper;

/**
 * Immutable value object for one row of a papers import/update CSV.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   identifier;repoid;version;status;volume_id;volume_title_fr;volume_title_en;volume_num;volume_year;
 *   section_id;section_title_fr;section_title_en;uid;publication_date;editors;doi;docid;rvid;submission_date
 *
 * - identifier        : (str) paper external id (e.g. HAL id) — required
 * - repoid            : (int) source repository id (open archive) — required
 * - version           : (str) paper version; left as string, cast where needed
 * - status            : (int) paper status id (default: published)
 * - volume_id         : (int) existing volume id, reused as-is if set
 * - volume_title_fr   : (str) French volume title — used to find or create a volume when volume_id is empty
 * - volume_title_en   : (str) English volume title
 * - volume_num        : (str) volume number, used only when creating a volume
 * - volume_year       : (str) volume year, used only when creating a volume
 * - section_id        : (int) existing section id, reused as-is if set
 * - section_title_fr  : (str) French section title — used to find or create a section when section_id is empty
 * - section_title_en  : (str) English section title
 * - uid               : (int) contributor id (default: randomly pick a chief editor)
 * - publication_date  : (str) publication date
 * - editors           : (str) dash-separated ('-') editor uids
 * - doi               : (str) DOI
 * - docid             : (int) paper docid (matched against an existing paper, for updates)
 * - rvid              : (str) journal RVID (numeric) or RVCODE (string) — required
 * - submission_date   : (str) submission date (default: now)
 *
 * Replaces the CSV parsing half of scripts/update_papers.php (JournalScript).
 */
final class Row
{
    public const COL_IDENTIFIER = 0;
    public const COL_REPOID = 1;
    public const COL_VERSION = 2;
    public const COL_STATUS = 3;
    public const COL_VOLUME_ID = 4;
    public const COL_VOLUME_TITLE_FR = 5;
    public const COL_VOLUME_TITLE_EN = 6;
    public const COL_VOLUME_NUM = 7;
    public const COL_VOLUME_YEAR = 8;
    public const COL_SECTION_ID = 9;
    public const COL_SECTION_TITLE_FR = 10;
    public const COL_SECTION_TITLE_EN = 11;
    public const COL_UID = 12;
    public const COL_PUBLICATION_DATE = 13;
    public const COL_EDITORS = 14;
    public const COL_DOI = 15;
    public const COL_DOCID = 16;
    public const COL_RVID = 17;
    public const COL_SUBMISSION_DATE = 18;

    public function __construct(
        public readonly ?string $identifier,
        public readonly ?int $repoid,
        public readonly ?string $version,
        public readonly ?int $status,
        public readonly ?int $volumeId,
        public readonly ?string $volumeTitleFr,
        public readonly ?string $volumeTitleEn,
        public readonly ?string $volumeNum,
        public readonly ?string $volumeYear,
        public readonly ?int $sectionId,
        public readonly ?string $sectionTitleFr,
        public readonly ?string $sectionTitleEn,
        public readonly ?int $uid,
        public readonly ?string $publicationDate,
        public readonly ?string $editors,
        public readonly ?string $doi,
        public readonly ?int $docid,
        public readonly ?string $rvidOrCode,
        public readonly ?string $submissionDate,
    ) {
    }

    /**
     * @param array<int, string> $data
     */
    public static function fromCsvRow(array $data): self
    {
        return new self(
            identifier: self::getCol($data, self::COL_IDENTIFIER),
            repoid: self::getIntCol($data, self::COL_REPOID),
            version: self::getCol($data, self::COL_VERSION),
            status: self::getIntCol($data, self::COL_STATUS),
            volumeId: self::getIntCol($data, self::COL_VOLUME_ID),
            volumeTitleFr: self::getCol($data, self::COL_VOLUME_TITLE_FR),
            volumeTitleEn: self::getCol($data, self::COL_VOLUME_TITLE_EN),
            volumeNum: self::getCol($data, self::COL_VOLUME_NUM),
            volumeYear: self::getCol($data, self::COL_VOLUME_YEAR),
            sectionId: self::getIntCol($data, self::COL_SECTION_ID),
            sectionTitleFr: self::getCol($data, self::COL_SECTION_TITLE_FR),
            sectionTitleEn: self::getCol($data, self::COL_SECTION_TITLE_EN),
            uid: self::getIntCol($data, self::COL_UID),
            publicationDate: self::getCol($data, self::COL_PUBLICATION_DATE),
            editors: self::getCol($data, self::COL_EDITORS),
            doi: self::getCol($data, self::COL_DOI),
            docid: self::getIntCol($data, self::COL_DOCID),
            rvidOrCode: self::getCol($data, self::COL_RVID),
            submissionDate: self::getCol($data, self::COL_SUBMISSION_DATE),
        );
    }

    /**
     * @return array<string, string> lang => title, non-empty only
     */
    public function volumeTitles(): array
    {
        return array_filter(
            ['fr' => $this->volumeTitleFr, 'en' => $this->volumeTitleEn],
            static fn(?string $title): bool => $title !== null && $title !== ''
        );
    }

    /**
     * @return array<string, string> lang => title, non-empty only
     */
    public function sectionTitles(): array
    {
        return array_filter(
            ['fr' => $this->sectionTitleFr, 'en' => $this->sectionTitleEn],
            static fn(?string $title): bool => $title !== null && $title !== ''
        );
    }

    /**
     * The CSV status, or null if absent or not one of Episciences_Paper::STATUS_CODES —
     * letting the caller's default (published) apply instead of persisting an unknown status.
     */
    public function validatedStatus(): ?int
    {
        return $this->hasInvalidStatus() ? null : $this->status;
    }

    /**
     * True when a status was given in the CSV but isn't one of Episciences_Paper::STATUS_CODES
     * (used by the caller to decide whether to log a warning before falling back to the default).
     */
    public function hasInvalidStatus(): bool
    {
        return $this->status !== null && !in_array($this->status, Episciences_Paper::STATUS_CODES, true);
    }

    /**
     * @param array<int, string> $data
     */
    public static function getCol(array $data, int $col): ?string
    {
        if (!array_key_exists($col, $data)) {
            return null;
        }
        $value = trim($data[$col]);
        return $value === '' ? null : $value;
    }

    /**
     * True when fgetcsv() returned the single-null-field array it produces for a blank line.
     *
     * @param array<int, string|null> $data
     */
    public static function isBlankCsvRecord(array $data): bool
    {
        return count($data) === 1 && $data[0] === null;
    }

    /**
     * @param array<int, string> $data
     */
    private static function getIntCol(array $data, int $col): ?int
    {
        $value = self::getCol($data, $col);
        return $value !== null && ctype_digit($value) ? (int)$value : null;
    }
}