<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

use JsonException;

/**
 * Checks the checklist posted by the admin page before it is saved.
 *
 * The browser is never trusted: texts are trimmed and stripped of tags, lengths are checked,
 * a key must be one of the keys already saved for the journal (a new checkbox has no key, the
 * repository assigns it), and the encoded JSON must fit in the REVIEW_SETTING.VALUE column.
 *
 * Errors are translation keys; "item" is the 1-based position of the checkbox concerned, or
 * null for an error about the whole list. The view translates them and links them to the row
 * (aria-describedby).
 */
final class ChecklistValidator
{
    public const MAX_ITEMS = 10;
    public const MAX_LABEL_LENGTH = 500;      // characters (mb_strlen)
    public const MAX_JSON_BYTES = 60000;      // REVIEW_SETTING.VALUE is a TEXT column (65 535 bytes)
    public const KEY_PATTERN = '/^c_[0-9a-f]{6}$/';
    public const LOCALES = ['en', 'fr'];

    public const ERROR_NO_ITEM = 'Add at least one statement';
    public const ERROR_TOO_MANY_ITEMS = 'Too many statements';
    public const ERROR_TOO_LARGE = 'The checklist is too long';
    public const ERROR_INVALID_ITEM = 'Invalid statement';
    public const ERROR_INVALID_KEY = 'Unknown statement';
    public const ERROR_DUPLICATE_KEY = 'This statement appears twice';
    public const ERROR_LABEL_REQUIRED = [
        'en' => 'The English text is required',
        'fr' => 'The French text is required',
    ];
    public const ERROR_LABEL_TOO_LONG = [
        'en' => 'The English text is too long',
        'fr' => 'The French text is too long',
    ];

    /**
     * @param int $maxJsonBytes lowered in tests only
     */
    public function __construct(private readonly int $maxJsonBytes = self::MAX_JSON_BYTES)
    {
    }

    /**
     * @param array<mixed> $raw decoded JSON posted by the admin form: [{key?, labels: {en, fr}}, ...]
     * @param JournalChecklist|null $current checklist saved for the journal (null if none)
     * @return array{checklist: ?JournalChecklist, errors: list<array{message: string, item: ?int}>}
     */
    public function validate(array $raw, ?JournalChecklist $current): array
    {
        $errors = $this->checkCount($raw);
        $knownKeys = $current?->keys() ?? [];
        $seenKeys = [];
        $items = [];

        foreach (array_values($raw) as $index => $rawItem) {
            $item = $this->validateItem($rawItem, $index + 1, $knownKeys, $seenKeys, $errors);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        if ($errors !== []) {
            return ['checklist' => null, 'errors' => $errors];
        }

        $checklist = new JournalChecklist($items);
        $errors = $this->checkSize($checklist);

        return ['checklist' => $errors === [] ? $checklist : null, 'errors' => $errors];
    }

    /**
     * 1 to MAX_ITEMS checkboxes.
     *
     * @param array<mixed> $raw
     * @return list<array{message: string, item: ?int}>
     */
    private function checkCount(array $raw): array
    {
        if ($raw === []) {
            return [self::error(self::ERROR_NO_ITEM)];
        }

        if (count($raw) > self::MAX_ITEMS) {
            return [self::error(self::ERROR_TOO_MANY_ITEMS)];
        }

        return [];
    }

    /**
     * @param list<string> $knownKeys keys saved for the journal
     * @param list<string> $seenKeys keys already met in this POST (updated)
     * @param list<array{message: string, item: ?int}> $errors (updated)
     */
    private function validateItem(mixed $rawItem, int $position, array $knownKeys, array &$seenKeys, array &$errors): ?ChecklistItem
    {
        if (!is_array($rawItem)) {
            $errors[] = self::error(self::ERROR_INVALID_ITEM, $position);
            return null;
        }

        $errorCount = count($errors);
        $key = $this->validateKey($rawItem['key'] ?? null, $position, $knownKeys, $seenKeys, $errors);
        $labels = $this->validateLabels($rawItem['labels'] ?? null, $position, $errors);

        if ($labels === null || count($errors) > $errorCount) {
            return null;
        }

        return new ChecklistItem($key, $labels);
    }

    /**
     * No key = new checkbox. Otherwise the key must match KEY_PATTERN, belong to the saved list
     * and appear only once.
     *
     * @param list<string> $knownKeys
     * @param list<string> $seenKeys (updated)
     * @param list<array{message: string, item: ?int}> $errors (updated)
     */
    private function validateKey(mixed $key, int $position, array $knownKeys, array &$seenKeys, array &$errors): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key) || !in_array($key, $knownKeys, true)) {
            $errors[] = self::error(self::ERROR_INVALID_KEY, $position);
            return null;
        }

        if (in_array($key, $seenKeys, true)) {
            $errors[] = self::error(self::ERROR_DUPLICATE_KEY, $position);
            return null;
        }

        $seenKeys[] = $key;

        return $key;
    }

    /**
     * @param list<array{message: string, item: ?int}> $errors (updated)
     * @return array{en: string, fr: string}|null null when a label is missing or invalid
     */
    private function validateLabels(mixed $rawLabels, int $position, array &$errors): ?array
    {
        if (!is_array($rawLabels)) {
            $rawLabels = [];
        }

        $labels = [];
        foreach (self::LOCALES as $locale) {
            $label = $this->validateLabel($rawLabels[$locale] ?? null, $locale, $position, $errors);
            if ($label !== null) {
                $labels[$locale] = $label;
            }
        }

        if (!isset($labels['en'], $labels['fr'])) {
            return null;
        }

        return ['en' => $labels['en'], 'fr' => $labels['fr']];
    }

    /**
     * Plain text only: tags removed, spaces trimmed, not empty, at most MAX_LABEL_LENGTH characters.
     *
     * @param list<array{message: string, item: ?int}> $errors (updated)
     */
    private function validateLabel(mixed $rawLabel, string $locale, int $position, array &$errors): ?string
    {
        $label = is_string($rawLabel) ? trim(strip_tags($rawLabel)) : '';

        if ($label === '') {
            $errors[] = self::error(self::ERROR_LABEL_REQUIRED[$locale], $position);
            return null;
        }

        if (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            $errors[] = self::error(self::ERROR_LABEL_TOO_LONG[$locale], $position);
            return null;
        }

        return $label;
    }

    /**
     * The encoded JSON (accents escaped as é, 6 bytes each) must fit in the column.
     *
     * @return list<array{message: string, item: ?int}>
     */
    private function checkSize(JournalChecklist $checklist): array
    {
        try {
            $bytes = strlen($checklist->toJson());
        } catch (JsonException) {
            return [self::error(self::ERROR_TOO_LARGE)];
        }

        return $bytes > $this->maxJsonBytes ? [self::error(self::ERROR_TOO_LARGE)] : [];
    }

    /**
     * @return array{message: string, item: ?int}
     */
    private static function error(string $message, ?int $item = null): array
    {
        return ['message' => $message, 'item' => $item];
    }
}
