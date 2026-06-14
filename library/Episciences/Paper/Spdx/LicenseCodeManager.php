<?php

namespace Episciences\Paper\Spdx;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;

class LicenseCodeManager
{

    /**
     * @param LicenseCode $licenseCode
     * @return int
     */
    public static function save(LicenseCode $licenseCode): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$db) {
            throw new \RuntimeException('The database adapter has not been initialized correctly.');
        }


        $safeTable = $db->quoteIdentifier(T_PAPER_LICENSE_CODE);
        $safeCode = $db->quoteIdentifier('code');

        $docId = $licenseCode->getDocid();
        $code = $licenseCode->getCode();


        if (!$docId || !$code) {
            throw new \InvalidArgumentException("Values 'docid' and 'code' cannot be null.");
        }


        $sql = "INSERT INTO {$safeTable} (docid, {$safeCode}) VALUES (:docId, :code) ON DUPLICATE KEY UPDATE code= :code";
        $stmt = $db->prepare($sql);

        $stmt->execute([
                ':docId' => $docId,
                ':code' => $code,
        ]);

        return (int)$db->lastInsertId();
    }

    private static function getLicenseCodeQuery(?int $docId = null): ?Zend_Db_Select
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return $db?->select()->from(T_PAPER_LICENSE_CODE, 'code')->where('docid = ? ', $docId);
    }

    public static function getCode(?int $docId = null): false|null|string
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = self::getLicenseCodeQuery($docId);

        return $db?->fetchOne($sql);

    }
}