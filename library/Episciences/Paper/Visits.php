<?php
declare(strict_types=1);

use geertw\IpAnonymizer\IpAnonymizer;
use Psr\Log\LogLevel;

class Episciences_Paper_Visits
{
    public const CONSULT_TYPE_NOTICE = 'notice';
    public const CONSULT_TYPE_FILE = 'file';
    public const PAGE_COUNT_METRICS_NAME = 'page_count';
    public const FILE_COUNT_METRICS_NAME = 'file_count';

    /**
     * Record a paper visit in STAT_TEMP.
     *
     * The real (non-anonymized) IP is stored so the cron job (stats:process)
     * can perform GeoIP lookup, bot detection, and anonymization at processing time.
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function add(int $docId, string $consult = self::CONSULT_TYPE_NOTICE): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($db === null) {
            return;
        }

        // Zend_Controller_Front::getInstance() is always non-null (singleton).
        // getRequest() returns ?Zend_Controller_Request_Abstract; Http subclass has getClientIp().
        $request  = Zend_Controller_Front::getInstance()->getRequest();
        $clientIp = null;
        if ($request instanceof Zend_Controller_Request_Http) {
            $clientIp = $request->getClientIp();
            if (Episciences_Tools::isIPv6($clientIp)) {
                Episciences_View_Helper_Log::log('Paper visits with IPv6 client: ', LogLevel::INFO, [
                    'checkProxy' => true,
                    'clientIp'   => $clientIp,
                    'server'     => $request->getServer(),
                ]);
            }
        }

        $clientIpQuoted = $db->quote((string) $clientIp);
        $ipExpr = "IFNULL(CASE WHEN IS_IPV4($clientIpQuoted) THEN INET_ATON($clientIpQuoted) ELSE INET_ATON('127.1') END, INET_ATON('127.1'))";

        $db->insert(VISITS_TEMP, [
            'DOCID'           => $docId,
            'IP'              => new Zend_Db_Expr($ipExpr),
            'HTTP_USER_AGENT' => self::getUserAgent(),
            'DHIT'            => new Zend_Db_Expr('NOW()'),
            'CONSULT'         => $consult,
        ]);
    }

    /**
     * Return the current request's User-Agent, sanitized and truncated.
     * Returns 'Unknown' when no UA is present or when the value is empty after sanitization.
     */
    public static function getUserAgent(): string
    {
        $raw = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($raw === '') {
            return 'Unknown';
        }

        $sanitized = filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $userAgent  = is_string($sanitized) ? substr($sanitized, 0, 2000) : '';

        return $userAgent !== '' ? $userAgent : 'Unknown';
    }

    /**
     * Get all access metrics for a PaperID.
     *
     * @return array<string, int>
     */
    public static function getPaperMetricsByPaperId(int $paperId): array
    {
        $docIds = array_column(Episciences_PapersManager::getDocIdsFromPaperId($paperId), 'DOCID');
        return self::countAccessMetricForDocIds($docIds);
    }

    /**
     * @param array<int, mixed> $docIds
     * @return array<string, int>
     */
    private static function countAccessMetricForDocIds(array $docIds): array
    {
        $result = [
            self::PAGE_COUNT_METRICS_NAME => 0,
            self::FILE_COUNT_METRICS_NAME => 0,
        ];

        // Cast to int and drop non-positive values to prevent SQL injection.
        $docIds = array_values(
            array_filter(array_map('intval', $docIds), static fn(int $id): bool => $id > 0)
        );

        if (empty($docIds)) {
            return $result;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($db === null) {
            return $result;
        }

        $sql = $db->select()
            ->from(
                T_PAPER_VISITS,
                [
                    self::PAGE_COUNT_METRICS_NAME => new Zend_Db_Expr("SUM(CASE WHEN CONSULT = '" . self::CONSULT_TYPE_NOTICE . "' THEN COUNTER ELSE 0 END)"),
                    self::FILE_COUNT_METRICS_NAME => new Zend_Db_Expr("SUM(CASE WHEN CONSULT = '" . self::CONSULT_TYPE_FILE . "' THEN COUNTER ELSE 0 END)"),
                ]
            )
            ->where('ROBOT = 0')
            ->where('DOCID IN (?)', $docIds);

        try {
            $row = $db->fetchRow($sql);
            // fetchRow() returns array|false; cast SUM() string values to int.
            if (is_array($row)) {
                $result = [
                    self::PAGE_COUNT_METRICS_NAME => (int) ($row[self::PAGE_COUNT_METRICS_NAME] ?? 0),
                    self::FILE_COUNT_METRICS_NAME => (int) ($row[self::FILE_COUNT_METRICS_NAME] ?? 0),
                ];
            }
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return $result;
    }

    /**
     * @deprecated Use self::getPaperMetricsByPaperId() instead.
     */
    public static function count(int $docId, string $consult = self::CONSULT_TYPE_NOTICE): int
    {
        trigger_error(
            __METHOD__ . ' is deprecated. Use self::getPaperMetricsByPaperId()',
            E_USER_DEPRECATED
        );

        $db  = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db?->select()
            ->from(T_PAPER_VISITS, new Zend_Db_Expr('SUM(COUNTER)'))
            ->where('DOCID = ?', $docId)
            ->where('ROBOT = 0')
            ->where('CONSULT = ?', $consult);

        return (int) $db?->fetchOne($sql);
    }

    /**
     * @deprecated IP anonymization is now performed in ProcessStatTempCommand (stats:process cron).
     */
    protected static function anonymizeClientIp(string $clientIp): string
    {
        $anonymizer              = new IpAnonymizer();
        $anonymizer->ipv4NetMask = '255.255.0.0';
        $anonymizedIp            = $anonymizer->anonymize($clientIp);

        return $anonymizedIp !== '' ? $anonymizedIp : '127.0.0.1';
    }
}
