<?php

declare(strict_types=1);

class Ccsd_Form_Validate_NotSame extends Zend_Validate_Abstract
{
    public const SAME = 'same';
    public const MISSING_TOKEN = 'missingToken';
    protected bool $_group = true;
    /** @var array<string, string> */
    protected $_messageTemplates = [
        self::SAME => "Vous ne pouvez pas soumettre plus de deux valeurs pour une même langue",
        self::MISSING_TOKEN => 'Les valeurs passées ne sont pas valides',
    ];
    /** @var array<string, bool> */
    private array $_count = [];

    public function __construct(bool $grouponly = true)
    {
        $this->setGroup($grouponly);
    }

    public function isGroup(): bool
    {
        return $this->_group;
    }

    public function setGroup(bool $group): void
    {
        $this->_group = $group;
    }

    public function isValid(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $lang => $val) {
                $langKey = (string)$lang;
                if (!array_key_exists($langKey, $this->_count)) {
                    $this->_count[$langKey] = true;
                } else {
                    $this->_count[$langKey] = false;
                }
            }

            if (!array_reduce($this->_count, function (bool $v, bool $w): bool {
                return $v && $w;
            }, true)) {
                $this->_error(self::SAME);
                return false;
            }

            return true;
        }

        $this->_error(self::MISSING_TOKEN);
        return false;
    }
}
