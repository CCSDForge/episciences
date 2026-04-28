<?php
/**
 * Custom validator for volume years (AAAA or AAAA-AAAA).
 * It enforces format, chronological order (Year 2 > Year 1), and temporal boundaries (1970 to CurrentYear + 5).
 */
class Episciences_Form_Validate_VolumeYear extends Zend_Validate_Abstract
{
    // Temporal boundaries constants
    const MIN_YEAR = 1970;

    // Error message keys
    const INVALID_FORMAT = 'invalidFormat';
    const INVALID_RANGE  = 'invalidRange';
    const OUTSIDE_BOUNDS = 'outsideBounds';

    // Message templates use translation keys (T_...) for Zend_Translate
    protected $_messageTemplates = [
        self::INVALID_FORMAT => 'T_VOLUME_YEAR_INVALID_FORMAT',
        self::INVALID_RANGE  => 'T_VOLUME_YEAR_INVALID_RANGE',
        // This static key will be overridden by the dynamic message in _createMessage
        self::OUTSIDE_BOUNDS => 'T_VOLUME_YEAR_OUTSIDE_BOUNDS',
    ];

    // Property to store the fully calculated dynamic error message
    protected $_fullOutsideBoundsMessage = '';

    /**
     * Checks if the provided value is valid.
     *
     * @param string $value The value to check.
     * @return bool
     */
    public function isValid($value)
    {
        // Handle array values explicitly to avoid "Array to string conversion" warning
        if (is_array($value)) {
            $this->_setValue($value);
            $this->_error(self::INVALID_FORMAT);
            return false;
        }

        // Convert to string to avoid PHP 8.1+ deprecation warnings with null
        $value = (string)$value;
        $this->_setValue($value);

        // Calculate the maximum allowed year: Current year + 5 years
        $maxYear = (int)date('Y') + 5;
        $minYear = self::MIN_YEAR;

        // Construct the full dynamic error message using safe PHP formatting
        $this->_fullOutsideBoundsMessage = sprintf(
            'L\'année doit être comprise entre %s et %s (Année courante + 5 ans).',
            $minYear,
            $maxYear
        );

        // 1. Format validation (Regex: YYYY or YYYY-YYYY)
        $pattern = '/^(\d{4}|\d{4}-\d{4})$/';
        if (!preg_match($pattern, $value)) {
            $this->_error(self::INVALID_FORMAT);
            return false;
        }

        // Determine years to check
        if (str_contains($value, '-')) {
            $parts = explode('-', $value);
            $year1 = (int)$parts[0];
            $year2 = (int)$parts[1];

            // 2. Chronological consistency check (Year 2 > Year 1)
            if ($year2 <= $year1) {
                $this->_error(self::INVALID_RANGE);
                return false;
            }
        } else {
            // Case for a single year (YYYY)
            $year1 = (int)$value;
            $year2 = $year1;
        }

        // 3. Temporal bounds check
        if ($year1 < $minYear || $year2 > $maxYear) {
            $this->_error(self::OUTSIDE_BOUNDS);
            return false;
        }

        return true;
    }

    /**
     * Overrides the default method to inject the pre-calculated dynamic error message
     * when the key is OUTSIDE_BOUNDS.
     *
     * @param string $messageKey
     * @param string $value
     * @return string
     */
    protected function _createMessage($messageKey, $value): string
    {
        if ($messageKey === self::OUTSIDE_BOUNDS) {
            // If Zend_Translate is used, translate the template and then inject the dynamic values.
            if ($this->hasTranslator()) {
                $messageTemplate = $this->getTranslator()->translate($this->_messageTemplates[$messageKey]);
                // We re-use the format to inject the variables dynamically.
                return sprintf($messageTemplate, self::MIN_YEAR, (int)date('Y') + 5);
            }

            // If no translator is available, return the pre-calculated French message
            return $this->_fullOutsideBoundsMessage;
        }

        // For other messages, rely on the parent method (which uses the translation keys)
        return parent::_createMessage($messageKey, $value);
    }
}