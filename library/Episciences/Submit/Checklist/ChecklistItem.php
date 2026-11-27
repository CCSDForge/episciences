<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

/**
 * One checkbox of a journal submission checklist. Every checkbox is required: the author must
 * tick it to submit.
 *
 * The key is null for a checkbox not saved yet; the repository assigns it on save.
 */
final readonly class ChecklistItem
{
    public const DEFAULT_LOCALE = 'en';

    /**
     * @param array{en: string, fr: string} $labels
     */
    public function __construct(
        public ?string $key,
        public array $labels,
    ) {
    }

    /**
     * Label in the given locale, English for any other locale.
     */
    public function getLabel(string $locale): string
    {
        return $this->labels[$locale] ?? $this->labels[self::DEFAULT_LOCALE];
    }

    public function withKey(string $key): self
    {
        return new self($key, $this->labels);
    }

    /**
     * @return array{key: ?string, labels: array{en: string, fr: string}}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'labels' => $this->labels,
        ];
    }
}
