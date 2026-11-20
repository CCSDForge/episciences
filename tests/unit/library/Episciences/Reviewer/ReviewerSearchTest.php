<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\ReviewerSearch;
use Episciences\Reviewer\Workload;
use unit\modules\ControllerTestCase;
use unit\modules\support\RecordingDbAdapter;

/**
 * One search for the people an editor may invite (git #900).
 *
 * Extends ControllerTestCase for its database stub; a second stub plays the accounts directory.
 */
final class ReviewerSearchTest extends ControllerTestCase
{
    private RecordingDbAdapter $accounts;

    protected function setUp(): void
    {
        $this->accounts = new RecordingDbAdapter();
    }

    private function search(): ReviewerSearch
    {
        return new ReviewerSearch($this->db, $this->accounts);
    }

    /**
     * @return array<string, mixed>
     */
    private static function account(int $uid, string $firstname, string $lastname, string $email = ''): array
    {
        return ['UID' => $uid, 'FIRSTNAME' => $firstname, 'LASTNAME' => $lastname, 'EMAIL' => $email ?: "u$uid@example.org", 'USERNAME' => "u$uid", 'SCREEN_NAME' => "$firstname $lastname", 'LANGUEID' => 'fr'];
    }

    public function testATermTooShortSearchesNothing(): void
    {
        $groups = $this->search()->search(' x ', RVID);

        self::assertSame([ReviewerSearch::GROUP_JOURNAL => [], ReviewerSearch::GROUP_ACCOUNTS => [], ReviewerSearch::GROUP_WITHOUT_ACCOUNT => []], $groups);
        self::assertSame([], $this->db->queries());
        self::assertSame([], $this->accounts->queries());
    }

    public function testEveryWordIsLookedForInTheNamesInAnyOrder(): void
    {
        $this->db->respondTo('/FROM `USER` AS `u`/', [self::account(1, 'Claire', 'Voisin')]);

        $groups = $this->search()->search('Voisin  CLAIRE', RVID);

        self::assertSame('uid:1', $groups[ReviewerSearch::GROUP_JOURNAL][0]['key']);
        $sql = $this->db->selects()[0];
        self::assertStringContainsString("r.RVID = " . RVID, $sql);
        self::assertStringContainsString("r.ROLEID = 'reviewer'", $sql);
        // among the reviewers of the journal: anywhere in the names
        self::assertStringContainsString("u.FIRSTNAME LIKE '%voisin%' OR u.LASTNAME LIKE '%voisin%' OR u.USERNAME LIKE '%voisin%'", $sql);
        self::assertStringContainsString("u.LASTNAME LIKE '%claire%'", $sql);
        // in the accounts directory: at the start of the names (indexed)
        $directory = $this->accounts->selects()[0];
        self::assertStringContainsString("LASTNAME LIKE 'voisin%'", $directory);
        self::assertStringContainsString('VALID = 1', $directory);
    }

    public function testTheWildcardsOfATermAreSearchedAsSuch(): void
    {
        $this->search()->search('50%_off', RVID);

        self::assertStringContainsString("LIKE '%50\\\\%\\\\_off%'", $this->db->selects()[0]);
    }

    public function testTheAccountsDirectoryNeedsAWordOfThreeCharacters(): void
    {
        $this->search()->search('li wu', RVID);

        self::assertNotSame([], $this->db->selects());
        self::assertSame([], $this->accounts->queries());
    }

    public function testTheReviewersOfTheJournalAreLeftOutOfTheOtherAccounts(): void
    {
        $this->db
            ->respondTo('/FROM `USER` AS `u`/', [self::account(1, 'Claire', 'Voisin')])
            ->respondTo('/SELECT `USER_ROLES`.`UID`/', [['UID' => 1]])
            ->respondTo('/SELECT `USER`.`UID`, `USER`.`LANGUEID`/', [['UID' => 2, 'LANGUEID' => 'en']]);
        // the directory has no language column
        $directory = static fn(array $account): array => array_diff_key($account, ['LANGUEID' => true, 'SCREEN_NAME' => true]);
        $this->accounts->respondTo('/T_UTILISATEURS/', array_map($directory, [self::account(1, 'Claire', 'Voisin'), self::account(2, 'Claire', 'Voisinet'), self::account(3, 'Clara', 'Voisins')]));

        $groups = $this->search()->search('voisin', RVID);

        self::assertSame(['uid:2', 'uid:3'], array_column($groups[ReviewerSearch::GROUP_ACCOUNTS], 'key'));
        // the language of an account that already has one on Episciences
        self::assertSame('en', $groups[ReviewerSearch::GROUP_ACCOUNTS][0]['locale']);
        self::assertSame('', $groups[ReviewerSearch::GROUP_ACCOUNTS][1]['locale']);
    }

    public function testAnAddressIsMatchedExactlyAndFindsThePeopleInvitedWithoutAnAccount(): void
    {
        $this->db->respondTo('/FROM `USER_TMP`/', [['EMAIL' => 'Jane@Example.org', 'FIRSTNAME' => '', 'LASTNAME' => 'Jane Doe', 'LANGUEID' => 'en']]);

        $groups = $this->search()->search(' Jane@Example.org ', RVID);

        self::assertSame('email:jane@example.org', $groups[ReviewerSearch::GROUP_WITHOUT_ACCOUNT][0]['key']);
        self::assertNull($groups[ReviewerSearch::GROUP_WITHOUT_ACCOUNT][0]['uid']);
        // no LOWER(): the index of the address is used, the collation ignores case
        self::assertStringContainsString("u.EMAIL = 'jane@example.org'", $this->db->selects()[0]);
        self::assertStringContainsString("EMAIL = 'jane@example.org'", $this->accounts->selects()[0]);
        $selects = $this->db->selects();
        $withoutAccount = end($selects) ?: '';
        self::assertStringContainsString('a.RVID = ' . RVID, $withoutAccount);
        self::assertStringContainsString("t.EMAIL = 'jane@example.org'", $withoutAccount);
    }

    public function testThePeopleWithoutAnAccountAreOnlyFoundByAddress(): void
    {
        $this->search()->search('jane doe', RVID);

        self::assertSame([], array_filter($this->db->selects(), static fn(string $sql): bool => str_contains($sql, 'USER_TMP')));
    }

    public function testTheReviewersOfTheJournalAreCountedAsTheyAreListed(): void
    {
        $this->db->respondTo('/COUNT\(\*\)/', [['NB' => 1597]]);

        self::assertSame(1597, $this->search()->countJournalReviewers(RVID));
        $sql = $this->db->selects()[0];
        self::assertStringContainsString('r.RVID = ' . RVID, $sql);
        self::assertStringContainsString("r.ROLEID = 'reviewer'", $sql);
    }

    public function testTheReviewersOfTheJournalCanBeBrowsedPageByPage(): void
    {
        $this->db->respondTo('/FROM `USER` AS `u`/', [
            self::account(1, 'Ada', 'A'), self::account(2, 'Bob', 'B'), self::account(3, 'Cy', 'C'),
        ]);

        $page = $this->search()->browse(RVID, [], ReviewerSearch::ORDER_NAME, 2, 2);

        self::assertSame(3, $page['total']);
        self::assertSame(['uid:3'], array_column($page['people'], 'key'));
    }

    public function testTheLeastRequestedReviewersComeFirst(): void
    {
        $this->db->respondTo('/FROM `USER` AS `u`/', [
            self::account(1, 'Ada', 'A'), self::account(2, 'Bob', 'B'), self::account(3, 'Cy', 'C'),
        ]);
        $workloads = [
            1 => new Workload(2, 0, '2026-11-01 10:00:00', Workload::ANSWER_ACCEPTED),
            2 => new Workload(0, 0, '2026-10-01 10:00:00', Workload::ANSWER_DECLINED),
        ];

        $least = $this->search()->browse(RVID, $workloads, ReviewerSearch::ORDER_LEAST_REQUESTED, 1, 10);
        $latest = $this->search()->browse(RVID, $workloads, ReviewerSearch::ORDER_LAST_INVITATION, 1, 10);

        // never invited, then nothing in progress, then busy
        self::assertSame(['uid:3', 'uid:2', 'uid:1'], array_column($least['people'], 'key'));
        // latest invitation first, never invited last
        self::assertSame(['uid:1', 'uid:2', 'uid:3'], array_column($latest['people'], 'key'));
    }
}
