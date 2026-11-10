<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\RecipientScope;
use Episciences\Mail\RecipientSearchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Pdo_Mysql;

/**
 * The queries are built by Zend_Db_Select on an adapter without connection: the tests read the SQL
 * actually sent to fetchAll() and the rows the service makes of the database answer.
 */
final class RecipientSearchServiceTest extends TestCase
{
    private const RVID = 3;

    /** @var list<string> */
    private array $queries = [];

    /**
     * @param list<list<array<string, mixed>>> $fetchAllResults answers of the successive fetchAll() calls
     * @param list<list<mixed>> $fetchColResults answers of the successive fetchCol() calls
     */
    private function service(array $fetchAllResults = [], array $fetchColResults = []): RecipientSearchService
    {
        return new RecipientSearchService($this->adapter($fetchAllResults, $fetchColResults));
    }

    /**
     * @param list<list<array<string, mixed>>> $fetchAllResults
     * @param list<list<mixed>> $fetchColResults
     */
    private function adapter(array $fetchAllResults = [], array $fetchColResults = []): Zend_Db_Adapter_Pdo_Mysql&MockObject
    {
        $db = $this->getMockBuilder(Zend_Db_Adapter_Pdo_Mysql::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_connect', '_quote', 'fetchAll', 'fetchCol'])
            ->getMock();

        $db->method('_quote')->willReturnCallback(
            static fn($value): string => is_int($value) || is_float($value) ? (string)$value : "'" . addcslashes((string)$value, "\\'") . "'"
        );
        $db->method('fetchAll')->willReturnCallback(function ($select) use (&$fetchAllResults): array {
            $this->queries[] = (string)$select;
            return array_shift($fetchAllResults) ?? [];
        });
        $db->method('fetchCol')->willReturnCallback(function ($select) use (&$fetchColResults): array {
            $this->queries[] = (string)$select;
            return array_shift($fetchColResults) ?? [];
        });

        return $db;
    }

    private static function allUsers(?int $contextPaperId = null): RecipientScope
    {
        return new RecipientScope(RecipientScope::ALL_REVIEW_USERS, [], $contextPaperId, true);
    }

    private function lastQuery(): string
    {
        self::assertNotEmpty($this->queries, 'a query is sent');

        return (string)end($this->queries);
    }

    public function testEscapeLikeNeutralizesTheWildcards(): void
    {
        self::assertSame('!%!%', RecipientSearchService::escapeLike('%%'));
        self::assertSame('!_!_', RecipientSearchService::escapeLike('__'));
        self::assertSame('a!!b\\c', RecipientSearchService::escapeLike('a!b\\c'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tooShortQueries(): array
    {
        return [
            'empty' => [''],
            'one character' => ['a'],
            'one character surrounded by spaces' => ['  a  '],
        ];
    }

    /**
     * @dataProvider tooShortQueries
     */
    public function testShortQueriesDoNotReachTheDatabase(string $query): void
    {
        self::assertSame([], $this->service()->search(self::RVID, self::allUsers(), $query));
        self::assertSame([], $this->queries);
    }

    public function testWildcardsAreSearchedLiterally(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), '%%');

        self::assertStringContainsString("u.LASTNAME LIKE '!%!%%' ESCAPE '!'", $this->lastQuery());
        self::assertStringNotContainsString("LIKE '%%%'", $this->lastQuery());
    }

    public function testNamesAreSearchedByPrefix(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'dup');

        foreach (['u.LASTNAME', 'u.FIRSTNAME', 'u.SCREEN_NAME', 'u.USERNAME'] as $column) {
            self::assertStringContainsString($column . " LIKE 'dup%' ESCAPE '!'", $this->lastQuery());
        }
        self::assertStringNotContainsString("'%dup", $this->lastQuery(), 'no infix search');
    }

    public function testEmailIsNotSearchedWithTwoCharacters(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'du');

        self::assertStringNotContainsString('u.EMAIL', $this->lastQuery());
    }

    public function testEmailIsSearchedByPrefixFromThreeCharacters(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'dup');

        self::assertStringContainsString("u.EMAIL LIKE 'dup%' ESCAPE '!'", $this->lastQuery());
    }

    public function testCompleteEmailIsSearchedExactly(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'ada@example.org');

        self::assertStringContainsString("u.EMAIL = 'ada@example.org'", $this->lastQuery());
        self::assertStringNotContainsString('u.EMAIL LIKE', $this->lastQuery());
    }

    public function testLimitIsBounded(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'dup', null, 5000);

        self::assertStringContainsString('LIMIT ' . RecipientSearchService::MAX_LIMIT, $this->lastQuery());
        self::assertSame(1, RecipientSearchService::boundLimit(-3));
    }

    public function testEveryUserScopeIsLimitedToTheUsersOfTheJournal(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'dup');

        self::assertStringContainsString('u.UID IN (SELECT `USER_ROLES`.`UID` FROM `USER_ROLES` WHERE (RVID = 3))', $this->lastQuery());
        self::assertStringContainsString('u.IS_VALID = 1', $this->lastQuery());
    }

    public function testRestrictedScopeOnlyFindsTheStaffAndTheAuthorsOfThePapers(): void
    {
        $scope = new RecipientScope(RecipientScope::STAFF_AND_PAPERS, [7, 8]);

        $this->service()->search(self::RVID, $scope, 'dup');

        $sql = $this->lastQuery();
        self::assertStringContainsString("ROLEID IN ('chief_editor', 'secretary', 'editor', 'guest_editor', 'copyeditor', 'webmaster')", $sql);
        self::assertStringContainsString('PAPERID IN (7, 8)', $sql);
        self::assertStringContainsString("a.ROLEID = 'coauthor'", $sql);
        self::assertStringNotContainsString("'reviewer'", $sql);
    }

    public function testRoleFilterRestrictsTheSearch(): void
    {
        $this->service()->search(self::RVID, self::allUsers(), 'dup', 'editor');

        self::assertStringContainsString("ROLEID IN ('editor')", $this->lastQuery());
    }

    public function testStaffListingRefusesNonEditorialRoles(): void
    {
        self::assertSame([], $this->service()->staff(self::RVID, self::allUsers(), 'reviewer'));
        self::assertSame([], $this->queries);
    }

    public function testValuesAreReturnedRawWithTheirGroup(): void
    {
        $rows = [[
            'UID' => '12',
            'SCREEN_NAME' => 'screen',
            'FIRSTNAME' => '<img src=x onerror=alert(1)>',
            'LASTNAME' => 'Lovelace',
            'USERNAME' => 'ada"',
            'EMAIL' => 'ada@example.org',
            'ROLES' => 'editor,member',
        ]];

        $results = $this->service([$rows])->search(self::RVID, self::allUsers(), 'lov');

        self::assertCount(1, $results);
        self::assertSame(12, $results[0]['uid']);
        self::assertStringStartsWith('<img ', $results[0]['fullname'], 'not HTML-encoded');
        self::assertStringNotContainsString('&lt;', $results[0]['fullname']);
        self::assertSame('ada"', $results[0]['username']);
        self::assertSame(['editor'], $results[0]['roles'], 'member is not shown');
        self::assertSame('staff', $results[0]['group']);
        self::assertNull($results[0]['paperRole']);
    }

    public function testParticipantsOfThePaperComeFirstWithTheirRole(): void
    {
        $authors = [10];
        $assignments = [
            ['UID' => '11', 'ROLEID' => 'reviewer', 'STATUS' => 'active'],
            ['UID' => '12', 'ROLEID' => 'editor', 'STATUS' => 'inactive'],
            ['UID' => '13', 'ROLEID' => 'coauthor', 'STATUS' => 'pending'],
        ];
        $rows = [
            ['UID' => '10', 'FIRSTNAME' => 'A', 'LASTNAME' => 'Author', 'ROLES' => 'author'],
            ['UID' => '11', 'FIRSTNAME' => 'R', 'LASTNAME' => 'Reviewer', 'ROLES' => 'reviewer'],
            ['UID' => '12', 'FIRSTNAME' => 'E', 'LASTNAME' => 'Editor', 'ROLES' => 'editor'],
        ];

        $results = $this->service([$assignments, $rows], [$authors])->search(self::RVID, self::allUsers(5), 'xyz');

        self::assertStringContainsString('CASE WHEN u.UID IN (10, 11, 13) THEN 0 ELSE 1 END', $this->lastQuery());
        self::assertSame(['paper', 'paper', 'staff'], array_column($results, 'group'));
        self::assertSame(['author', 'reviewer', null], array_column($results, 'paperRole'), 'an inactive editor is not a participant');
    }

    public function testReviewersOfThePaperAreNotListedWhenNotAllowed(): void
    {
        $this->service([[]], [[]])->paperRoles(self::RVID, 5, false);

        self::assertStringNotContainsString("'reviewer'", implode("\n", $this->queries));
    }

    public function testResolveEmailsIgnoresAmbiguousAddresses(): void
    {
        $rows = [
            ['UID' => '1', 'EMAIL' => 'Ada@Example.org'],
            ['UID' => '2', 'EMAIL' => 'shared@example.org'],
            ['UID' => '3', 'EMAIL' => 'shared@example.org'],
        ];

        $resolved = $this->service([$rows])->resolveEmails(self::RVID, ['ADA@example.org ', 'shared@example.org']);

        self::assertSame(['ada@example.org' => 1], $resolved);
        self::assertStringContainsString("u.EMAIL IN ('ada@example.org', 'shared@example.org')", $this->lastQuery());
        self::assertStringContainsString('RVID = 3', $this->lastQuery());
    }

    public function testResolveEmailsWithoutAddressDoesNotQuery(): void
    {
        self::assertSame([], $this->service()->resolveEmails(self::RVID, ['', '  ']));
        self::assertSame([], $this->queries);
    }

    public function testTemporaryReviewerEmailsAreTheCurrentInvitationsWithoutAccount(): void
    {
        $emails = $this->service([], [['Invited@Example.org ', 'invited@example.org', 'other@example.org']])
            ->temporaryReviewerEmails(self::RVID, 5);

        self::assertSame(['invited@example.org', 'other@example.org'], $emails);
        $query = $this->lastQuery();
        self::assertStringContainsString('TMP_USER = 1', $query);
        self::assertStringContainsString("ROLEID IN ('reviewer')", $query);
        self::assertStringContainsString('t.ID = a.UID', $query);
        self::assertStringContainsString("a.STATUS IN ('active', 'pending')", $query);
        self::assertStringContainsString('PAPERID = 5', $query);
    }

    public function testVisibleUserHasTheVisibilityOfTheSearch(): void
    {
        $scope = new RecipientScope(RecipientScope::STAFF_AND_PAPERS, [7]);
        $rows = [['UID' => '42', 'FIRSTNAME' => 'S', 'LASTNAME' => 'Staff', 'EMAIL' => 's@example.org', 'ROLES' => 'editor']];

        $user = $this->service([$rows])->findVisible(self::RVID, $scope, 42);

        self::assertSame(42, $user['uid'] ?? null);
        $sql = $this->lastQuery();
        self::assertStringContainsString('u.UID = 42', $sql);
        self::assertStringContainsString("ROLEID IN ('chief_editor', 'secretary', 'editor', 'guest_editor', 'copyeditor', 'webmaster')", $sql);
        self::assertStringContainsString('PAPERID IN (7)', $sql);
    }

    public function testUserOutsideTheScopeIsNotVisible(): void
    {
        self::assertNull($this->service([[]])->findVisible(self::RVID, self::allUsers(), 42));
        self::assertStringContainsString('u.UID IN (SELECT `USER_ROLES`.`UID` FROM `USER_ROLES` WHERE (RVID = 3))', $this->lastQuery());
    }

    public function testVisibleUserGivesTheRoleInThePaperOfTheMail(): void
    {
        $rows = [['UID' => '11', 'FIRSTNAME' => 'R', 'LASTNAME' => 'Reviewer', 'ROLES' => 'reviewer']];
        $assignments = [['UID' => '11', 'ROLEID' => 'reviewer', 'STATUS' => 'active']];

        $user = $this->service([$assignments, $rows], [[]])->findVisible(self::RVID, self::allUsers(5), 11);

        self::assertSame('reviewer', $user['paperRole'] ?? null);
    }

    public function testInvalidUidIsNeverVisible(): void
    {
        self::assertNull($this->service()->findVisible(self::RVID, self::allUsers(), 0));
        self::assertSame([], $this->queries);
    }

    public function testTemporaryReviewerIsFoundOnlyAmongTheInvitationsOfThePaper(): void
    {
        $rows = [['EMAIL' => 'invited@example.org', 'FIRSTNAME' => 'Ivy', 'LASTNAME' => 'Invited']];

        $reviewer = $this->service([$rows])->findVisibleTmpReviewer(self::RVID, self::allUsers(5), 77);

        self::assertSame(
            ['uid' => null, 'fullname' => 'Ivy Invited', 'username' => '', 'mail' => 'invited@example.org', 'roles' => [], 'group' => 'paper', 'paperRole' => 'reviewer'],
            $reviewer
        );
        $sql = $this->lastQuery();
        self::assertStringContainsString('t.ID = 77', $sql);
        self::assertStringContainsString('PAPERID = 5', $sql);
        self::assertStringContainsString('TMP_USER = 1', $sql);
        self::assertNull($this->service([[]])->findVisibleTmpReviewer(self::RVID, self::allUsers(5), 78));
    }

    public function testTemporaryReviewerNeedsAPaperWhoseReviewersTheCallerMayKnow(): void
    {
        $withoutReviewers = new RecipientScope(RecipientScope::STAFF_AND_PAPERS, [5], 5, false);

        self::assertNull($this->service()->findVisibleTmpReviewer(self::RVID, self::allUsers(), 77));
        self::assertNull($this->service()->findVisibleTmpReviewer(self::RVID, $withoutReviewers, 77));
        self::assertSame([], $this->queries);
    }
}
