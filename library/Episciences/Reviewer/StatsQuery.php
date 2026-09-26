<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Acl;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Builds the reviewer statistics dashboards (editors' global view and a
 * reviewer's own personal view).
 */
class StatsQuery
{
    public const ACTION_REMINDER_SENT = 'reminder_sent';

    /**
     * Public sort keys (used in URLs) mapped to the SELECT alias they order by.
     * Kept as an explicit allow-list so a sort key never gets interpolated into
     * SQL directly — only these known-safe aliases can end up in ORDER BY.
     */
    public const SORTABLE_COLUMNS = [
        'name' => 'SCREEN_NAME',
        'invitations_pending' => 'invitations_pending',
        'reviews_pending' => 'reviews_pending',
        'reviews_completed' => 'reviews_completed',
        'reviews_overdue' => 'reviews_overdue',
        'max_overdue_days' => 'max_overdue_days',
        'last_invitation_date' => 'last_invitation_date',
        'last_review_completed_date' => 'last_review_completed_date',
        'response_rate' => 'response_rate',
        'acceptance_rate' => 'acceptance_rate',
        'avg_review_delay_seconds' => 'avg_review_delay_seconds',
    ];

    private const DEFAULT_SORT = 'name';

    /**
     * Paper statuses for which `Episciences_Paper::canBeReviewed()` returns false — mirrors
     * that method exactly (same constant arrays, same combination), so an "invitation still
     * pending" count doesn't include invitations whose paper has since moved past active
     * review (published, refused, a new version requested, copy-editing started, ...).
     * `Episciences_Reviewer_Reviewing::loadStatus()` calls this state "obsolete"; without this
     * filter USER_INVITATION.STATUS stays 'pending' forever for those, wildly inflating the
     * "pending" count.
     *
     * @return list<int>
     */
    private static function nonReviewablePaperStatuses(): array
    {
        return array_values(array_unique(array_merge(
            \Episciences_Paper::$_noEditableStatus,
            [\Episciences_Paper::STATUS_ACCEPTED],
            \Episciences_Paper::STATUS_WITH_EXPECTED_REVISION,
            [
                \Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES,
                \Episciences_Paper::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION,
                \Episciences_Paper::STATUS_CE_AUTHOR_SOURCES_DEPOSED,
                \Episciences_Paper::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED,
                \Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
                \Episciences_Paper::STATUS_CE_AUTHOR_FORMATTING_DEPOSED,
                \Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
                \Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION,
            ],
            [
                \Episciences_Paper::STATUS_CE_READY_TO_PUBLISH,
                \Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
            ]
        )));
    }

    private Zend_Db_Adapter_Abstract $db;

    public function __construct(?Zend_Db_Adapter_Abstract $db = null)
    {
        $adapter = $db ?? Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($adapter === null) {
            throw new \RuntimeException('No default database adapter is available.');
        }
        $this->db = $adapter;
    }

    /**
     * Calculates reviewer invitation/review statistics for a given journal.
     *
     * The GDPR rolling-history cap only applies to the editors' global view:
     * pass $isPersonal = true to get a reviewer's own, uncapped history.
     *
     * @param int $rvid Current journal ID (RVID)
     * @param bool $isRestricted Applies editor partitioning/encapsulation (ignored when $isPersonal is true)
     * @param int $currentUserId ID of the logged-in user (for partitioning/personal filter)
     * @param int|null $periodMonths Rolling history limit in months (max 24), ignored when $isPersonal is true
     * @param string|null $search Optional. Text search on name, firstname, or email
     * @param bool $onlyPendingInvitations Filter only reviewers with pending invitations
     * @param bool $onlyPendingReviews Filter only reviewers with pending reviews
     * @param bool $onlyOverdue Filter only reviewers with overdue reviews (deadline passed)
     * @param bool $isPersonal Filter strictly for the reviewer's own personal stats (no GDPR cap, no restriction)
     * @param int $limit Results per page limit
     * @param int $offset Pagination offset
     * @param string $sort Sort key, must be a key of self::SORTABLE_COLUMNS (falls back to 'name' otherwise)
     * @param string $direction 'asc' or 'desc' (falls back to 'asc' otherwise)
     * @return array{total: int, data: array<int, array<string, mixed>>}
     */
    public function getReviewersGlobalStats(
        int $rvid,
        bool $isRestricted,
        int $currentUserId,
        ?int $periodMonths = 24,
        ?string $search = null,
        bool $onlyPendingInvitations = false,
        bool $onlyPendingReviews = false,
        bool $onlyOverdue = false,
        bool $isPersonal = false,
        int $limit = 50,
        int $offset = 0,
        string $sort = self::DEFAULT_SORT,
        string $direction = 'asc'
    ): array {
        // GDPR: the rolling-history cap only makes sense for the editors' view of
        // someone else's data. A reviewer looking at their own stats sees everything.
        $periodMonths = $isPersonal ? null : min(24, max(1, $periodMonths ?? 24));

        [$coreSql, $params] = $this->buildCoreQuery(
            $rvid,
            $isRestricted,
            $currentUserId,
            $periodMonths,
            $search,
            $isPersonal
        );

        $havingClauses = [];
        if ($onlyPendingInvitations) {
            $havingClauses[] = 'invitations_pending > 0';
        }
        if ($onlyPendingReviews) {
            $havingClauses[] = 'reviews_pending > 0';
        }
        if ($onlyOverdue) {
            $havingClauses[] = 'reviews_overdue > 0';
        }

        $coreSql .= ' GROUP BY identity_key';
        if (!empty($havingClauses)) {
            $coreSql .= ' HAVING ' . implode(' AND ', $havingClauses);
        }

        // Wrapping the exact same core query (WHERE + GROUP BY + HAVING) guarantees
        // the total always matches the filtered/paginated result set below.
        $countSql = 'SELECT COUNT(*) FROM (' . $coreSql . ') AS sub';
        $total = (int)$this->db->fetchOne($countSql, $params);

        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? self::SORTABLE_COLUMNS[self::DEFAULT_SORT];
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        // Reviewers whose account was since deleted have no name to sort on: MySQL sorts
        // NULL first in ASC order, which would otherwise flood page 1 with nameless rows.
        // SCREEN_NAME (what is actually displayed) is appended as a stable tiebreaker,
        // unless it's already the sort key.
        $orderBy = "no_identity ASC, $sortColumn $sortDirection";
        if ($sortColumn !== 'SCREEN_NAME') {
            $orderBy .= ', SCREEN_NAME ASC';
        }

        $dataSql = $coreSql . " ORDER BY $orderBy LIMIT " . max(0, $limit) . ' OFFSET ' . max(0, $offset);

        /** @var array<int, array<string, mixed>> $data */
        $data = $this->db->fetchAll($dataSql, $params);

        return [
            'total' => $total,
            'data' => $data,
        ];
    }

    /**
     * `Episciences_User_Invitation::save()` always INSERTs a new row (never updates one in
     * place): answering or cancelling an invitation inserts a new row under the same AID and
     * leaves the original one behind, still 'pending'. Most assignments therefore have more
     * than one USER_INVITATION row sharing the same AID. A plain `JOIN ... ON ua.ID = ui.AID`
     * fans a single assignment out into one row per state instead of one row per assignment,
     * double-counting a "pending" invitation that was really answered since.
     *
     * Mirrors `Episciences_PapersManager::getLatestInvitationByDocIdQuery()`: keep only the
     * invitation with the latest SENDING_DATE per AID.
     *
     * That latest row carries the *current state* (STATUS), but not the date the invitation
     * was actually sent: accepting/declining/cancelling inserts a new row whose SENDING_DATE
     * is the time of that transition. Every AID has exactly one 'pending' row (the
     * invitation really sent) and every accepted/declined/cancelled row is dated after it. FIRST_SENDING_DATE (earliest row of the AID) is
     * therefore the real sending date, and is what every "sent on"/period computation uses.
     *
     * `USER_INVITATION` had no index on AID (see src/mysql/2026-07-12-add-user-invitation-aid-index.sql).
     * A self-join-based dedup (`JOIN (SELECT AID, MAX(...) ... GROUP BY AID) ON ui1.AID =
     * ui2.AID`) is eligible for MySQL's derived-table merge optimization, which flattens it
     * back into the outer query — turning the join into a full table scan repeated *once per
     * outer assignment row* instead of once total: several minutes on a large journal, and a
     * gateway timeout on `/reviewersstats`. A window function is not eligible for
     * derived-table merge, so MySQL is forced to materialize this subquery exactly once.
     */
    private static function latestInvitationPerAssignmentSql(): string
    {
        return '(
            SELECT ui1.*
            FROM (
                SELECT
                    ui2.*,
                    MIN(SENDING_DATE) OVER (PARTITION BY AID) AS FIRST_SENDING_DATE,
                    ROW_NUMBER() OVER (PARTITION BY AID ORDER BY SENDING_DATE DESC, ID DESC) AS rn
                FROM `' . T_USER_INVITATIONS . '` ui2
            ) ui1
            WHERE ui1.rn = 1
        )';
    }

    /**
     * The reviewer's answer to one assignment, keyed by AID. USER_INVITATION_ANSWER points to
     * the invitation row that was answered — the original 'pending' one, *not* the later
     * accepted/declined row that latestInvitationPerAssignmentSql() keeps: joining answers on
     * the latest invitation's ID lost almost all of them (response and acceptance rates at
     * 0 %, average review time empty, sorting on them a no-op).
     * A handful of AIDs have several answers: the most recent one wins. Window function for
     * the same reason as latestInvitationPerAssignmentSql(): not merged into the outer query.
     */
    private static function latestAnswerPerAssignmentSql(): string
    {
        return '(
            SELECT uia1.ID, uia1.AID, uia1.ANSWER, uia1.ANSWER_DATE
            FROM (
                SELECT
                    uia2.ID,
                    ui3.AID,
                    uia2.ANSWER,
                    uia2.ANSWER_DATE,
                    ROW_NUMBER() OVER (PARTITION BY ui3.AID ORDER BY uia2.ANSWER_DATE DESC, uia2.ID DESC) AS rn
                FROM `' . T_USER_INVITATION_ANSWER . '` uia2
                JOIN `' . T_USER_INVITATIONS . '` ui3 ON ui3.ID = uia2.ID
            ) uia1
            WHERE uia1.rn = 1
        )';
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function buildCoreQuery(
        int $rvid,
        bool $isRestricted,
        int $currentUserId,
        ?int $periodMonths,
        ?string $search,
        bool $isPersonal
    ): array {
        // Use of backticks is mandatory (MySQL 8.4 compatibility, `USER` is a reserved keyword).
        // Identity columns are wrapped in MAX() because they are functionally single-valued
        // per identity_key group: required by ONLY_FULL_GROUP_BY (MySQL 8 default sql_mode).
        //
        // Grouping key: email is the real-world identity, so group by it whenever one is
        // known — this merges reviewers across BOTH cases:
        // (a) the same external/unregistered reviewer (TMP_USER = 1) invited to several
        //     papers gets a brand new USER_TMP row every time, so UID alone fragments them
        //     into several rows even though it's the same person and same email;
        // (b) a reviewer first invited while unregistered (TMP_USER = 1, USER_TMP row) who
        //     later registered a real account (TMP_USER = 0, USER row): same email, two
        //     different UIDs, one per path.
        // UID is only used as a last-resort key when no email is on record at all.
        $isReviewableExpr = 'p.STATUS IS NOT NULL AND p.STATUS NOT IN (' . implode(',', self::nonReviewablePaperStatuses()) . ')';

        $sql = '
            SELECT
                CASE
                    WHEN COALESCE(u.EMAIL, ut.EMAIL) IS NOT NULL AND COALESCE(u.EMAIL, ut.EMAIL) != \'\'
                        THEN CONCAT(\'e:\', LOWER(COALESCE(u.EMAIL, ut.EMAIL)))
                    WHEN ua.TMP_USER = 0 THEN CONCAT(\'u:\', ua.UID)
                    ELSE CONCAT(\'t:\', ua.UID)
                END AS identity_key,
                MIN(ua.UID) AS UID,
                MAX(ua.TMP_USER) AS TMP_USER,
                MAX(TRIM(COALESCE(u.LASTNAME, ut.LASTNAME))) AS LASTNAME,
                MAX(TRIM(COALESCE(u.FIRSTNAME, ut.FIRSTNAME))) AS FIRSTNAME,
                MAX(TRIM(COALESCE(NULLIF(u.SCREEN_NAME, \'\'), NULLIF(TRIM(CONCAT(u.FIRSTNAME, \' \', u.LASTNAME)), \'\'), TRIM(CONCAT(ut.FIRSTNAME, \' \', ut.LASTNAME))))) AS SCREEN_NAME,
                MAX(COALESCE(u.EMAIL, ut.EMAIL)) AS EMAIL,
                MAX(CASE WHEN u.UID IS NOT NULL OR ut.ID IS NOT NULL THEN 0 ELSE 1 END) AS no_identity,
                COUNT(DISTINCT ui.ID) AS total_invitations,
                COUNT(DISTINCT uia.ID) AS total_responses,
                COUNT(DISTINCT CASE WHEN uia.ANSWER = \'yes\' THEN uia.ID END) AS total_accepted,
                COUNT(DISTINCT CASE WHEN uia.ANSWER = \'no\' THEN uia.ID END) AS total_declined,
                CASE WHEN COUNT(DISTINCT ui.ID) > 0
                    THEN (COUNT(DISTINCT uia.ID) / COUNT(DISTINCT ui.ID)) * 100
                    ELSE 0 END AS response_rate,
                CASE WHEN COUNT(DISTINCT uia.ID) > 0
                    THEN (COUNT(DISTINCT CASE WHEN uia.ANSWER = \'yes\' THEN uia.ID END) / COUNT(DISTINCT uia.ID)) * 100
                    ELSE 0 END AS acceptance_rate,
                AVG(TIMESTAMPDIFF(SECOND, ui.FIRST_SENDING_DATE, uia.ANSWER_DATE)) AS avg_response_delay_seconds,
                AVG(CASE WHEN rr.STATUS = 2 THEN TIMESTAMPDIFF(SECOND, uia.ANSWER_DATE, rr.UPDATE_DATE) END) AS avg_review_delay_seconds,
                COUNT(DISTINCT CASE WHEN ui.STATUS = \'pending\' AND ' . $isReviewableExpr . ' THEN ui.ID END) AS invitations_pending,
                COUNT(DISTINCT CASE WHEN rr.STATUS IN (0, 1) AND ' . $isReviewableExpr . ' THEN rr.ID END) AS reviews_pending,
                COUNT(DISTINCT CASE WHEN rr.STATUS = 2 THEN rr.ID END) AS reviews_completed,
                COUNT(DISTINCT CASE WHEN rr.STATUS IN (0, 1) AND ' . $isReviewableExpr . ' AND ua.DEADLINE < NOW() THEN rr.ID END) AS reviews_overdue,
                MAX(CASE WHEN rr.STATUS IN (0, 1) AND ' . $isReviewableExpr . ' AND ua.DEADLINE < NOW() THEN TIMESTAMPDIFF(DAY, ua.DEADLINE, NOW()) END) AS max_overdue_days,
                COUNT(DISTINCT CASE WHEN ui.FIRST_SENDING_DATE >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN ui.ID END) AS recent_invitations_3m,
                COUNT(DISTINCT CASE WHEN ui.FIRST_SENDING_DATE >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN ui.ID END) AS recent_invitations_6m,
                MAX(ui.FIRST_SENDING_DATE) AS last_invitation_date,
                MAX(CASE WHEN rr.STATUS = 2 THEN rr.UPDATE_DATE END) AS last_review_completed_date,
                GROUP_CONCAT(DISTINCT ua.ITEMID) AS item_ids
            FROM `' . T_ASSIGNMENTS . '` ua
            LEFT JOIN `' . T_USERS . '` u ON ua.TMP_USER = 0 AND ua.UID = u.UID AND u.IS_VALID = 1
            LEFT JOIN `' . T_TMP_USER . '` ut ON ua.TMP_USER = 1 AND ua.UID = ut.ID
            LEFT JOIN ' . self::latestInvitationPerAssignmentSql() . ' ui ON ua.ID = ui.AID
            LEFT JOIN ' . self::latestAnswerPerAssignmentSql() . ' uia ON ua.ID = uia.AID
            LEFT JOIN `' . T_REVIEWER_REPORTS . '` rr ON ua.TMP_USER = 0 AND ua.UID = rr.UID AND ua.ITEMID = rr.DOCID
            LEFT JOIN `' . T_PAPERS . '` p ON ua.ITEMID = p.DOCID
            WHERE ua.RVID = :rvid
              AND ua.ROLEID = :role_reviewer
        ';

        $params = [
            'rvid' => $rvid,
            'role_reviewer' => Episciences_Acl::ROLE_REVIEWER,
        ];

        if ($periodMonths !== null) {
            // Strict cap: USER_ASSIGNMENT.STATUS is not a reliable signal for "still open" —
            // many assignments stay stuck on 'pending' for years (including ones whose invitation was actually accepted and
            // whose review report is already completed), so exempting them from the rolling
            // window used to leak arbitrarily old rows into a page captioned "24 months max".
            // Personal (uncapped) stats skip this entirely.
            $sql .= ' AND COALESCE(ui.FIRST_SENDING_DATE, ua.WHEN) >= DATE_SUB(NOW(), INTERVAL :period_months MONTH)';
            $params['period_months'] = $periodMonths;
        }

        if ($isPersonal) {
            // Strict check on TMP_USER = 0 for personal stats (registered user only).
            $sql .= ' AND ua.UID = :current_user_uid AND ua.TMP_USER = 0';
            $params['current_user_uid'] = $currentUserId;
        } elseif ($isRestricted) {
            $sql .= ' AND ' . self::restrictionClauseSql();
            $params['current_user_uid'] = $currentUserId;
        }

        if (!empty($search)) {
            $sql .= ' AND (u.LASTNAME LIKE :search OR u.FIRSTNAME LIKE :search OR ut.LASTNAME LIKE :search OR ut.FIRSTNAME LIKE :search OR u.EMAIL LIKE :search OR ut.EMAIL LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        return [$sql, $params];
    }

    /**
     * Same "does this editor manage this paper" test used by the restricted global view,
     * shared with getReviewerInvitationDetails() so a drill-down can never show more than
     * the aggregate row it was reached from. Binds :current_user_uid and :rvid.
     */
    private static function restrictionClauseSql(): string
    {
        // Roles are fixed class constants (not user input), safe to inline.
        $managerRoles = "'" . implode("','", [
            Episciences_Acl::ROLE_EDITOR,
            Episciences_Acl::ROLE_GUEST_EDITOR,
            Episciences_Acl::ROLE_CHIEF_EDITOR,
        ]) . "'";

        return '(ui.SENDER_UID = :current_user_uid OR ua.FROM_UID = :current_user_uid OR ua.ITEMID IN (
            SELECT ITEMID FROM `' . T_ASSIGNMENTS . '` WHERE UID = :current_user_uid AND TMP_USER = 0 AND ITEM = \'paper\' AND RVID = :rvid AND ROLEID IN (' . $managerRoles . ')
        ))';
    }

    /**
     * Lists individual invitations/assignments behind one aggregate row of
     * getReviewersGlobalStats() — the drill-down "which papers made up this number".
     * Matches the same identity resolution (email primarily, UID+TMP_USER as a last-resort
     * fallback for reviewers with no email on record) and applies the same
     * restriction/period rules, so the detail can never show more than the aggregate implied.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReviewerInvitationDetails(
        int $rvid,
        ?string $email,
        int $uid,
        int $tmpUser,
        bool $isRestricted,
        int $currentUserId,
        ?int $periodMonths = 24
    ): array {
        $periodMonths = $periodMonths !== null ? min(24, max(1, $periodMonths)) : null;

        $sql = '
            SELECT
                ua.ITEMID AS docid,
                ua.UID AS uid,
                ua.TMP_USER AS tmp_user,
                ua.STATUS AS assignment_status,
                ua.WHEN AS assignment_date,
                ua.DEADLINE AS deadline,
                ui.STATUS AS invitation_status,
                ui.FIRST_SENDING_DATE AS sending_date,
                uia.ANSWER AS answer,
                uia.ANSWER_DATE AS answer_date,
                rr.STATUS AS review_status,
                rr.UPDATE_DATE AS review_update_date
            FROM `' . T_ASSIGNMENTS . '` ua
            LEFT JOIN `' . T_USERS . '` u ON ua.TMP_USER = 0 AND ua.UID = u.UID
            LEFT JOIN `' . T_TMP_USER . '` ut ON ua.TMP_USER = 1 AND ua.UID = ut.ID
            LEFT JOIN ' . self::latestInvitationPerAssignmentSql() . ' ui ON ua.ID = ui.AID
            LEFT JOIN ' . self::latestAnswerPerAssignmentSql() . ' uia ON ua.ID = uia.AID
            LEFT JOIN `' . T_REVIEWER_REPORTS . '` rr ON ua.TMP_USER = 0 AND ua.UID = rr.UID AND ua.ITEMID = rr.DOCID
            WHERE ua.RVID = :rvid
              AND ua.ROLEID = :role_reviewer
        ';

        $params = [
            'rvid' => $rvid,
            'role_reviewer' => Episciences_Acl::ROLE_REVIEWER,
        ];

        if (!empty($email)) {
            $sql .= ' AND LOWER(COALESCE(u.EMAIL, ut.EMAIL)) = :email';
            $params['email'] = strtolower($email);
        } else {
            $sql .= ' AND ua.UID = :uid AND ua.TMP_USER = :tmp_user';
            $params['uid'] = $uid;
            $params['tmp_user'] = $tmpUser;
        }

        if ($periodMonths !== null) {
            $sql .= ' AND COALESCE(ui.FIRST_SENDING_DATE, ua.WHEN) >= DATE_SUB(NOW(), INTERVAL :period_months MONTH)';
            $params['period_months'] = $periodMonths;
        }

        if ($isRestricted) {
            $sql .= ' AND ' . self::restrictionClauseSql();
            $params['current_user_uid'] = $currentUserId;
        }

        $sql .= ' ORDER BY COALESCE(ui.FIRST_SENDING_DATE, ua.WHEN) DESC';

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->db->fetchAll($sql, $params);

        return $rows;
    }

    /**
     * Counts reminder emails actually sent (Episciences_Paper_Logger::CODE_REMINDER_SENT
     * entries in the paper activity log) for a set of item IDs, matched against a set of
     * reviewer emails. Replaces free-text matching over mail subjects/recipients, which
     * is both unreliable (localized subjects, substring false positives) and duplicates
     * a mechanism that already exists: scripts/reminders.php logs every reminder it sends.
     *
     * @param int $rvid Current journal ID (RVID)
     * @param array<int, int> $itemIds Paper/document IDs (DOCID) the reviewers are assigned to
     * @param int|null $periodMonths Rolling history limit in months, or null for no limit
     * @param array<int, string> $emails List of reviewer emails to count reminders for
     * @return array<string, int> Map of lowercased email => total reminders count
     */
    public function getReminderCounts(int $rvid, array $itemIds, ?int $periodMonths, array $emails): array
    {
        $targetEmails = array_unique(array_map(static fn(string $e): string => strtolower(trim($e)), $emails));
        $results = array_fill_keys($targetEmails, 0);

        $itemIds = array_unique(array_map('intval', $itemIds));
        if (empty($itemIds) || empty($targetEmails)) {
            return $results;
        }

        $sql = '
            SELECT DOCID, DETAIL
            FROM `' . T_LOGS . '`
            WHERE RVID = :rvid
              AND ACTION = :action
              AND DOCID IN (' . implode(',', $itemIds) . ')
        ';

        $params = [
            'rvid' => $rvid,
            'action' => self::ACTION_REMINDER_SENT,
        ];

        if ($periodMonths !== null) {
            $sql .= ' AND DATE >= DATE_SUB(NOW(), INTERVAL :period_months MONTH)';
            $params['period_months'] = $periodMonths;
        }

        $targetEmailsSet = array_fill_keys($targetEmails, true);

        foreach ($this->db->fetchAll($sql, $params) as $row) {
            $detail = json_decode((string)($row['DETAIL'] ?? ''), true);
            if (!is_array($detail)) {
                continue;
            }

            $recipients = $detail['mail']['To'] ?? [];
            if (!is_array($recipients)) {
                $recipients = [$recipients];
            }

            foreach ($recipients as $recipient) {
                $email = self::extractEmail((string)$recipient);
                if ($email !== null && isset($targetEmailsSet[$email])) {
                    $results[$email]++;
                }
            }
        }

        return $results;
    }

    /**
     * Extracts a lowercased email address from a "Name <email>" or bare "email" string.
     * Uses an exact match on the extracted address rather than substring matching, to
     * avoid false positives (e.g. "jo@x.com" must not match "majo@x.com").
     */
    private static function extractEmail(string $raw): ?string
    {
        if (preg_match('/<([^>]+)>/', $raw, $matches) === 1) {
            $email = strtolower(trim($matches[1]));
        } else {
            $email = strtolower(trim($raw));
        }

        return $email !== '' ? $email : null;
    }
}
