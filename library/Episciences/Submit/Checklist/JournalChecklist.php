<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

use JsonException;

/**
 * Submission checklist: the ordered checkboxes of a journal (RVID = journal) or of the default
 * list (RVID = 0). Stored as JSON in REVIEW_SETTING (SETTING = 'submissionChecklist').
 *
 * Reading (fromArray / fromJson) is tolerant: a malformed item stored in the database is
 * skipped so that the submission form keeps working. Strict checks of what an admin posts
 * belong to ChecklistValidator.
 *
 */
final readonly class JournalChecklist
{
    public const FORMAT_VERSION = 1;

    /**
     * @param list<ChecklistItem> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    /**
     * Builds a checklist from decoded JSON; malformed items are skipped.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawItems = $data['items'] ?? [];
        $items = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $rawItem) {
                $item = self::itemFromArray($rawItem);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return new self($items);
    }

    /**
     * @return self|null null when the string is not JSON or not a JSON object / array
     */
    public static function fromJson(string $json): ?self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? self::fromArray($data) : null;
    }

    /**
     * @return array{version: int, items: list<array{key: ?string, labels: array{en: string, fr: string}}>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'items' => array_map(
                static fn (ChecklistItem $item): array => $item->toArray(),
                $this->items
            ),
        ];
    }

    /**
     * JSON stored in the database. Non-ASCII characters are escaped (é) on purpose:
     * no JSON_UNESCAPED_UNICODE, so that 4-byte characters cannot break the utf8mb3 column.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Keys of the saved items, in order (items not saved yet have no key).
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->items as $item) {
            if ($item->key !== null) {
                $keys[] = $item->key;
            }
        }

        return $keys;
    }

    /**
     * One stored item, or null when it is malformed.
     */
    private static function itemFromArray(mixed $raw): ?ChecklistItem
    {
        if (!is_array($raw) || !is_array($raw['labels'] ?? null)) {
            return null;
        }

        $key = $raw['key'] ?? null;
        $en = $raw['labels']['en'] ?? null;
        $fr = $raw['labels']['fr'] ?? null;

        if (($key !== null && !is_string($key)) || !is_string($en) || !is_string($fr)) {
            return null;
        }

        return new ChecklistItem($key, ['en' => $en, 'fr' => $fr]);
    }
}
