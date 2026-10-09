<?php

declare(strict_types=1);

namespace Episciences\User;

use geertw\IpAnonymizer\IpAnonymizer;
use JsonException;
use Zend_Controller_Request_Http;
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
    public const ACTION_UNSU = 'UNSU';

    public const TABLE_NAME = 'user_su_log';

    /**
     * Key of the client-declared X-Forwarded-For chain in `details` (not trustworthy, removed on anonymization)
     */
    public const DETAILS_FORWARDED_FOR = 'forwarded_for';

    /**
     * Reason recorded when the session is switched back to answer a conflict of interest check
     */
    public const REASON_CONFLICT_CONFIRMATION = 'conflict_confirmation';

    /**
     * Records a switch user event (granted, denied or ended).
     *
     * @param int $fromUid Initiator user UID
     * @param int $toUid Target user UID
     * @param string $action One of the ACTION_* constants
     * @param int $rvid Current journal id (0 for portal)
     * @param ?string $reason Rejection reason code when DENIED
     * @param ?string $ipAddress Client IP address (IPv4 or IPv6)
     * @param ?string $userAgent Client HTTP user agent
     * @param ?string $sessionId Current session identifier, only its SHA-256 hash is stored
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
                'session_id' => self::hashSessionId($sessionId),
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
     * Client data recorded with switch user events.
     *
     * The IP address is the TCP peer (REMOTE_ADDR), which the client cannot forge. The
     * X-Forwarded-For chain is kept apart, as declared, since it is only meaningful behind a proxy.
     *
     * @return array{ipAddress: ?string, userAgent: ?string, details: array<string, string>}
     */
    public static function requestContext(?object $request): array
    {
        $context = ['ipAddress' => null, 'userAgent' => null, 'details' => []];

        if (!$request instanceof Zend_Controller_Request_Http) {
            return $context;
        }

        $remoteAddr = $request->getServer('REMOTE_ADDR');
        $userAgent = $request->getHeader('User-Agent');
        $forwardedFor = $request->getHeader('X-Forwarded-For');

        if (is_string($remoteAddr) && $remoteAddr !== '') {
            $context['ipAddress'] = $remoteAddr;
        }
        if (is_string($userAgent) && $userAgent !== '') {
            $context['userAgent'] = $userAgent;
        }
        if (is_string($forwardedFor) && $forwardedFor !== '') {
            $context['details'][self::DETAILS_FORWARDED_FOR] = substr($forwardedFor, 0, 255);
        }

        return $context;
    }

    /**
     * Session identifiers are bearer credentials: only a hash is kept, enough to correlate
     * the events of one switch user session.
     */
    public static function hashSessionId(?string $sessionId): ?string
    {
        return ($sessionId === null || $sessionId === '') ? null : hash('sha256', $sessionId);
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
     * Anonymizes user_su_log records older than the retention threshold: the IP address is masked,
     * the user agent, the session hash and the declared forwarded-for chain are removed.
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

        $sql = 'SELECT id, ip_address, details FROM ' . self::TABLE_NAME .
            ' WHERE is_anonymized = 0 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)';
        $rows = $db->fetchAll($sql, [$retentionDays]);
        $updated = 0;

        foreach ($rows as $row) {
            $rawIp = $row['ip_address'] ?? null;
            $db->update(
                self::TABLE_NAME,
                [
                    'ip_address' => ($rawIp === null || $rawIp === '') ? null : self::anonymizeIp((string)$rawIp),
                    'user_agent' => null,
                    'session_id' => null,
                    'details' => self::anonymizeDetails($row['details'] ?? null),
                    'is_anonymized' => 1
                ],
                ['id = ?' => (int)$row['id']]
            );
            $updated++;
        }

        return $updated;
    }

    private static function anonymizeDetails(?string $details): ?string
    {
        if ($details === null || $details === '') {
            return null;
        }

        try {
            $decoded = json_decode($details, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        unset($decoded[self::DETAILS_FORWARDED_FOR]);

        return $decoded === [] ? null : json_encode($decoded, JSON_THROW_ON_ERROR);
    }
}
