<?php

namespace Episciences\Paper\Spdx;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;


class LicenseManager
{

    public const RECOMMENDED = 1;

    public static function allQuery(string|array $cols = '*'): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(T_LICENSE, $cols);
    }

    public static function all(string|array $cols = '*'): ?array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery($cols);
        return $db?->fetchAssoc($query);
    }

    public static function fetchRecommended(string|array $cols = ['code', 'name'], $fetch = 'object'): ?array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery($cols)->where('recommended = ?', self::RECOMMENDED);
        $result = $db?->fetchAssoc($query);
        self::fetchProcess($result, $fetch);
        return $result;
    }

    private static function fetchProcess(?array &$result, $fetch = 'array'): void
    {

        $tmp = [];
        if ($fetch === 'object') {
            foreach ($result as $code => $data) {
                $tmp[$code] = new License($data);
            }

        }

        $result = $tmp;
    }

    public static function loadSpdxCode(): ?array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery('code');
        $query->order('recommended DESC');
        return $db?->fetchCol($query);

    }

    public static function getNameByIdentifier(string $code): ?string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery('name');
        $query->where('code = ?', $code);
        return $db?->fetchOne($query);

    }
}
