<?php
declare(strict_types=1);

namespace Episciences\Paper\Export\Csv;

/**
 * Immutable value object for the papers CSV export criteria.
 *
 * Pure — built from already-resolved CLI option values, no DB access.
 */
final class Filters
{
    /**
     * @param int[] $docids
     * @param int[] $statuses
     */
    public function __construct(
        public readonly int $rvid,
        public readonly ?int $volumeId = null,
        public readonly ?int $sectionId = null,
        public readonly ?int $year = null,
        public readonly array $docids = [],
        public readonly ?string $identifier = null,
        public readonly ?string $version = null,
        public readonly array $statuses = [],
        public readonly ?int $repoid = null,
        public readonly ?int $uid = null,
        public readonly ?string $sqlWhere = null,
        public readonly bool $versionIgnored = false,
    ) {
    }

    /**
     * Builds a Filters DTO from raw Symfony Console option values, given the already-resolved
     * journal RVID (see Episciences\Paper\Import\ReviewResolver).
     *
     * @param array<string, mixed> $options keyed by option name: volume-id, section-id, year,
     *                                       docid, identifier, version, status, repoid, uid, sql-where
     */
    public static function fromOptions(array $options, int $resolvedRvid): self
    {
        $identifier = self::stringOrNull($options['identifier'] ?? null);
        $version = self::stringOrNull($options['version'] ?? null);
        // A version filter without an identifier to scope it to is meaningless (VERSION alone
        // isn't unique across papers) — drop it rather than filtering on VERSION = X repo-wide.
        $versionIgnored = $version !== null && $identifier === null;

        return new self(
            rvid: $resolvedRvid,
            volumeId: self::intOrNull($options['volume-id'] ?? null),
            sectionId: self::intOrNull($options['section-id'] ?? null),
            year: self::intOrNull($options['year'] ?? null),
            docids: self::intList($options['docid'] ?? []),
            identifier: $identifier,
            version: $versionIgnored ? null : $version,
            statuses: self::intList($options['status'] ?? []),
            repoid: self::intOrNull($options['repoid'] ?? null),
            uid: self::intOrNull($options['uid'] ?? null),
            sqlWhere: self::stringOrNull($options['sql-where'] ?? null),
            versionIgnored: $versionIgnored,
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        $value = self::stringOrNull($value);
        return $value !== null && ctype_digit($value) ? (int)$value : null;
    }

    /**
     * @return int[]
     */
    private static function intList(mixed $values): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $result = [];
        foreach ($values as $value) {
            $intValue = self::intOrNull($value);
            if ($intValue !== null) {
                $result[] = $intValue;
            }
        }

        return array_values(array_unique($result));
    }
}
