<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use PHPUnit\Framework\Attributes\DataProvider;
use StatsController;
use unit\modules\ControllerTestCase;
use Zend_Session_Namespace;

/**
 * JSON of the papers of a figure (StatsController::papersAction()), for the journal administrators only: the papers of
 * the journal kept in session for the connected user, with the dates of the figure; for an evaluation figure, a row by
 * invitation or by report, counted as reviews.
 */
final class StatsControllerPapersActionTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'StatsController');
    }

    protected function setUp(): void
    {
        $this->givenJournal();
        // the papers of the journal: DOCID 201 and 203
        $this->db->respondTo('/FROM `PAPERS`/', [
            ['DOCID' => 201, 'PAPERID' => 1, 'RVID' => RVID, 'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED, 'FLAG' => 'submitted', 'SUBMISSION_DATE' => '2024-01-10 10:00:00'],
            ['DOCID' => 203, 'PAPERID' => 3, 'RVID' => RVID, 'STATUS' => Episciences_Paper::STATUS_REFUSED, 'FLAG' => 'submitted', 'SUBMISSION_DATE' => '2024-02-10 10:00:00'],
        ]);
    }

    /**
     * The lists kept in session by the statistics page of the year, for $uid
     */
    private function givenReviewListsInSession(int $uid): void
    {
        $lists = StatsController::getReviewLists([
            ['name' => 'reviews-requested', 'value' => 4, 'reviews' => [
                ['docid' => 201, 'date' => '2024-03-01', 'answer' => 'accepted'],
                ['docid' => 201, 'date' => '2024-03-02', 'answer' => 'declined'],
                ['docid' => 203, 'date' => '2024-05-02', 'answer' => 'expired'],
            ], 'hiddenReviews' => 1],
            ['name' => 'reviews-received', 'value' => 1, 'reviews' => [['docid' => 201, 'date' => '2024-03-20']], 'hiddenReviews' => 0],
        ]);

        $session = new Zend_Session_Namespace(StatsController::PAPERS_SESSION_NAMESPACE);
        $session->{StatsController::getPapersSessionKey(2024)} = ['uid' => $uid, 'lists' => $lists];
    }

    /**
     * The papers of the figures kept in session by the statistics page of the year, for $uid
     */
    private function givenPaperListsInSession(int $uid, bool $fullHistory = false): void
    {
        $lists = StatsController::getPaperLists(['papers' => [
            StatsController::NB_ACCEPTED => [201],
            StatsController::NB_PUBLISHED => [203],
        ]]);

        $session = new Zend_Session_Namespace(StatsController::PAPERS_SESSION_NAMESPACE);
        $session->{StatsController::getPapersSessionKey(2024, $fullHistory)} = ['uid' => $uid, 'lists' => $lists];
    }

    /**
     * @return list<string> the SQL of the queries sent to $table
     */
    private function queriesOf(string $table): array
    {
        return array_values(array_filter(
            array_column($this->db->queries(), 'sql'),
            static fn(string $sql): bool => str_contains($sql, 'FROM `' . $table . '`')
        ));
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('rolesSeeingThePapersProvider')]
    public function testTheAdministratorsOfTheJournalSeeThePapers(array $roles): void
    {
        $this->logInAs($roles);
        $this->givenPaperListsInSession(self::UID);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));

        self::assertSame(200, $result->statusCode());
        self::assertSame(1, $result->json()['total']);
    }

    public static function rolesSeeingThePapersProvider(): array
    {
        return [
            'administrator' => [[Episciences_Acl::ROLE_ADMIN]],
            'secretary' => [[Episciences_Acl::ROLE_SECRETARY]],
            'root' => [[Episciences_Acl::ROLE_ROOT]],
        ];
    }

    /**
     * @param list<string>|null $roles null: nobody logged in
     */
    #[DataProvider('rolesNotSeeingThePapersProvider')]
    public function testTheOthersCannotSeeThePapers(?array $roles): void
    {
        if ($roles !== null) {
            $this->logInAs($roles);
        }
        $this->givenPaperListsInSession(self::UID);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));

        self::assertSame(403, $result->statusCode());
        self::assertArrayNotHasKey('rows', $result->json());
        self::assertSame([], $this->db->queries(), 'nothing is read before the rights are checked');
    }

    public static function rolesNotSeeingThePapersProvider(): array
    {
        return [
            'guest' => [null],
            'member' => [[Episciences_Acl::ROLE_MEMBER]],
            'editor' => [[Episciences_Acl::ROLE_EDITOR]],
            'reviewer' => [[Episciences_Acl::ROLE_REVIEWER]],
        ];
    }

    public function testWithoutListsInSession_NotFound(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));

        self::assertSame(404, $result->statusCode());
    }

    public function testThePapersOfAnotherUserAreNotReturned(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenPaperListsInSession(self::UID + 1);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));

        self::assertSame(404, $result->statusCode());
        self::assertSame([], $this->queriesOf(T_PAPERS));
    }

    public function testTheListsOfAllTheDataAreKeptApart(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_ADMIN]);
        $this->givenPaperListsInSession(self::UID, true);

        $filtered = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));
        $all = $this->dispatch(StatsController::class, 'papers', $this->getRequest([
            'figure' => StatsController::NB_PUBLISHED,
            'year' => '2024',
            StatsController::FULL_HISTORY_PARAM => '1',
        ]));

        self::assertSame(404, $filtered->statusCode());
        self::assertSame(200, $all->statusCode());
    }

    public function testOnlyThePapersOfTheJournalAreRead(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenPaperListsInSession(self::UID);

        $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']));

        $queries = $this->queriesOf(T_PAPERS);
        self::assertCount(1, $queries);
        self::assertStringContainsString('(RVID = ' . RVID . ')', $queries[0]);
        self::assertStringContainsString('(DOCID IN (203))', $queries[0]);
    }

    public function testTheColumnsOfTheFigure_TheHistoryIsNotReadWithoutDateFromIt(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenPaperListsInSession(self::UID);

        $json = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_PUBLISHED, 'year' => '2024']))->json();

        self::assertSame(StatsController::FIGURE_COLUMNS[StatsController::NB_PUBLISHED], $json['columns']);
        self::assertSame(StatsController::SUBMISSION_LABEL_SUBMITTED, $json['submissionLabel']);
        self::assertSame([], $this->queriesOf(T_LOGS), 'no date of the figure comes from the history');
    }

    public function testTheAcceptanceDateIsTheLatestOfThePaperInTheJournal(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenPaperListsInSession(self::UID);
        $this->db->respondTo('/FROM `' . T_LOGS . '`/', [['PAPERID' => 1, 'STATUS_DATE' => '2024-06-01 10:00:00']]);

        $json = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::NB_ACCEPTED, 'year' => '2024']))->json();

        self::assertSame(StatsController::FIGURE_COLUMNS[StatsController::NB_ACCEPTED], $json['columns']);
        self::assertSame('2024-06-01', $json['rows'][0][StatsController::COLUMN_ACCEPTANCE]);

        $queries = $this->queriesOf(T_LOGS);
        self::assertCount(1, $queries, 'the history is only read for the acceptance date');
        self::assertStringContainsString('MAX(DATE)', $queries[0]);
        self::assertStringContainsString('(RVID = ' . RVID . ')', $queries[0]);
        self::assertStringContainsString(
            sprintf('(status IN (%d, %d))', Episciences_Paper::STATUS_ACCEPTED, Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED),
            $queries[0]
        );
        self::assertStringContainsString('GROUP BY `PAPERID`', $queries[0]);
    }

    public function testTheInvitationsAreListedAndCountedAsInvitations(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenReviewListsInSession(self::UID);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::REVIEWS_REQUESTED, 'year' => '2024']));
        $json = $result->json();

        self::assertSame(200, $result->statusCode());
        self::assertSame(3, $json['total'], 'the invitations are counted, not the 2 papers');
        self::assertCount(3, $json['rows']);
        self::assertSame(1, $json['hidden']);
        self::assertSame('invitation(s)', $json['countLabel']);
        self::assertSame([StatsController::COLUMN_INVITATION, StatsController::COLUMN_ANSWER], $json['columns']);
        self::assertSame(['2024-03-01', '2024-03-02', '2024-05-02'], array_column($json['rows'], StatsController::COLUMN_INVITATION));
    }

    public function testTheReportsAreCountedAsReports(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenReviewListsInSession(self::UID);

        $json = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::REVIEWS_RECEIVED, 'year' => '2024']))->json();

        self::assertSame(1, $json['total']);
        self::assertSame('rapport(s)', $json['countLabel']);
        self::assertSame([StatsController::COLUMN_REPORT], $json['columns']);
        self::assertSame(['2024-03-20'], array_column($json['rows'], StatsController::COLUMN_REPORT));
    }

    public function testAnEditorCannotSeeTheReviews(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        $this->givenReviewListsInSession(self::UID);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::REVIEWS_REQUESTED, 'year' => '2024']));

        self::assertSame(403, $result->statusCode());
        self::assertArrayNotHasKey('rows', $result->json());
    }

    public function testTheListsOfAnotherUserAreNotReturned(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenReviewListsInSession(self::UID + 1);

        $result = $this->dispatch(StatsController::class, 'papers', $this->getRequest(['figure' => StatsController::REVIEWS_REQUESTED, 'year' => '2024']));

        self::assertSame(404, $result->statusCode());
    }
}
