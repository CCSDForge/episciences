<?php

declare(strict_types=1);

namespace Episciences\User;

use geertw\IpAnonymizer\IpAnonymizer;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table;

/**
 * Service handling local audit logging of Switch User (su) sessions in the
 * Episciences database (`user_su_log` table), replacing the historical CAS SU_LOG.
 */
final class SuLogger
{
    public const ACTION_GRANTED = 'GRANTED';
    public const ACTION_DENIED = 'DENIED';

    public const TABLE_NAME = 'user_su_log';

    /**
     * Records a switch user attempt (granted or denied).
     *
     * @param int $fromUid Initiator user UID
     * @param int $toUid Target user UID
     * @param string $action 'GRANTED' or 'DENIED'
     * @param int $rvid Current journal id (0 for portal)
     * @param ?string $reason Rejection reason code when DENIED
     * @param ?string $ipAddress Client IP address (IPv4 or IPv6)
     * @param ?string $userAgent Client HTTP user agent
     * @param ?string $sessionId Current session identifier
     * @param ?array<string, mixed> $details Additional snapshot context
     * @param ?Zend_Db_Adapter_Abstract $adapter Optional database adapter (for testing)
     * @return bool True if logged successfully, false otherwise
     */
    public static function log(
        int $fromUid,
        int $toUid,
        string $action,
        int $rvid = 0,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        ?array $details = null,
        ?Zend_Db_Adapter_Abstract $adapter = null
    ): bool {
        try {
            $db = $adapter ?? Zend_Db_Table::getDefaultAdapter();
            if ($db === null) {
                return false;
            }

            $data = [
                'from_uid' => $fromUid,
                'to_uid' => $toUid,
                'rvid' => $rvid,
                'action' => $action,
                'reason' => $reason,
                'ip_address' => $ipAddress !== null ? substr($ipAddress, 0, 45) : null,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
                'session_id' => $sessionId !== null ? substr($sessionId, 0, 128) : null,
                'details' => !empty($details) ? json_encode($details, JSON_THROW_ON_ERROR) : null,
                'is_anonymized' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            return $db->insert(self::TABLE_NAME, $data) > 0;
        } catch (\Throwable $e) {
            trigger_error('Failed to write to ' . self::TABLE_NAME . ': ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Anonymizes an IPv4 or IPv6 address:
     * - IPv4: /16 mask (255.255.0.0), e.g. 1.1.1.1 -> 1.1.0.0
     * - IPv6: /48 mask (ffff:ffff:ffff::), e.g. 2001:db8:85a3:8d3::1 -> 2001:db8:85a3::
     */
    public static function anonymizeIp(string $ip): string
    {
        $anonymizer = new IpAnonymizer();
        $anonymizer->ipv4NetMask = '255.255.0.0';
        $anonymizer->ipv6NetMask = 'ffff:ffff:ffff:0000:0000:0000:0000:0000';
        return $anonymizer->anonymize($ip);
    }

    /**
     * Anonymizes IP addresses in user_su_log records older than the retention threshold.
     *
     * @param int $retentionDays Number of retention days before anonymizing (default 365)
     * @param ?Zend_Db_Adapter_Abstract $adapter Optional database adapter
     * @return int Number of updated rows
     */
    public static function anonymizeOldLogs(int $retentionDays = 365, ?Zend_Db_Adapter_Abstract $adapter = null): int
    {
        $db = $adapter ?? Zend_Db_Table::getDefaultAdapter();
        if ($db === null) {
            return 0;
        }

        $sql = 'SELECT id, ip_address FROM ' . self::TABLE_NAME .
            ' WHERE is_anonymized = 0 AND ip_address IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)';
        $rows = $db->fetchAll($sql, [$retentionDays]);
        $updated = 0;

        foreach ($rows as $row) {
            $rawIp = (string)$row['ip_address'];
            $anonymized = self::anonymizeIp($rawIp);
            $db->update(
                self::TABLE_NAME,
                [
                    'ip_address' => $anonymized,
                    'is_anonymized' => 1
                ],
                ['id = ?' => (int)$row['id']]
            );
            $updated++;
        }

        return $updated;
    }
}
