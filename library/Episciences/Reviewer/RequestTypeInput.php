<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences\Form\Validate\DeadlineUnit;
use InvalidArgumentException;

/**
 * Review request type posted by the settings form: checks the values and builds the type, or lists the errors
 * (messages to translate, by field name).
 *
 * Posted fields: label[<lang>], deadline + deadline_unit (both empty: journal setting),
 * position, enabled (absent: disabled), light_answer (absent: answered with an account and the rating grid),
 * counts_as_report (absent: the reports inform only; ignored for the review report and with light_answer),
 * visible_to_author (absent: the reports stay with the editorial team; ignored for the review report, always passed on),
 * instructions[<lang>] (optional).
 */
final class RequestTypeInput
{
    /** error of the whole form, not of a field */
    public const ERROR_FORM = 'form';
    public const FIELD_LABEL = 'label';
    public const FIELD_DEADLINE = 'deadline';
    public const FIELD_POSITION = 'position';
    public const FIELD_ENABLED = 'enabled';
    /** answered from the invitation link, without an account (#319); never for the review report */
    public const FIELD_LIGHT_ANSWER = 'light_answer';
    /** the reports of the type count like review reports (#319) */
    public const FIELD_COUNTS_AS_REPORT = 'counts_as_report';
    /** the reports of the type are passed on to the author (#319, lot D) */
    public const FIELD_VISIBLE_TO_AUTHOR = 'visible_to_author';
    /** instructions[<lang>], shown on the page of the answer without an account; optional */
    public const FIELD_INSTRUCTIONS = 'instructions';

    /** @var array<string, string> */
    private array $errors = [];

    private ?RequestType $type = null;

    /**
     * @param array<string, mixed> $post
     * @param list<string> $languages language codes of the journal; a label is required in each of them
     * @param RequestType|null $existing the type being edited, null for a new type
     */
    public function __construct(array $post, int $rvid, array $languages, ?RequestType $existing = null)
    {
        $labels = $this->labels($post[self::FIELD_LABEL] ?? null, $languages);
        $instructions = $this->instructions($post[self::FIELD_INSTRUCTIONS] ?? null, $languages);
        $deadline = $this->deadline($post);
        $position = $this->position($post[self::FIELD_POSITION] ?? '');
        // the review report type stays enabled: it is the type of the requests made before the types existed
        $enabled = ($existing?->isFullReport() ?? false) || !empty($post[self::FIELD_ENABLED]);
        $isFullReport = $existing?->isFullReport() ?? false;
        $lightAnswer = !$isFullReport && !empty($post[self::FIELD_LIGHT_ANSWER]);
        // an answer without an account is not a rating report: it never counts
        $countsAsReport = $isFullReport || (!$lightAnswer && !empty($post[self::FIELD_COUNTS_AS_REPORT]));
        // the review reports are always passed on to the author
        $visibleToAuthor = $isFullReport || !empty($post[self::FIELD_VISIBLE_TO_AUTHOR]);

        if ($this->errors !== []) {
            return;
        }

        try {
            $this->type = $existing !== null
                ? $existing->withSettings($labels, $deadline, $position, $enabled, $lightAnswer, $instructions, $countsAsReport, $visibleToAuthor)
                // a type created by the journal: the default types are created by the application
                : new RequestType(null, $rvid, null, $labels, $lightAnswer, $deadline, $position, $enabled, $instructions, $countsAsReport, $visibleToAuthor);
        } catch (InvalidArgumentException) {
            $this->errors[self::ERROR_FORM] = "Ce type de demande n'a pas pu être enregistré.";
        }
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string> message to translate, by field name (label fields: "label_<lang>")
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getType(): ?RequestType
    {
        return $this->type;
    }

    /**
     * @param list<string> $languages
     * @return array<string, string>
     */
    private function labels(mixed $posted, array $languages): array
    {
        $labels = [];
        $posted = is_array($posted) ? $posted : [];

        foreach ($languages as $lang) {
            $label = trim(self::stringValue($posted[$lang] ?? ''));

            if ($label === '') {
                $this->errors[self::FIELD_LABEL . '_' . $lang] = 'Le libellé est obligatoire.';
            } elseif (mb_strlen($label) > RequestType::LABEL_MAX_LENGTH) {
                $this->errors[self::FIELD_LABEL . '_' . $lang] = 'Le libellé ne doit pas dépasser 100 caractères.';
            } else {
                $labels[$lang] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param list<string> $languages
     * @return array<string, string>
     */
    private function instructions(mixed $posted, array $languages): array
    {
        $instructions = [];
        $posted = is_array($posted) ? $posted : [];

        foreach ($languages as $lang) {
            $text = trim(self::stringValue($posted[$lang] ?? ''));

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) > RequestType::INSTRUCTIONS_MAX_LENGTH) {
                $this->errors[self::FIELD_INSTRUCTIONS . '_' . $lang] = 'La consigne ne doit pas dépasser 1000 caractères.';
                continue;
            }

            $instructions[$lang] = $text;
        }

        return $instructions;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function deadline(array $post): ?string
    {
        $value = trim(self::stringValue($post[self::FIELD_DEADLINE] ?? ''));

        if ($value === '') {
            return null;
        }

        $interval = DeadlineUnit::buildInterval($post, self::FIELD_DEADLINE);

        if ($interval === null || DeadlineUnit::parseStoredInterval($interval) === null) {
            $this->errors[self::FIELD_DEADLINE] = 'Le délai doit être un nombre entier positif suivi de son unité.';
            return null;
        }

        return $interval;
    }

    private function position(mixed $posted): int
    {
        $posted = trim(self::stringValue($posted));

        if ($posted === '') {
            return 0;
        }

        if (!ctype_digit($posted) || (int)$posted > 999) {
            $this->errors[self::FIELD_POSITION] = 'La position doit être un nombre entier entre 0 et 999.';
            return 0;
        }

        return (int)$posted;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string)$value : '';
    }
}
