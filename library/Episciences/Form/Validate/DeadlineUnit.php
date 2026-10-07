<?php

declare(strict_types=1);

namespace Episciences\Form\Validate;

use Episciences_Review;
use Zend_Validate_Abstract;

/**
 * Validates the unit posted along with a deadline setting.
 *
 * The unit is not a form element (it is rendered by the deadline view script): this validator is attached to
 * the value element and reads the unit from the form context.
 */
class DeadlineUnit extends Zend_Validate_Abstract
{
    public const INVALID_UNIT = 'invalidUnit';

    /** @var array<string, string> */
    protected $_messageTemplates = [
        self::INVALID_UNIT => "L'unité du délai est invalide.",
    ];

    public function __construct(private readonly string $unitKey)
    {
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->_setValue(is_scalar($value) ? (string)$value : '');

        if (!is_array($context) || !self::isKnownUnit($context[$this->unitKey] ?? null)) {
            $this->_error(self::INVALID_UNIT);
            return false;
        }

        return true;
    }

    public static function isKnownUnit(mixed $unit): bool
    {
        return in_array($unit, Episciences_Review::DEADLINE_UNITS, true);
    }

    /**
     * Builds a strtotime()-compatible interval ("2 month") from the posted value and unit,
     * or returns null when either of them is not usable.
     *
     * @param array<string, mixed> $post
     */
    public static function buildInterval(array $post, string $valueKey): ?string
    {
        $value = $post[$valueKey] ?? null;
        $unit = $post[$valueKey . '_unit'] ?? null;

        if ((!is_string($value) && !is_int($value)) || !ctype_digit(trim((string)$value)) || !self::isKnownUnit($unit)) {
            return null;
        }

        return trim((string)$value) . ' ' . $unit;
    }
}
