<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\StatsQuery;
use Episciences_Acl;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Reviewer\StatsQuery
 */
final class StatsQueryTest extends TestCase
{
    // =========================================================================
    // getReviewersGlobalStats() — GDPR rolling-history cap
    // =========================================================================

    public function testEditorsViewClampsPeriodToTwentyFourMonths(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 999, null, false, false, false, false);

        [$countCall, $dataCall] = $adapter->calls;
        self::assertSame(24, $countCall['bind']['period_months']);
        self::assertSame(24, $dataCall['bind']['period_months']);
    }

    public function testPersonalViewHasNoRollingHistoryCap(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        // periodMonths passed as 6 but isPersonal = true must still yield no cap at all.
        $query->getReviewersGlobalStats(1, false, 42, 6, null, false, false, false, true);

        foreach ($adapter->calls as $call) {
            self::assertArrayNotHasKey('period_months', $call['bind']);
            self::assertStringNotContainsString('period_months', $call['sql']);
        }
    }

    public function testCountQueryReusesTheExactSameFilteredCoreQuery(): void
    {
        $adapter = new StatsQueryTestAdapter([3, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12, null, false, false, true, false);

        [$countCall, $dataCall] = $adapter->calls;
        self::assertStringContainsString('reviews_overdue > 0', $countCall['sql']);
        self::assertStringStartsWith('SELECT COUNT(*) FROM (', $countCall['sql']);
        self::assertStringContainsString('reviews_overdue > 0', $dataCall['sql']);
    }

    public function testRestrictedViewIncludesAllThreeManagerRoles(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, true, 42, 12);

        $sql = $adapter->calls[0]['sql'];
        self::assertStringContainsString("'" . Episciences_Acl::ROLE_EDITOR . "'", $sql);
        self::assertStringContainsString("'" . Episciences_Acl::ROLE_GUEST_EDITOR . "'", $sql);
        self::assertStringContainsString("'" . Episciences_Acl::ROLE_CHIEF_EDITOR . "'", $sql);
    }

    public function testPendingInvitationsExcludeObsoletePapers(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12);

        // Regression guard: USER_INVITATION.STATUS stays 'pending'
        // forever if the reviewer never answers, even once the underlying paper moves past
        // active review (published, refused, a new version requested, ...) — exactly what
        // Episciences_Reviewer_Reviewing::loadStatus() calls "obsolete" via Paper::canBeReviewed().
        // Without gating on the paper's own status, "pending invitations" counted those dead
        // invitations too, inflating the count (e.g. 11 shown vs. 0 actually actionable).
        $sql = $adapter->calls[0]['sql'];
        self::assertStringContainsString('LEFT JOIN `' . T_PAPERS . '` p ON ua.ITEMID = p.DOCID', $sql);
        self::assertMatchesRegularExpression(
            "/ui\\.STATUS = 'pending' AND p\\.STATUS IS NOT NULL AND p\\.STATUS NOT IN \\([\\d,]+\\)/",
            $sql
        );
        // Sanity-check a couple of the statuses that must be excluded: published and accepted
        // papers no longer need reviewing, so a never-answered invitation for one of them is
        // not a genuinely pending action.
        self::assertMatchesRegularExpression(
            '/p\.STATUS NOT IN \([\d,]*\b' . Episciences_Paper::STATUS_PUBLISHED . '\b[\d,]*\)/',
            $sql
        );
        self::assertMatchesRegularExpression(
            '/p\.STATUS NOT IN \([\d,]*\b' . Episciences_Paper::STATUS_ACCEPTED . '\b[\d,]*\)/',
            $sql
        );
    }

    public function testDeletedAccountRowsAreSortedAfterNamedReviewersByDefault(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12);

        // MySQL sorts NULL first on ASC: without an explicit "has an identity" sort key,
        // reviewers whose account was deleted (no matching USER/USER_TMP row, so SCREEN_NAME
        // is NULL) would flood page 1 ahead of every real, named reviewer.
        self::assertStringContainsString('ORDER BY no_identity ASC, SCREEN_NAME ASC LIMIT', $adapter->calls[1]['sql']);
    }

    public function testSortingByAnotherColumnKeepsNameAsAStableTiebreaker(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12, null, false, false, false, false, 50, 0, 'reviews_overdue', 'desc');

        self::assertStringContainsString('ORDER BY no_identity ASC, reviews_overdue DESC, SCREEN_NAME ASC LIMIT', $adapter->calls[1]['sql']);
    }

    public function testUnknownSortKeyFallsBackToName(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12, null, false, false, false, false, 50, 0, 'not_a_real_column; DROP TABLE x', 'not_a_real_direction');

        self::assertStringContainsString('ORDER BY no_identity ASC, SCREEN_NAME ASC LIMIT', $adapter->calls[1]['sql']);
        self::assertStringNotContainsString('DROP TABLE', $adapter->calls[1]['sql']);
    }

    public function testReviewersAreGroupedByEmailNotByUidOrTmpUserId(): void
    {
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12);

        // Regression guard: the same reviewer can hold several
        // USER_TMP rows (one created per invitation before they had an account) and/or both
        // a USER_TMP row and a later USER row (once they registered) — grouping by
        // (UID, TMP_USER) alone showed them as several separate "reviewers". Grouping by
        // email whenever one is known merges all of these back into a single row.
        $sql = $adapter->calls[1]['sql'];
        self::assertStringContainsString('CONCAT(\'e:\', LOWER(COALESCE(u.EMAIL, ut.EMAIL)))', $sql);
        self::assertStringContainsString('GROUP BY identity_key', $sql);
    }

    // =========================================================================
    // getReviewerInvitationDetails() — drill-down behind one aggregate row
    // =========================================================================

    public function testDetailMatchesByEmailWhenGiven(): void
    {
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReviewerInvitationDetails(1, 'Jane@X.com', 0, 0, false, 42, 24);

        $call = $adapter->calls[0];
        self::assertStringContainsString('LOWER(COALESCE(u.EMAIL, ut.EMAIL)) = :email', $call['sql']);
        self::assertStringNotContainsString('ua.UID = :uid', $call['sql']);
        // Case-insensitive match: the email is lowercased before binding.
        self::assertSame('jane@x.com', $call['bind']['email']);
    }

    public function testDetailFallsBackToUidAndTmpUserWhenNoEmail(): void
    {
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReviewerInvitationDetails(1, null, 304, 1, false, 42, 24);

        $call = $adapter->calls[0];
        self::assertStringContainsString('ua.UID = :uid AND ua.TMP_USER = :tmp_user', $call['sql']);
        self::assertSame(304, $call['bind']['uid']);
        self::assertSame(1, $call['bind']['tmp_user']);
    }

    public function testDetailAppliesTheSameRestrictionClauseAsTheList(): void
    {
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReviewerInvitationDetails(1, 'jane@x.com', 0, 0, true, 42, 24);

        // Same helper the restricted global view uses: a drill-down must never reveal more
        // than what the aggregate row it was reached from was already allowed to show.
        $sql = $adapter->calls[0]['sql'];
        self::assertStringContainsString("'" . Episciences_Acl::ROLE_GUEST_EDITOR . "'", $sql);
        self::assertSame(42, $adapter->calls[0]['bind']['current_user_uid']);
    }

    public function testDetailAppliesTheGdprPeriodCap(): void
    {
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReviewerInvitationDetails(1, 'jane@x.com', 0, 0, false, 42, 999);

        self::assertSame(24, $adapter->calls[0]['bind']['period_months']);
    }

    public function testDetailOnlyJoinsTheLatestInvitationPerAssignment(): void
    {
        // Regression guard: Episciences_User_Invitation::save()
        // always INSERTs a new row instead of updating one in place, so answering an
        // invitation leaves the original row behind, still 'pending': most assignments have
        // more than one USER_INVITATION row sharing the same AID. A plain
        // `LEFT JOIN USER_INVITATION ON ua.ID = ui.AID` fans a single assignment out into one
        // row per state (one assignment showed up twice, both labelled "obsolete review
        // invitation", instead of once). Only the invitation
        // with the latest SENDING_DATE per AID must be joined — mirrors
        // Episciences_PapersManager::getLatestInvitationByDocIdQuery().
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReviewerInvitationDetails(1, 'jane@x.com', 0, 0, false, 42, 24);

        $sql = $adapter->calls[0]['sql'];
        self::assertStringNotContainsString('LEFT JOIN `' . T_USER_INVITATIONS . '` ui ON ua.ID = ui.AID', $sql);
        self::assertMatchesRegularExpression(
            '/ROW_NUMBER\(\) OVER \(PARTITION BY AID ORDER BY SENDING_DATE DESC, ID DESC\) AS rn/',
            $sql
        );
        self::assertStringContainsString('WHERE ui1.rn = 1', $sql);
    }

    public function testListAggregateAlsoOnlyJoinsTheLatestInvitationPerAssignment(): void
    {
        // Same fan-out bug applies to the aggregate list query: an unresolved, superseded
        // 'pending' invitation on an assignment that was later re-invited and accepted must
        // not inflate invitations_pending.
        $adapter = new StatsQueryTestAdapter([0, []]);
        $query = new StatsQuery($adapter);

        $query->getReviewersGlobalStats(1, false, 42, 12);

        $sql = $adapter->calls[0]['sql'];
        self::assertStringNotContainsString('LEFT JOIN `' . T_USER_INVITATIONS . '` ui ON ua.ID = ui.AID', $sql);
        self::assertMatchesRegularExpression(
            '/ROW_NUMBER\(\) OVER \(PARTITION BY AID ORDER BY SENDING_DATE DESC, ID DESC\) AS rn/',
            $sql
        );
        self::assertStringContainsString('WHERE ui1.rn = 1', $sql);
    }

    /**
     * Regression guard: the answer is stored against the invitation row that was answered
     * (the original 'pending' one), not the later accepted/declined row kept as "latest".
     * Joining answers on the latest invitation's ID lost almost all of them (response/
     * acceptance rates at 0 %, average review time empty, sorting on them a no-op).
     *
     * @return array<string, array{0: string}>
     */
    public static function bothQueriesProvider(): array
    {
        return ['list' => ['list'], 'detail' => ['detail']];
    }

    /**
     * @dataProvider bothQueriesProvider
     */
    public function testAnswersAreJoinedPerAssignmentNotPerLatestInvitationRow(string $which): void
    {
        $sql = $this->capturedSql($which);

        self::assertStringNotContainsString('uia ON ui.ID = uia.ID', $sql);
        self::assertStringContainsString(' uia ON ua.ID = uia.AID', $sql);
        self::assertStringContainsString('JOIN `' . T_USER_INVITATIONS . '` ui3 ON ui3.ID = uia2.ID', $sql);
        self::assertStringContainsString('WHERE uia1.rn = 1', $sql);
    }

    /**
     * @dataProvider bothQueriesProvider
     */
    public function testSendingDateIsTheFirstInvitationRowNotTheLatestStateChange(string $which): void
    {
        // Accepting/declining inserts a new USER_INVITATION row dated at the transition:
        // the latest row's SENDING_DATE is the answer time, not when the invitation was sent.
        $sql = $this->capturedSql($which);

        self::assertStringContainsString('MIN(SENDING_DATE) OVER (PARTITION BY AID) AS FIRST_SENDING_DATE', $sql);
        self::assertStringNotContainsString('ui.SENDING_DATE', $sql);
        self::assertStringContainsString('COALESCE(ui.FIRST_SENDING_DATE, ua.WHEN) >= DATE_SUB', $sql);
    }

    private function capturedSql(string $which): string
    {
        if ($which === 'list') {
            $adapter = new StatsQueryTestAdapter([0, []]);
            (new StatsQuery($adapter))->getReviewersGlobalStats(1, false, 42, 12);
            return $adapter->calls[1]['sql'];
        }

        $adapter = new StatsQueryTestAdapter([[]]);
        (new StatsQuery($adapter))->getReviewerInvitationDetails(1, 'jane@x.com', 0, 0, false, 42, 24);
        return $adapter->calls[0]['sql'];
    }

    // =========================================================================
    // getReminderCounts() — extraction from PAPER_LOG.DETAIL, not MAIL_LOG substrings
    // =========================================================================

    public function testReturnsZeroForAllEmailsWhenNoItemIds(): void
    {
        $adapter = new StatsQueryTestAdapter([]);
        $query = new StatsQuery($adapter);

        $result = $query->getReminderCounts(1, [], null, ['a@x.com']);

        self::assertSame(['a@x.com' => 0], $result);
        self::assertCount(0, $adapter->calls);
    }

    public function testCountsRemindersFromDetailMailToArray(): void
    {
        $adapter = new StatsQueryTestAdapter([[
            ['DOCID' => 10, 'DETAIL' => json_encode(['mail' => ['To' => ['Jane Doe <jane@x.com>']]])],
            ['DOCID' => 10, 'DETAIL' => json_encode(['mail' => ['To' => ['jane@x.com']]])],
        ]]);
        $query = new StatsQuery($adapter);

        $result = $query->getReminderCounts(1, [10], null, ['jane@x.com']);

        self::assertSame(2, $result['jane@x.com']);
    }

    public function testDoesNotMatchOnSubstring(): void
    {
        $adapter = new StatsQueryTestAdapter([[
            ['DOCID' => 10, 'DETAIL' => json_encode(['mail' => ['To' => ['majo@x.com']]])],
        ]]);
        $query = new StatsQuery($adapter);

        $result = $query->getReminderCounts(1, [10], null, ['jo@x.com']);

        self::assertSame(0, $result['jo@x.com']);
    }

    public function testIgnoresMalformedDetailJson(): void
    {
        $adapter = new StatsQueryTestAdapter([[
            ['DOCID' => 10, 'DETAIL' => 'not-json'],
        ]]);
        $query = new StatsQuery($adapter);

        $result = $query->getReminderCounts(1, [10], null, ['jane@x.com']);

        self::assertSame(0, $result['jane@x.com']);
    }

    public function testAppliesPeriodFilterOnlyWhenGiven(): void
    {
        $adapter = new StatsQueryTestAdapter([[]]);
        $query = new StatsQuery($adapter);

        $query->getReminderCounts(1, [10], 24, ['jane@x.com']);

        self::assertStringContainsString('DATE >= DATE_SUB', $adapter->calls[0]['sql']);
        self::assertSame(24, $adapter->calls[0]['bind']['period_months']);
    }
}

/**
 * Minimal stub adapter that bypasses the real Zend_Db driver and captures
 * every fetchOne()/fetchAll() call so tests can assert on the built SQL/binds.
 */
final class StatsQueryTestAdapter extends \Zend_Db_Adapter_Abstract
{
    /** @var list<array{sql: string, bind: array<string, int|string>}> */
    public array $calls = [];

    /** @var list<mixed> */
    private array $results;

    private int $callIndex = 0;

    /**
     * @param list<mixed> $results
     */
    public function __construct(array $results = [])
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
        $this->results = $results;
    }

    public function fetchOne($sql, $bind = []): string
    {
        $this->calls[] = ['sql' => $sql, 'bind' => $bind];
        return (string)($this->results[$this->callIndex++] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll($sql, $bind = [], $fetchMode = null): array
    {
        $this->calls[] = ['sql' => $sql, 'bind' => $bind];
        return $this->results[$this->callIndex++] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function listTables(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function describeTable($tableName, $schemaName = null): array
    {
        return [];
    }

    protected function _connect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function closeConnection(): void
    {
    }

    public function prepare($sql)
    {
        throw new \LogicException('Not used by StatsQuery: it calls fetchOne()/fetchAll() directly.');
    }

    public function lastInsertId($tableName = null, $primaryKey = null): string
    {
        throw new \LogicException('Not used by StatsQuery: it calls fetchOne()/fetchAll() directly.');
    }

    protected function _beginTransaction(): void
    {
    }

    protected function _commit(): void
    {
    }

    protected function _rollBack(): void
    {
    }

    public function setFetchMode($mode): void
    {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0): string
    {
        return $sql;
    }

    public function supportsParameters($type): bool
    {
        return false;
    }

    public function getServerVersion(): string
    {
        return 'test';
    }
}
