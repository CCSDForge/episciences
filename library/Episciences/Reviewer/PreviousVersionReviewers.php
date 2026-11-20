<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_User_Assignment;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * The reviewers of the previous versions of a paper (git #1387), so that the editor can invite some of them again on
 * the current version: one row per reviewer and per version, with what became of their request.
 */
final class PreviousVersionReviewers
{
    public const STATUS_REPORT_COMPLETED = 'report_completed';
    public const STATUS_REPORT_IN_PROGRESS = 'report_in_progress';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NOT_ANSWERED = 'not_answered';

    /**
     * @return list<array{
     *     docid: int, version: float, tmp_version: bool, version_date: string, uid: int, tmp_user: bool,
     *     firstname: string, lastname: string, email: string, screen_name: string, username: string, locale: string,
     *     status: string, date: string|null
     * }> latest version first
     */
    public static function ofPaper(Episciences_Paper $paper, ?Zend_Db_Adapter_Abstract $db = null): array
    {
        $paperId = (int)$paper->getPaperid();
        $when = $paper->getWhen();

        if ($paperId <= 0 || $paperId === (int)$paper->getDocid() || !$when) {
            return [];
        }

        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
        $versions = $db->fetchAll(
            $db->select()
                ->from(T_PAPERS, ['DOCID', 'VERSION', 'REPOID', 'WHEN'])
                ->where('PAPERID = ?', $paperId)
                ->where('RVID = ?', (int)$paper->getRvid())
                ->where('`WHEN` < ?', $when)
                ->order('WHEN DESC')
        );

        if ($versions === []) {
            return [];
        }

        $docIds = array_map(static fn(array $version): int => (int)$version['DOCID'], $versions);

        return self::fromRows($versions, $db->fetchAll(self::reviewersQuery($db, $docIds)));
    }

    /**
     * The given version when it is a previous version of the paper (same paper, same journal, submitted before), null
     * otherwise: what the invitation form posts is checked before it is used.
     */
    public static function previousVersionOf(Episciences_Paper $paper, mixed $docId, ?Zend_Db_Adapter_Abstract $db = null): ?int
    {
        if (!is_scalar($docId) || preg_match('/^[1-9]\d*$/', (string)$docId) !== 1 || (int)$docId === (int)$paper->getDocid() || !$paper->getWhen()) {
            return null;
        }

        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
        $found = $db->fetchOne(
            $db->select()
                ->from(T_PAPERS, ['DOCID'])
                ->where('DOCID = ?', (int)$docId)
                ->where('PAPERID = ?', (int)$paper->getPaperid())
                ->where('RVID = ?', (int)$paper->getRvid())
                ->where('`WHEN` < ?', $paper->getWhen())
        );

        return $found ? (int)$found : null;
    }

    /**
     * @param list<array<string, mixed>> $versions DOCID, VERSION, REPOID, WHEN, latest first
     * @param list<array<string, mixed>> $reviewers latest meaningful assignment of each (version, reviewer), with the
     *        report and the person: ITEMID, UID, TMP_USER, STATUS, WHEN, REPORT_STATUS, REPORT_DATE, FIRSTNAME,
     *        LASTNAME, EMAIL, SCREEN_NAME, USERNAME, LANGUEID
     * @return list<array{
     *     docid: int, version: float, tmp_version: bool, version_date: string, uid: int, tmp_user: bool,
     *     firstname: string, lastname: string, email: string, screen_name: string, username: string, locale: string,
     *     status: string, date: string|null
     * }>
     */
    public static function fromRows(array $versions, array $reviewers): array
    {
        $byVersion = [];
        foreach ($reviewers as $row) {
            $byVersion[(int)$row['ITEMID']][] = $row;
        }

        $rows = [];

        foreach ($versions as $version) {
            $docId = (int)$version['DOCID'];
            $versionRows = $byVersion[$docId] ?? [];
            usort($versionRows, static fn(array $a, array $b): int => [(string)$a['LASTNAME'], (string)$a['FIRSTNAME']] <=> [(string)$b['LASTNAME'], (string)$b['FIRSTNAME']]);

            foreach ($versionRows as $row) {
                [$status, $date] = self::statusOf($row);
                $rows[] = [
                    'docid' => $docId,
                    'version' => (float)$version['VERSION'],
                    'tmp_version' => (int)$version['REPOID'] === 0,
                    'version_date' => (string)$version['WHEN'],
                    'uid' => (int)$row['UID'],
                    'tmp_user' => (bool)(int)$row['TMP_USER'],
                    'firstname' => (string)($row['FIRSTNAME'] ?? ''),
                    'lastname' => (string)($row['LASTNAME'] ?? ''),
                    'email' => (string)($row['EMAIL'] ?? ''),
                    'screen_name' => (string)($row['SCREEN_NAME'] ?? ''),
                    'username' => (string)($row['USERNAME'] ?? ''),
                    'locale' => (string)($row['LANGUEID'] ?? ''),
                    'status' => $status,
                    'date' => $date,
                ];
            }
        }

        return $rows;
    }

    /**
     * What became of the request: the report first, else the latest answer to the invitation
     *
     * @param array<string, mixed> $row
     * @return array{string, string|null}
     */
    private static function statusOf(array $row): array
    {
        $reportStatus = isset($row['REPORT_STATUS']) ? (int)$row['REPORT_STATUS'] : null;
        $reportDate = self::dateOrNull($row['REPORT_DATE'] ?? null);
        $date = self::dateOrNull($row['WHEN'] ?? null);

        if ($reportStatus === Episciences_Rating_Report::STATUS_COMPLETED) {
            return [self::STATUS_REPORT_COMPLETED, $reportDate ?? $date];
        }

        if ($reportStatus === Episciences_Rating_Report::STATUS_WIP) {
            return [self::STATUS_REPORT_IN_PROGRESS, $reportDate ?? $date];
        }

        return [match ((string)$row['STATUS']) {
            Episciences_User_Assignment::STATUS_ACTIVE => self::STATUS_ACCEPTED,
            Episciences_User_Assignment::STATUS_DECLINED => self::STATUS_DECLINED,
            Episciences_User_Assignment::STATUS_EXPIRED => self::STATUS_EXPIRED,
            Episciences_User_Assignment::STATUS_CANCELLED => self::STATUS_CANCELLED,
            default => self::STATUS_NOT_ANSWERED,
        }, $date];
    }

    private static function dateOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Latest meaningful assignment of each (version, reviewer): the "inactive" rows added when a new version replaces
     * the paper, or when a reviewer is removed, say nothing about the request and are skipped.
     *
     * @param list<int> $docIds
     */
    private static function reviewersQuery(Zend_Db_Adapter_Abstract $db, array $docIds): \Zend_Db_Select
    {
        $latest = $db->select()
            ->from(T_ASSIGNMENTS, ['LAST_ID' => new Zend_Db_Expr('MAX(ID)')])
            ->where('ITEMID IN (?)', $docIds)
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->where('STATUS != ?', Episciences_User_Assignment::STATUS_INACTIVE)
            ->group(['ITEMID', 'UID', 'TMP_USER']);

        return $db->select()
            ->from(['a' => T_ASSIGNMENTS], ['ITEMID' => 'a.ITEMID', 'UID' => 'a.UID', 'TMP_USER' => 'a.TMP_USER', 'STATUS' => 'a.STATUS', 'WHEN' => 'a.WHEN'])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', [])
            ->joinLeft(['r' => T_REVIEWER_REPORTS], 'a.TMP_USER = 0 AND r.UID = a.UID AND r.DOCID = a.ITEMID', ['REPORT_STATUS' => 'r.STATUS', 'REPORT_DATE' => 'r.UPDATE_DATE'])
            ->joinLeft(['u' => T_USERS], 'a.TMP_USER = 0 AND u.UID = a.UID', [])
            ->joinLeft(['t' => T_TMP_USER], 'a.TMP_USER = 1 AND t.ID = a.UID', [])
            ->columns([
                'FIRSTNAME' => new Zend_Db_Expr('COALESCE(u.FIRSTNAME, t.FIRSTNAME)'),
                'LASTNAME' => new Zend_Db_Expr('COALESCE(u.LASTNAME, t.LASTNAME)'),
                'EMAIL' => new Zend_Db_Expr('COALESCE(u.EMAIL, t.EMAIL)'),
                'SCREEN_NAME' => 'u.SCREEN_NAME',
                'USERNAME' => 'u.USERNAME',
                'LANGUEID' => new Zend_Db_Expr('COALESCE(u.LANGUEID, t.LANG)'),
            ]);
    }
}
