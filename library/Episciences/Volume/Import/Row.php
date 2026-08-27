<?php
declare(strict_types=1);

namespace Episciences\Volume\Import;

/**
 * Immutable value object for one row of a journal-volumes import CSV.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   position;status;current_issue;special_issue;bib_reference;title_en;title_fr;description_en;description_fr;num;year
 *
 * - position       : (int) volume position; ignored if empty (currently unused by the importer)
 * - status         : (int) volume status flag
 * - current_issue  : (int) 1 if current issue, 0 otherwise
 * - special_issue  : (int) 1 if special issue, 0 otherwise
 * - bib_reference  : (str) bibliographic reference (optional)
 * - title_en       : (str) English title (at least one of title_en / title_fr required)
 * - title_fr       : (str) French title
 * - description_en : (str) English description (optional)
 * - description_fr : (str) French description (optional)
 * - num            : (str) volume number (optional)
 * - year           : (str) volume year (optional)
 */
final class Row
{
    public const COL_POSITION = 0;
    public const COL_STATUS = 1;
    public const COL_CURRENT_ISSUE = 2;
    public const COL_SPECIAL_ISSUE = 3;
    public const COL_BIB_REFERENCE = 4;
    public const COL_TITLE_EN = 5;
    public const COL_TITLE_FR = 6;
    public const COL_DESC_EN = 7;
    public const COL_DESC_FR = 8;
    public const COL_NUM = 9;
    public const COL_YEAR = 10;

    public function __construct(
        public readonly ?string $position,
        public readonly ?string $status,
        public readonly ?string $currentIssue,
        public readonly ?string $specialIssue,
        public readonly ?string $bibReference,
        public readonly ?string $titleEn,
        public readonly ?string $titleFr,
        public readonly ?string $descriptionEn,
        public readonly ?string $descriptionFr,
        public readonly ?string $num,
        public readonly ?string $year,
    ) {
    }

    /**
     * @param array<int, string> $data
     */
    public static function fromCsvRow(array $data): self
    {
        return new self(
            position: self::getCol($data, self::COL_POSITION),
            status: self::getCol($data, self::COL_STATUS),
            currentIssue: self::getCol($data, self::COL_CURRENT_ISSUE),
            specialIssue: self::getCol($data, self::COL_SPECIAL_ISSUE),
            bibReference: self::getCol($data, self::COL_BIB_REFERENCE),
            titleEn: self::getCol($data, self::COL_TITLE_EN),
            titleFr: self::getCol($data, self::COL_TITLE_FR),
            descriptionEn: self::getCol($data, self::COL_DESC_EN),
            descriptionFr: self::getCol($data, self::COL_DESC_FR),
            num: self::getCol($data, self::COL_NUM),
            year: self::getCol($data, self::COL_YEAR),
        );
    }

    /**
     * @return array<string, string> lang => title, non-empty only
     */
    public function titles(): array
    {
        return array_filter(
            ['en' => $this->titleEn, 'fr' => $this->titleFr],
            static fn(?string $title): bool => $title !== null && $title !== ''
        );
    }

    /**
     * @return array<string, string> lang => description, non-empty only
     */
    public function descriptions(): array
    {
        return array_filter(
            ['en' => $this->descriptionEn, 'fr' => $this->descriptionFr],
            static fn(?string $description): bool => $description !== null && $description !== ''
        );
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
}
