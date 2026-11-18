<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences\Form\Validate\DeadlineUnit;
use InvalidArgumentException;
use JsonException;

/**
 * Type of a review request (review report, quick opinion, or a type created by the journal), defined per journal.
 *
 * Every reviewer invitation is made for one type: it selects the invitation e-mail template, and the rating grid
 * when the journal created one for the type (grid_t<type id>.xml, see Episciences_GridsManager). A reviewer may hold
 * requests of different types on the same paper version.
 */
final class RequestType
{
    public const LABEL_MAX_LENGTH = 100;
    /** Longest instructions, in characters */
    public const INSTRUCTIONS_MAX_LENGTH = 1000;
    /** Suffix of the invitation template key of a type created by a journal: paper_reviewer_invitation_type_<id> */
    public const JOURNAL_TYPE_KEY_PREFIX = 'type_';

    /** Settings of the default types, by DefaultRequestType value */
    private const DEFAULTS = [
        DefaultRequestType::FullReport->value => [
            'labels' => ['fr' => 'Rapport de relecture', 'en' => 'Review report'],
            'deadline' => null,
            'instructions' => [],
        ],
        DefaultRequestType::QuickOpinion->value => [
            'labels' => ['fr' => 'Avis rapide', 'en' => 'Quick opinion'],
            'deadline' => '2 week',
            'instructions' => [
                'fr' => "Quelques lignes sur l'intérêt, l'originalité et la pertinence de l'article pour la revue suffisent ; il ne s'agit pas d'un rapport de relecture détaillé.",
                'en' => 'A few lines on the interest, originality and relevance of the article for the journal are enough; this is not a detailed review report.',
            ],
        ],
    ];

    /** @var array<string, string> label per language */
    private array $labels;

    /** @var array<string, string> instructions per language, shown to the invited person (answer without an account) */
    private array $instructions;

    private bool $countsAsReport;

    /**
     * @param DefaultRequestType|null $defaultType the default type it is, null for a type created by the journal
     * @param array<string, string> $labels label per language, at least one
     * @param string|null $defaultDeadline stored interval ("2 week"); null = journal setting
     * @param array<string, string> $instructions instructions per language (answer without an account)
     * @param bool $countsAsReport whether its reports count like review reports (reviewed status, required number of
     *     reports, average rating); always for the review report, never for a type answered without an account
     * @throws InvalidArgumentException on an invalid label or deadline, or settings a default type cannot have
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $rvid,
        private readonly ?DefaultRequestType $defaultType,
        array $labels,
        private readonly bool $lightAnswer = false,
        private readonly ?string $defaultDeadline = null,
        private readonly int $position = 0,
        private readonly bool $enabled = true,
        array $instructions = [],
        bool $countsAsReport = false
    ) {
        if ($defaultDeadline !== null && DeadlineUnit::parseStoredInterval($defaultDeadline) === null) {
            throw new InvalidArgumentException(sprintf('Invalid default deadline "%s"', $defaultDeadline));
        }

        $isFullReport = $defaultType === DefaultRequestType::FullReport;

        if ($isFullReport && !$enabled) {
            // the review report is the type of every request made before the types existed
            throw new InvalidArgumentException('The review report type cannot be disabled');
        }

        if ($isFullReport && $lightAnswer) {
            // a review report is written with the rating grid, by a reviewer with an account
            throw new InvalidArgumentException('The review report type cannot be answered without an account');
        }

        if ($lightAnswer && $countsAsReport) {
            // an answer without an account is not a rating report
            throw new InvalidArgumentException('A type answered without an account cannot count as a review report');
        }

        $this->labels = self::cleanLabels($labels);
        $this->instructions = self::cleanInstructions($instructions);
        $this->countsAsReport = $isFullReport || $countsAsReport;
    }

    /**
     * The types every journal starts with
     * @return list<self>
     */
    public static function defaults(int $rvid): array
    {
        $types = [];
        $position = 1;

        foreach (self::DEFAULTS as $value => $default) {
            $types[] = new self(
                null, $rvid, DefaultRequestType::from($value), $default['labels'], false, $default['deadline'], $position++, true,
                $default['instructions']
            );
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

        try {
            $instructions = json_decode((string)($row['instructions'] ?? '[]'), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $instructions = [];
        }

        $id = isset($row['id']) ? (int)$row['id'] : null;
        $defaultType = isset($row['default_type']) && $row['default_type'] !== ''
            ? DefaultRequestType::tryFrom((int)$row['default_type'])
            : null;

        return new self(
            $id,
            (int)$row['rvid'],
            $defaultType,
            // unreadable labels: a label is required, the type stays identifiable
            is_array($labels) && $labels !== [] ? $labels : ['en' => $defaultType?->code() ?? '#' . $id],
            (bool)($row['light_answer'] ?? false),
            isset($row['default_deadline']) && $row['default_deadline'] !== '' ? (string)$row['default_deadline'] : null,
            (int)($row['position'] ?? 0),
            (bool)($row['enabled'] ?? true),
            is_array($instructions) ? $instructions : [],
            (bool)($row['counts_as_report'] ?? false)
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
            'default_type' => $this->defaultType?->value,
            'labels' => json_encode($this->labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'instructions' => $this->instructions === [] ? null : json_encode($this->instructions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'light_answer' => (int)$this->lightAnswer,
            'default_deadline' => $this->defaultDeadline,
            'position' => $this->position,
            'enabled' => (int)$this->enabled,
            'counts_as_report' => (int)$this->countsAsReport,
        ];
    }

    public function withId(int $id): self
    {
        return new self(
            $id, $this->rvid, $this->defaultType, $this->labels, $this->lightAnswer, $this->defaultDeadline, $this->position, $this->enabled,
            $this->instructions, $this->countsAsReport
        );
    }

    /**
     * @param array<string, string> $labels
     * @param bool|null $lightAnswer null: unchanged
     * @param array<string, string>|null $instructions null: unchanged
     * @param bool|null $countsAsReport null: unchanged
     */
    public function withSettings(
        array $labels,
        ?string $defaultDeadline,
        int $position,
        bool $enabled,
        ?bool $lightAnswer = null,
        ?array $instructions = null,
        ?bool $countsAsReport = null
    ): self {
        return new self(
            $this->id, $this->rvid, $this->defaultType, $labels, $lightAnswer ?? $this->lightAnswer, $defaultDeadline, $position, $enabled,
            $instructions ?? $this->instructions, $countsAsReport ?? $this->countsAsReport
        );
    }

    /**
     * Instructions in the requested language, else in the fallback language, else the first ones; empty when none
     */
    public function getInstructions(string $locale, string $fallbackLocale = 'en'): string
    {
        return $this->instructions[$locale] ?? $this->instructions[$fallbackLocale] ?? (reset($this->instructions) ?: '');
    }

    /**
     * @return array<string, string>
     */
    public function getAllInstructions(): array
    {
        return $this->instructions;
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

    public function getDefaultType(): ?DefaultRequestType
    {
        return $this->defaultType;
    }

    /**
     * Suffix of the key of its invitation template (paper_reviewer_invitation_<suffix>): the code of a default type,
     * "type_<id>" for a type created by the journal; null for a type created by the journal and not saved yet
     */
    public function invitationKeySuffix(): ?string
    {
        if ($this->defaultType !== null) {
            return $this->defaultType->code();
        }

        return $this->id === null ? null : self::JOURNAL_TYPE_KEY_PREFIX . $this->id;
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
        return $this->defaultType === DefaultRequestType::FullReport;
    }

    /**
     * Whether its reports count like review reports: reviewed status, required number of reports, average rating
     */
    public function countsAsReport(): bool
    {
        return $this->countsAsReport;
    }

    public function isQuickOpinion(): bool
    {
        return $this->defaultType === DefaultRequestType::QuickOpinion;
    }

    public function isDefault(): bool
    {
        return $this->defaultType !== null;
    }

    /**
     * @param array<mixed> $instructions
     * @return array<string, string>
     */
    private static function cleanInstructions(array $instructions): array
    {
        $clean = [];

        foreach ($instructions as $locale => $text) {
            if (!is_string($locale) || preg_match('/^[a-z]{2}$/', $locale) !== 1 || !is_string($text) || trim($text) === '') {
                continue;
            }

            if (mb_strlen(trim($text)) > self::INSTRUCTIONS_MAX_LENGTH) {
                throw new InvalidArgumentException(sprintf('Instructions longer than %d characters', self::INSTRUCTIONS_MAX_LENGTH));
            }

            $clean[$locale] = trim($text);
        }

        return $clean;
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
