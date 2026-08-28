<?php
declare(strict_types=1);

namespace Episciences\Section\Import;

use Episciences_Section;

/**
 * Immutable value object for one row of a journal-sections import CSV.
 *
 * Expected CSV format (semicolon-separated, with header row):
 *   rvid;position;title_fr;title_en;description_fr;description_en;status
 *
 * - rvid           : (int) journal RVID — required per row
 * - position       : (int) section position; auto-incremented if empty
 * - title_fr       : (str) French title (at least one of title_fr / title_en required)
 * - title_en       : (str) English title
 * - description_fr : (str) French description (only used when title_fr is set)
 * - description_en : (str) English description (only used when title_en is set)
 * - status         : (str) 1 = open (default), 0 = closed
 */
final class Row
{
    public const COL_RVID = 0;
    public const COL_POSITION = 1;
    public const COL_TITLE_FR = 2;
    public const COL_TITLE_EN = 3;
    public const COL_DESC_FR = 4;
    public const COL_DESC_EN = 5;
    public const COL_STATUS = 6;

    public function __construct(
        public readonly ?int $rvid,
        public readonly ?int $position,
        public readonly ?string $titleFr,
        public readonly ?string $titleEn,
        public readonly ?string $descriptionFr,
        public readonly ?string $descriptionEn,
        public readonly ?string $status,
    ) {
    }

    /**
     * @param array<int, string> $data
     */
    public static function fromCsvRow(array $data): self
    {
        return new self(
            rvid: self::getIntCol($data, self::COL_RVID),
            position: self::getIntCol($data, self::COL_POSITION),
            titleFr: self::getCol($data, self::COL_TITLE_FR),
            titleEn: self::getCol($data, self::COL_TITLE_EN),
            descriptionFr: self::getCol($data, self::COL_DESC_FR),
            descriptionEn: self::getCol($data, self::COL_DESC_EN),
            status: self::getCol($data, self::COL_STATUS),
        );
    }

    /**
     * @return array<string, string> lang => title, non-empty only
     */
    public function titles(): array
    {
        return array_filter(
            ['fr' => $this->titleFr, 'en' => $this->titleEn],
            static fn(?string $title): bool => $title !== null && $title !== ''
        );
    }

    /**
     * Descriptions restricted to languages that also have a title — a description
     * without a matching title language is meaningless to Episciences_Section.
     *
     * @param array<string, string> $titles lang => title
     * @return array<string, string>
     */
    public function descriptionsForTitles(array $titles): array
    {
        return array_filter(
            ['fr' => $this->descriptionFr, 'en' => $this->descriptionEn],
            static fn(?string $description, string $lang): bool =>
                $description !== null && $description !== '' && array_key_exists($lang, $titles),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param array<string, string> $titles lang => title
     * @return array<int, string> languages with a non-empty description but no matching title
     */
    public function orphanedDescriptionLanguages(array $titles): array
    {
        $orphaned = [];
        foreach (['fr' => $this->descriptionFr, 'en' => $this->descriptionEn] as $lang => $description) {
            if ($description !== null && $description !== '' && !array_key_exists($lang, $titles)) {
                $orphaned[] = $lang;
            }
        }

        return $orphaned;
    }

    /**
     * Returns SECTION_OPEN_STATUS when the value is empty or unrecognised.
     */
    public static function parseStatus(?string $raw): int
    {
        if ($raw === null) {
            return Episciences_Section::SECTION_OPEN_STATUS;
        }

        $trimmed = trim($raw);

        // is_numeric guards against non-numeric strings like 'abc' whose (int) cast would
        // silently produce 0 and accidentally match SECTION_CLOSED_STATUS.
        if ($trimmed === '' || !is_numeric($trimmed)) {
            return Episciences_Section::SECTION_OPEN_STATUS;
        }

        $statusInt = (int)$trimmed;

        return in_array(
            $statusInt,
            [Episciences_Section::SECTION_OPEN_STATUS, Episciences_Section::SECTION_CLOSED_STATUS],
            true
        ) ? $statusInt : Episciences_Section::SECTION_OPEN_STATUS;
    }

    /**
     * True when the raw status value is non-blank but doesn't parse to a recognised status
     * (used by the caller to decide whether to log a warning before falling back to open).
     */
    public static function isStatusInvalid(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return false;
        }

        if (!is_numeric($trimmed)) {
            return true;
        }

        return !in_array(
            (int)$trimmed,
            [Episciences_Section::SECTION_OPEN_STATUS, Episciences_Section::SECTION_CLOSED_STATUS],
            true
        );
    }

    /**
     * @param array<int, string> $data
     */
    private static function getCol(array $data, int $col): ?string
    {
        if (!array_key_exists($col, $data)) {
            return null;
        }
        $value = trim($data[$col]);
        return $value === '' ? null : $value;
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
