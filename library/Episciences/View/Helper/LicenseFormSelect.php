<?php

use Episciences\Paper\Spdx\License;
use Episciences\Paper\Spdx\LicenseManager;


/**
 * View helper rendering a <select> of the recommended SPDX licenses.
 *
 * Usage:
 *
 * Simple
 * $this->licenseFormSelect('license')
 *
 * With a pre-selected value (SPDX code)
 * $this->licenseFormSelect('license', 'MIT')
 *
 * With custom attributes
 * $this->licenseFormSelect('license', 'Apache-2.0', ['class' => 'form-control']);
 *
 * If the pre-selected value is not a recommended license, it is added to the options
 * so that the current license of the document is never silently lost.
 */
class Episciences_View_Helper_LicenseFormSelect extends Zend_View_Helper_FormSelect
{
    public const EMPTY_LABEL = '--- Sélectionner une licence ---';

    /**
     * @param string|string[]|null $value SPDX code(s); an array for a multiple select
     * @param array|null $licenseCodes codes to offer (list of codes, or a code => label map as passed by
     *                                 Zend_Form_Decorator_ViewHelper); null: all the recommended licenses
     */
    public function licenseFormSelect(string $name, string|array|null $value = null, array $attribs = [], ?array $licenseCodes = null): string
    {
        $values = LicenseManager::normalizeCodes($value);
        $isMultiple = !empty($attribs['multiple']);

        if (!isset($attribs['id'])) {
            $attribs['id'] = $name;
        }

        $attribs['class'] = $attribs['class'] ?? sprintf('%s-select', $name);

        $options = $this->buildOptions($values, self::normalizeOfferedCodes($licenseCodes), !$isMultiple);

        // select the rendered option whatever the case of the saved code (e.g. "cc-by-4.0" selects "CC-BY-4.0")
        $values = array_map(static fn(string $code) => self::findOptionKey($code, $options) ?? $code, $values);

        return $this->view->formSelect($name, $isMultiple ? $values : ($values[0] ?? null), $attribs, $options);
    }

    /**
     * @param string[] $currentValues codes always offered, even if not recommended / not in $licenseCodes
     * @param string[]|null $licenseCodes restricts the recommended licenses; null: no restriction
     * @return array<string, string> SPDX code => "name (code)", optional empty option first, then sorted by label
     *
     * SPDX codes are matched case-insensitively: a code offered or saved with a different case
     * does not exclude the recommended license, nor add a duplicate option.
     */
    protected function buildOptions(array $currentValues = [], ?array $licenseCodes = null, bool $withEmptyOption = true): array
    {
        $options = [];

        foreach ($this->fetchRecommendedLicenses() as $license) {
            if ($licenseCodes === null || LicenseManager::containsCode($license->getCode(), $licenseCodes)) {
                $options[$license->getCode()] = self::formatLabel($license->getName(), $license->getCode());
            }
        }

        // offered codes that are not recommended: only the known SPDX licenses (they could not be saved otherwise)
        foreach ($licenseCodes ?? [] as $code) {
            if (self::findOptionKey($code, $options) === null && ($name = $this->fetchLicenseName($code)) !== null) {
                $options[$code] = self::formatLabel($name, $code);
            }
        }

        // the current values are always offered, so that they are never silently lost
        foreach ($currentValues as $code) {
            if (self::findOptionKey($code, $options) === null) {
                $options[$code] = self::formatLabel((string)$this->fetchLicenseName($code), $code);
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $withEmptyOption ? ['' => $this->view->translate(self::EMPTY_LABEL)] + $options : $options;
    }

    /**
     * Option key matching the SPDX code case-insensitively; null if there is none
     * @param array<string, string> $options
     */
    private static function findOptionKey(string $code, array $options): ?string
    {
        foreach (array_keys($options) as $key) {
            if (strcasecmp($code, (string)$key) === 0) {
                return (string)$key;
            }
        }

        return null;
    }

    /**
     * @return string[]|null
     */
    private static function normalizeOfferedCodes(?array $licenseCodes): ?array
    {
        if ($licenseCodes === null) {
            return null;
        }

        return LicenseManager::normalizeCodes(array_is_list($licenseCodes) ? $licenseCodes : array_keys($licenseCodes));
    }

    /**
     * "MIT License (MIT)"; the code alone when the name is missing or identical to it
     */
    public static function formatLabel(string $name, string $code): string
    {
        $name = trim($name);

        if ($name === '' || strcasecmp($name, $code) === 0) {
            return $code;
        }

        return sprintf('%s (%s)', $name, $code);
    }

    /**
     * @return License[]
     */
    protected function fetchRecommendedLicenses(): array
    {
        return LicenseManager::fetchRecommended() ?? [];
    }

    protected function fetchLicenseName(string $code): ?string
    {
        return LicenseManager::getNameByIdentifier($code) ?: null;
    }
}
