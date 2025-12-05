<?php

use geertw\IpAnonymizer\IpAnonymizer;

class Episciences_Paper_Visits
{
    public const CONSULT_TYPE_NOTICE = 'notice';
    public const CONSULT_TYPE_FILE = 'file';
    public const PAGE_COUNT_METRICS_NAME = 'page_count';
    public const FILE_COUNT_METRICS_NAME = 'file_count';

    /**
     * @param $docId
     * @param string $consult
     * @throws Zend_Db_Adapter_Exception
     */
    public static function add($docId, string $consult = self::CONSULT_TYPE_NOTICE): void
    {

        /**
         * Changer le type de la colonne IP à BIGINT ?
         * Possibilité de stocker les adresses IPv6 et IPv4 dans la même colonne
         *
         */
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $clientIp = Zend_Controller_Front::getInstance()?->getRequest()?->getClientIp();
        $clientIpAnon = self::anonymizeClientIp($clientIp);
        $clientIpAnon = $db?->quote($clientIpAnon);

        $ipAnonExp = "IFNULL(CASE WHEN IS_IPV4($clientIpAnon) THEN INET_ATON($clientIpAnon) ELSE INET_ATON('127.1') END, INET_ATON('127.1'))";

        $data = [
            'DOCID' => (int)$docId,
            'IP' => new Zend_Db_Expr($ipAnonExp),
            'HTTP_USER_AGENT' => self::getUserAgent(),
            'DHIT' => new Zend_Db_Expr('NOW()'),
            'CONSULT' => $consult
        ];


        $db?->insert(VISITS_TEMP, $data);

    }

    /**
     * @param $clientIp
     * @return string
     */
    protected static function anonymizeClientIp($clientIp): string
    {
        $ipAnonymizer = new IpAnonymizer();
        $ipAnonymizer->ipv4NetMask = "255.255.0.0";

        $anonymizedIp = $ipAnonymizer->anonymize($clientIp);

        if ($anonymizedIp === '' || $anonymizedIp === null) {
            $anonymizedIp = '127.0.0.1';
        }

        return $anonymizedIp;
    }

    /**
     * @return false|string
     */
    public static function getUserAgent(): bool|string
    {
        $userAgent = false;
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $userAgent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $userAgent = substr($userAgent, 0, 2000);
        }
        if (!$userAgent) {
            $userAgent = 'Unknown';
        }
        return $userAgent;
    }

    /**
     * @param $docId
     * @param string $consult
     * @return int|string
     * @deprecated @see self::getPaperMetricsByPaperId())
     */
    public static function count($docId, string $consult = self::CONSULT_TYPE_NOTICE): int|string
    {
        trigger_error(
            __METHOD__ . ' is deprecated. ' .
            'use self::getPaperMetricsByPaperId()',
            E_USER_DEPRECATED
        );

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db?->select()
            ->from(T_PAPER_VISITS, new Zend_Db_Expr('SUM(COUNTER)'))
            ->where('DOCID = ?', $docId)
            ->where('ROBOT = 0')
            ->where('CONSULT = ?', $consult);
        return (int)$db?->fetchOne($sql);
    }

    /**
     * Get all access metrics for a PaperID
     */
    public static function getPaperMetricsByPaperId(int $paperId): array
    {
        $allDocIdsArray = Episciences_PapersManager::getDocIdsFromPaperId($paperId);
        $allDocIdsArray = array_column($allDocIdsArray, 'DOCID');
        return self::countAccessMetricForDocIds($allDocIdsArray);
    }

    private static function countAccessMetricForDocIds(array $docIds): array
    {
        $result = [
            self::PAGE_COUNT_METRICS_NAME => 0,
            self::FILE_COUNT_METRICS_NAME => 0
        ];

        // Validate and convert all docIds to integers to prevent SQL injection
        $docIds = array_map('intval', $docIds);
        $docIds = array_filter($docIds, static fn($id) => $id > 0);

        // Return default result if no valid docIds
        if (empty($docIds)) {
            return $result;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db?->select()
            ->from(
                T_PAPER_VISITS,
                [
                    self::PAGE_COUNT_METRICS_NAME => new Zend_Db_Expr("SUM(CASE WHEN CONSULT = '" . self::CONSULT_TYPE_NOTICE . "' THEN COUNTER ELSE 0 END)"),
                    self::FILE_COUNT_METRICS_NAME => new Zend_Db_Expr("SUM(CASE WHEN CONSULT = '" . self::CONSULT_TYPE_FILE . "' THEN COUNTER ELSE 0 END)")
                ]
            )
            ->where('ROBOT = 0')
            ->where('DOCID IN (?)', $docIds);

        try {
            $result = $db?->fetchRow($sql);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return $result;
    }
}
