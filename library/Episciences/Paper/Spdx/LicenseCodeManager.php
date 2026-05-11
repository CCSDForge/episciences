<?php

namespace Episciences\Paper\Spdx;

use Zend_Db_Select;
use Zend_Db_Table_Abstract;

class LicenseCodeManager
{
    public static function save(LicenseCode $licenseCode): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $safeTable = $db->quoteIdentifier(T_PAPER_LICENSE_CODE);
        $safeCode = $db->quoteIdentifier('code');

        $sql = "INSERT INTO {$safeTable} (docid, {$safeCode}) VALUES (:docId, :code) ON DUPLICATE KEY UPDATE code= :code";
        $stmt = $db->prepare($sql);

        $stmt->execute([
                ':docId' => $licenseCode->getDocid(),
                ':code' => $licenseCode->getCode(),
        ]);

        return (int)$db->lastInsertId();
    }


    private static function getLicenceCodeQuery(?int $docId = null): ?Zend_Db_Select
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
        $sql = self::getLicenceCodeQuery($docId);

        return $db?->fetchOne($sql);


    }
}