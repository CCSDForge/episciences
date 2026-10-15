<?php

namespace Episciences\Paper\Spdx;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;


class LicenseManager
{

    public const RECOMMENDED = 1;

    /** @var array<string, array> fetchRecommended() results, by columns and fetch mode */
    private static array $recommendedCache = [];

    /** @var string[]|null loadSpdxCode() result */
    private static ?array $spdxCodesCache = null;

    private static function allQuery(string|array $cols = '*'): Zend_Db_Select
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

    /**
     * Recommended licenses, cached for the current process (the same page may need them several times,
     * e.g. the journal licenses form and each of its license selects)
     */
    public static function fetchRecommended(string|array $cols = ['code', 'name'], $fetch = 'object'): ?array
    {
        $cacheKey = serialize([$cols, $fetch]);

        if (array_key_exists($cacheKey, self::$recommendedCache)) {
            return self::$recommendedCache[$cacheKey];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery($cols)->where('recommended = ?', self::RECOMMENDED);
        $result = $db?->fetchAssoc($query);
        self::fetchProcess($result, $fetch);

        if ($result !== null) {
            self::$recommendedCache[$cacheKey] = $result;
        }

        return $result;
    }

    public static function clearCache(): void
    {
        self::$recommendedCache = [];
        self::$spdxCodesCache = null;
    }

    /**
     * @param string|array|null $codes SPDX code(s)
     * @return string[] non-empty trimmed codes, without duplicates
     */
    public static function normalizeCodes(string|array|null $codes): array
    {
        $codes = array_map(static fn($code) => trim((string)$code), (array)$codes);

        return array_values(array_unique(array_filter($codes, static fn(string $code) => $code !== '')));
    }

    /**
     * SPDX license identifiers are matched case-insensitively
     * @param string[] $codes
     */
    public static function containsCode(string $code, array $codes): bool
    {
        foreach ($codes as $candidate) {
            if (strcasecmp($code, (string)$candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string> code => name of the recommended licenses
     */
    public static function fetchRecommendedOptions(): array
    {
        $options = [];

        foreach (self::fetchRecommended() ?? [] as $license) {
            $options[$license->getCode()] = $license->getName();
        }

        return $options;
    }

    private static function fetchProcess(?array &$result, $fetch = 'array'): void
    {

        if ($fetch !== 'object' || $result === null) {
            return;
        }

        $array_map = array_map(static function ($data) {
            return new License($data);
        }, $result);
        $tmp = $array_map;

        $result = $tmp;
    }

    /**
     * All the SPDX codes, cached for the current process (a LicenseSpdxResolver is built
     * several times per request, e.g. by each Episciences_Paper::getSpdxLicenseCode() call)
     */
    public static function loadSpdxCode(): ?array
    {
        if (self::$spdxCodesCache !== null) {
            return self::$spdxCodesCache;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery('code');
        $query->order('recommended DESC');
        self::$spdxCodesCache = $db?->fetchCol($query);

        return self::$spdxCodesCache;
    }

    public static function getNameByIdentifier(string $code): ?string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = self::allQuery('name');
        $query->where('code = ?', $code);
        return $db?->fetchOne($query);

    }
}
