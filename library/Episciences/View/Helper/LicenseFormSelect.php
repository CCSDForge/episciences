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

    public function licenseFormSelect(string $name, ?string $value = null, array $attribs = []): string
    {
        $value = ($value !== null && trim($value) !== '') ? trim($value) : null;

        if (!isset($attribs['id'])) {
            $attribs['id'] = $name;
        }

        $attribs['class'] = $attribs['class'] ?? sprintf('%s-select', $name);

        return $this->view->formSelect($name, $value, $attribs, $this->buildOptions($value));
    }

    /**
     * @return array<string, string> SPDX code => "name (code)", empty option first, then sorted by label
     */
    protected function buildOptions(?string $currentValue = null): array
    {
        $options = [];

        foreach ($this->fetchRecommendedLicenses() as $license) {
            $options[$license->getCode()] = self::formatLabel($license->getName(), $license->getCode());
        }

        if ($currentValue !== null && !array_key_exists($currentValue, $options)) {
            $options[$currentValue] = self::formatLabel((string)$this->fetchLicenseName($currentValue), $currentValue);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return ['' => $this->view->translate(self::EMPTY_LABEL)] + $options;
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
