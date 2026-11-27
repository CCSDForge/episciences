<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

/**
 * Converts the rows posted by the admin page into the list checked by ChecklistValidator.
 *
 * The page posts items[i][key|en|fr] (one row per checkbox, plus empty rows to add new
 * ones). A row whose two texts are empty is dropped: it is an unused empty row, or a checkbox the
 * manager emptied to delete it. Nothing else is checked here: the validator does it.
 */
final class ChecklistPostedRows
{
    /**
     * @return list<array{key: ?string, labels: array{en: mixed, fr: mixed}}>
     */
    public static function toRaw(mixed $posted): array
    {
        if (!is_array($posted)) {
            return [];
        }

        $raw = [];
        foreach ($posted as $row) {
            if (!is_array($row) || self::isEmpty($row)) {
                continue;
            }

            $key = $row['key'] ?? null;
            $raw[] = [
                'key' => is_string($key) && $key !== '' ? $key : null,
                'labels' => ['en' => $row['en'] ?? '', 'fr' => $row['fr'] ?? ''],
            ];
        }

        return $raw;
    }

    /**
     * @param array<mixed> $row
     */
    private static function isEmpty(array $row): bool
    {
        foreach (['en', 'fr'] as $locale) {
            $text = $row[$locale] ?? '';
            if (!is_string($text) || trim($text) !== '') {
                return false;
            }
        }

        return true;
    }
}
