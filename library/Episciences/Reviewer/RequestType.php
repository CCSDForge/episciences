<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences\Form\Validate\DeadlineUnit;
use InvalidArgumentException;
use JsonException;

/**
 * Type of a review request (quick opinion, review report, revised version check...), defined per journal.
 *
 * Every reviewer invitation is made for one type: it selects the invitation e-mail template, and later the rating
 * grid. A reviewer may hold requests of different types on the same paper version.
 */
final class RequestType
{
    public const CODE_FULL_REPORT = 'full_report';
    public const CODE_QUICK_OPINION = 'quick_opinion';
    public const CODE_REVISION_CHECK = 'revision_check';

    /** Lower-case letters, digits and underscores, starting with a letter: the code is part of a mail template key */
    public const CODE_PATTERN = '/^[a-z][a-z0-9_]{0,29}$/';
    public const LABEL_MAX_LENGTH = 100;
    /** Codes that would clash with an existing mail template key (paper_reviewer_invitation_<code>) */
    public const RESERVED_CODES = ['generic'];

    private const DEFAULTS = [
        self::CODE_FULL_REPORT => [
            'labels' => ['fr' => 'Rapport de relecture', 'en' => 'Review report'],
            'deadline' => null,
        ],
        self::CODE_QUICK_OPINION => [
            'labels' => ['fr' => 'Avis rapide', 'en' => 'Quick opinion'],
            'deadline' => '2 week',
        ],
        self::CODE_REVISION_CHECK => [
            'labels' => ['fr' => "Relecture d'une version révisée", 'en' => 'Revised version check'],
            'deadline' => '3 week',
        ],
    ];

    /** @var array<string, string> label per language */
    private array $labels;

    /**
     * @param array<string, string> $labels label per language, at least one
     * @param string|null $defaultDeadline stored interval ("2 week"); null = journal setting
     * @throws InvalidArgumentException on an invalid code, label or deadline
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $rvid,
        private readonly string $code,
        array $labels,
        private readonly ?string $grid = null,
        private readonly bool $lightAnswer = false,
        private readonly ?string $defaultDeadline = null,
        private readonly int $position = 0,
        private readonly bool $enabled = true
    ) {
        if (preg_match(self::CODE_PATTERN, $code) !== 1 || in_array($code, self::RESERVED_CODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid review request type code "%s"', $code));
        }

        if ($defaultDeadline !== null && DeadlineUnit::parseStoredInterval($defaultDeadline) === null) {
            throw new InvalidArgumentException(sprintf('Invalid default deadline "%s"', $defaultDeadline));
        }

        if ($code === self::CODE_FULL_REPORT && !$enabled) {
            // the review report is the type of every request made before the types existed
            throw new InvalidArgumentException('The review report type cannot be disabled');
        }

        $this->labels = self::cleanLabels($labels);
    }

    /**
     * The types every journal starts with
     * @return list<self>
     */
    public static function defaults(int $rvid): array
    {
        $types = [];
        $position = 1;

        foreach (self::DEFAULTS as $code => $default) {
            $types[] = new self(null, $rvid, $code, $default['labels'], null, false, $default['deadline'], $position++);
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $row a row of the review_request_type table
     */
    public static function fromRow(array $row): self
    {
        try {
            $labels = json_decode((string)($row['labels'] ?? ''), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $labels = [];
        }

        return new self(
            isset($row['id']) ? (int)$row['id'] : null,
            (int)$row['rvid'],
            (string)$row['code'],
            is_array($labels) && $labels !== [] ? $labels : ['en' => (string)$row['code']],
            isset($row['grid']) && $row['grid'] !== '' ? (string)$row['grid'] : null,
            (bool)($row['light_answer'] ?? false),
            isset($row['default_deadline']) && $row['default_deadline'] !== '' ? (string)$row['default_deadline'] : null,
            (int)($row['position'] ?? 0),
            (bool)($row['enabled'] ?? true)
        );
    }

    /**
     * @return array<string, int|string|null> the columns of the review_request_type table, without the id
     * @throws JsonException
     */
    public function toRow(): array
    {
        return [
            'rvid' => $this->rvid,
            'code' => $this->code,
            'labels' => json_encode($this->labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'grid' => $this->grid,
            'light_answer' => (int)$this->lightAnswer,
            'default_deadline' => $this->defaultDeadline,
            'position' => $this->position,
            'enabled' => (int)$this->enabled,
        ];
    }

    public function withId(int $id): self
    {
        return new self($id, $this->rvid, $this->code, $this->labels, $this->grid, $this->lightAnswer, $this->defaultDeadline, $this->position, $this->enabled);
    }

    /**
     * @param array<string, string> $labels
     */
    public function withSettings(array $labels, ?string $defaultDeadline, int $position, bool $enabled): self
    {
        return new self($this->id, $this->rvid, $this->code, $labels, $this->grid, $this->lightAnswer, $defaultDeadline, $position, $enabled);
    }

    /**
     * Label in the requested language, else in the fallback language, else the first one
     */
    public function getLabel(string $locale, string $fallbackLocale = 'en'): string
    {
        return $this->labels[$locale] ?? $this->labels[$fallbackLocale] ?? reset($this->labels);
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRvid(): int
    {
        return $this->rvid;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getGrid(): ?string
    {
        return $this->grid;
    }

    public function isLightAnswer(): bool
    {
        return $this->lightAnswer;
    }

    public function getDefaultDeadline(): ?string
    {
        return $this->defaultDeadline;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isFullReport(): bool
    {
        return $this->code === self::CODE_FULL_REPORT;
    }

    public function isDefault(): bool
    {
        return array_key_exists($this->code, self::DEFAULTS);
    }

    /**
     * @param array<mixed> $labels
     * @return array<string, string>
     */
    private static function cleanLabels(array $labels): array
    {
        $clean = [];

        foreach ($labels as $locale => $label) {
            if (!is_string($locale) || preg_match('/^[a-z]{2}$/', $locale) !== 1 || !is_string($label)) {
                continue;
            }

            $label = trim($label);

            if ($label === '') {
                continue;
            }

            if (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
                throw new InvalidArgumentException(sprintf('Label longer than %d characters', self::LABEL_MAX_LENGTH));
            }

            $clean[$locale] = $label;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('A review request type needs at least one label');
        }

        return $clean;
    }
}
