<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use StatsController;

/**
 * Papers of the statistics figures (StatsController).
 */
class StatsControllerPaperListsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    // -------------------------------------------------------------------------
    // getPaperLists()
    // -------------------------------------------------------------------------

    public function testGetPaperLists_WithoutPapers_ReturnsEmpty(): void
    {
        $this->assertSame([], StatsController::getPaperLists([]));
        $this->assertSame([], StatsController::getPaperLists(['nbSubmissions' => 3]));
        $this->assertSame([], StatsController::getPaperLists(['papers' => 'invalid']));
    }

    public function testGetPaperLists_ByFigure(): void
    {
        $lists = StatsController::getPaperLists([
            'papers' => [
                'nbSubmissions' => [10, '11', 12],
                'nbAccepted' => [],
            ],
            'hiddenPapers' => ['nbSubmissions' => 2],
        ]);

        $this->assertSame(['docIds' => [10, 11, 12], 'hidden' => 2], $lists['nbSubmissions']);
        $this->assertSame(['docIds' => [], 'hidden' => 0], $lists['nbAccepted']);
    }

    public function testGetPaperLists_IgnoresInvalidValues(): void
    {
        $lists = StatsController::getPaperLists([
            'papers' => ['nbSubmissions' => [10, 0, -1, 'abc'], 'nbRefused' => 'invalid'],
            'hiddenPapers' => ['nbSubmissions' => -3],
        ]);

        $this->assertSame(['docIds' => [10], 'hidden' => 0], $lists['nbSubmissions']);
        $this->assertArrayNotHasKey('nbRefused', $lists);
    }

    public function testGetPaperLists_TotalPublished_MergesPublishedAndImportedPublished(): void
    {
        $lists = StatsController::getPaperLists([
            'papers' => ['nbPublished' => [1, 2], 'nbImportedPublished' => [3, 2]],
            'hiddenPapers' => ['nbPublished' => 1, 'nbImportedPublished' => 2],
        ]);

        $this->assertSame(['docIds' => [1, 2, 3], 'hidden' => 3], $lists[StatsController::TOTAL_PUBLISHED_PAPERS]);
    }

    public function testGetPaperLists_NoImportedPublished_NoTotalPublished(): void
    {
        $lists = StatsController::getPaperLists(['papers' => ['nbPublished' => [1, 2]]]);

        $this->assertArrayNotHasKey(StatsController::TOTAL_PUBLISHED_PAPERS, $lists);
    }

    // -------------------------------------------------------------------------
    // indexAction(): delegated token and fallback
    // -------------------------------------------------------------------------

    public function testDelegatedToken_OnlyForTheAdministratorsOfTheJournal(): void
    {
        $source = $this->extractMethod('getDelegatedToken');

        $rightsCheck = strpos($source, 'self::canSeePapers()');
        $exchange = strpos($source, 'new DelegatedTokenClient');

        $this->assertNotFalse($rightsCheck);
        $this->assertNotFalse($exchange);
        $this->assertLessThan($exchange, $rightsCheck, 'The rights must be checked before the token exchange');
    }

    public function testCanSeePapers_AdministratorsOfTheJournal(): void
    {
        $source = $this->extractMethod('canSeePapers');

        $this->assertStringContainsString('Episciences_Auth::isLogged()', $source);
        $this->assertStringContainsString('Episciences_Auth::isAdministrator(RVID)', $source);
    }

    public function testIndexAction_FallsBackToTheStatisticsWithoutPapers(): void
    {
        $source = $this->extractMethod('indexAction');

        // with a token: dashboard with the papers
        $this->assertStringContainsString('self::WITH_PAPERS_PARAM => \'true\']), $token)', $source);
        // otherwise, or if this call fails: the dashboard as before, without token
        $this->assertStringContainsString('$this->askApi($uri, $params)', $source);
    }

    // -------------------------------------------------------------------------
    // countPaperLists() / getFigureValues()
    // -------------------------------------------------------------------------

    public function testCountPaperLists_OnlyNumbersForThePage(): void
    {
        $counts = StatsController::countPaperLists([
            'nbAccepted' => ['docIds' => [1, 2, 3], 'hidden' => 1],
            'submissionAcceptanceTime' => ['docIds' => [1, 2], 'hidden' => 0],
        ], ['nbAccepted' => 4]);

        $this->assertSame(['figure' => 'nbAccepted', 'count' => 3, 'hidden' => 1, 'incomplete' => false], $counts['nbAccepted']);
        $this->assertSame(['figure' => 'submissionAcceptanceTime', 'count' => 2, 'hidden' => 0, 'incomplete' => false], $counts['submissionAcceptanceTime']);
        $this->assertArrayNotHasKey('docIds', $counts['nbAccepted']);
    }

    public function testCountPaperLists_ListsNotMatchingTheFigure_Incomplete(): void
    {
        $counts = StatsController::countPaperLists(['nbRefused' => ['docIds' => [1, 2], 'hidden' => 0]], ['nbRefused' => 5]);

        $this->assertTrue($counts['nbRefused']['incomplete']);
    }

    public function testGetFigureValues_CountsOnly(): void
    {
        $values = StatsController::getFigureValues([
            'nbSubmissions' => 507,
            'nbPublished' => 38,
            'nbImportedPublished' => 423,
            'submissionAcceptanceTime' => ['value' => 217, 'unit' => 'DAY'],
            'rates' => ['acceptance' => 54.3],
        ]);

        $this->assertEquals(['nbSubmissions' => 507, 'nbPublished' => 38, 'nbImportedPublished' => 423, StatsController::TOTAL_PUBLISHED_PAPERS => 461], $values);
    }

    // -------------------------------------------------------------------------
    // getStatusGroup() / getPapersSessionKey()
    // -------------------------------------------------------------------------

    /**
     * @dataProvider statusGroupProvider
     */
    public function testGetStatusGroup(int $status, string $group): void
    {
        $this->assertSame($group, StatsController::getStatusGroup($status));
    }

    public static function statusGroupProvider(): array
    {
        return [
            'published' => [\Episciences_Paper::STATUS_PUBLISHED, 'published'],
            'refused' => [\Episciences_Paper::STATUS_REFUSED, 'refused'],
            'accepted' => [\Episciences_Paper::STATUS_ACCEPTED, 'accepted'],
            'copy editing' => [\Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES, 'accepted'],
            'abandoned' => [\Episciences_Paper::STATUS_ABANDONED, 'closed'],
            'deleted' => [\Episciences_Paper::STATUS_DELETED, 'closed'],
            'submitted' => [\Episciences_Paper::STATUS_SUBMITTED, 'pending'],
        ];
    }

    public function testGetPapersSessionKey_ByJournalAndYear(): void
    {
        $this->assertSame(RVID . '_all', StatsController::getPapersSessionKey(null));
        $this->assertSame(RVID . '_2025', StatsController::getPapersSessionKey(2025));
        $this->assertNotSame(StatsController::getPapersSessionKey(2024), StatsController::getPapersSessionKey(2025));
    }

    // -------------------------------------------------------------------------
    // papersAction(): rights, session, journal
    // -------------------------------------------------------------------------

    public function testPapersAction_ChecksTheRightsBeforeReadingTheSession(): void
    {
        $source = $this->extractMethod('papersAction');

        $rightsCheck = strpos($source, 'self::canSeePapers()');
        $session = strpos($source, 'new Zend_Session_Namespace');

        $this->assertNotFalse($rightsCheck);
        $this->assertNotFalse($session);
        $this->assertLessThan($session, $rightsCheck);
        $this->assertStringContainsString('$this->sendJson(403', $source);
    }

    public function testPapersAction_OnlyThePapersKeptForTheConnectedUser(): void
    {
        $source = $this->extractMethod('papersAction');

        $this->assertStringContainsString("(\$stored['uid'] ?? null) !== (int)Episciences_Auth::getUid()", $source);
        $this->assertStringContainsString('$this->sendJson(404', $source);
    }

    public function testGetPaperRows_OnlyThePapersOfTheJournal(): void
    {
        $this->assertStringContainsString("->where('RVID = ?', RVID)", $this->extractMethod('getPaperRows'));
    }

    public function testGetPaperRows_NoDocId_NoQuery(): void
    {
        $this->assertSame([], StatsController::getPaperRows([]));
        $this->assertSame([], StatsController::getPaperRows([0, -1, 'abc']));
    }

    // -------------------------------------------------------------------------
    // Date columns: only the dates relevant to each figure
    // -------------------------------------------------------------------------

    public function testFigureColumns(): void
    {
        $submission = StatsController::COLUMN_SUBMISSION;
        $refusal = StatsController::COLUMN_REFUSAL;
        $acceptance = StatsController::COLUMN_ACCEPTANCE;
        $publication = StatsController::COLUMN_PUBLICATION;

        $this->assertSame([
            'nbSubmissions' => [$submission, $refusal, $acceptance, $publication],
            'nbImported' => [$submission, $publication],
            'nbImportedPublished' => [$submission, $publication],
            'nbPublished' => [$submission, $publication],
            StatsController::TOTAL_PUBLISHED_PAPERS => [$submission, $publication],
            'nbAccepted' => [$submission, $acceptance, $publication],
            'nbAcceptedNotYetPublished' => [$submission, $acceptance],
            'nbRefused' => [$submission, $refusal],
            'nbOtherStatus' => [$submission],
            'submissionAcceptanceTime' => [$submission, $acceptance],
            'submissionPublicationTime' => [$submission, $publication],
        ], StatsController::FIGURE_COLUMNS);
    }

    public function testFigureColumns_AcceptanceOnlyForTheAcceptedPapersRefusalOnlyForTheRefusedOnes(): void
    {
        foreach (StatsController::FIGURE_COLUMNS as $figure => $columns) {
            $this->assertSame(
                in_array($figure, ['nbSubmissions', 'nbAccepted', 'nbAcceptedNotYetPublished', 'submissionAcceptanceTime'], true),
                in_array(StatsController::COLUMN_ACCEPTANCE, $columns, true),
                $figure
            );
            $this->assertSame(
                in_array($figure, ['nbSubmissions', 'nbRefused'], true),
                in_array(StatsController::COLUMN_REFUSAL, $columns, true),
                $figure
            );
        }

        $this->assertNotContains(StatsController::COLUMN_PUBLICATION, StatsController::FIGURE_COLUMNS['nbRefused']);
    }

    public function testColumnLogStatuses_AsTheApi(): void
    {
        // the API counts the accepted papers with 4 and 25 (PaperLogRepository::ACCEPTED_STATUSES), the refused ones with 5
        $this->assertSame([4, 25], StatsController::COLUMN_LOG_STATUSES[StatsController::COLUMN_ACCEPTANCE]);
        $this->assertSame([\Episciences_Paper::STATUS_ACCEPTED, \Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED], StatsController::COLUMN_LOG_STATUSES[StatsController::COLUMN_ACCEPTANCE]);
        $this->assertSame([\Episciences_Paper::STATUS_REFUSED], StatsController::COLUMN_LOG_STATUSES[StatsController::COLUMN_REFUSAL]);
    }

    public function testGetStatusDates_NoPaperIdOrStatus_NoQuery(): void
    {
        $this->assertSame([], StatsController::getStatusDates([], [4]));
        $this->assertSame([], StatsController::getStatusDates([0, -2, 'abc'], [4]));
        $this->assertSame([], StatsController::getStatusDates([10], []));
    }

    public function testGetStatusDates_LatestDateInTheJournal(): void
    {
        $source = $this->extractMethod('getStatusDates');

        $this->assertStringContainsString("MAX(DATE)", $source);
        $this->assertStringContainsString("->where('RVID = ?', RVID)", $source);
        $this->assertStringContainsString("->where('status IN (?)', array_map('intval', \$statuses))", $source);
        $this->assertStringContainsString("->group('PAPERID')", $source);
    }

    public function testPaperRows_HistoryReadOnlyForTheColumnsOfTheFigure(): void
    {
        $this->assertStringContainsString('in_array($column, $columns, true)', $this->extractMethod('getPaperRows'));
        $source = $this->extractMethod('papersAction');
        $this->assertStringContainsString('self::FIGURE_COLUMNS[$figure] ?? self::DEFAULT_COLUMNS', $source);
        $this->assertStringContainsString("'columns' => \$columns", $source);
        $this->assertStringContainsString("'submissionLabel' => self::getSubmissionLabel(\$rows)", $source);
    }

    /**
     * @dataProvider submissionLabelProvider
     */
    public function testGetSubmissionLabel(array $imported, string $label): void
    {
        $rows = array_map(static fn(bool $isImported) => ['imported' => $isImported], $imported);

        $this->assertSame($label, StatsController::getSubmissionLabel($rows));
    }

    public static function submissionLabelProvider(): array
    {
        return [
            'submitted papers' => [[false, false], 'submitted'],
            'imported papers' => [[true, true], 'imported'],
            'both' => [[true, false], 'mixed'],
            'no paper' => [[], 'submitted'],
        ];
    }

    public function testFormatPaperRow_OnlyTheDatesOfTheColumns(): void
    {
        $paper = new \Episciences_Paper([
            'DOCID' => 12, 'PAPERID' => 10, 'STATUS' => \Episciences_Paper::STATUS_PUBLISHED, 'FLAG' => 'submitted',
            'SUBMISSION_DATE' => '2025-01-15 10:00:00', 'PUBLICATION_DATE' => '2025-09-01 08:00:00', 'withxsl' => false,
        ]);

        $row = StatsController::formatPaperRow($paper, StatsController::FIGURE_COLUMNS['nbAccepted'], [StatsController::COLUMN_ACCEPTANCE => '2025-06-03 14:30:00']);

        $this->assertSame(12, $row['docid']);
        $this->assertSame(10, $row['paperid']);
        $this->assertSame('published', $row['statusGroup']);
        $this->assertFalse($row['imported']);
        $this->assertSame('2025-01-15', $row['submissionDate']);
        $this->assertSame('2025-06-03', $row['acceptanceDate']);
        $this->assertSame('2025-09-01', $row['publicationDate']);
        $this->assertArrayNotHasKey('refusalDate', $row);
    }

    public function testFormatPaperRow_RefusedPaper_NoPublicationDate(): void
    {
        $paper = new \Episciences_Paper(['DOCID' => 13, 'PAPERID' => 13, 'STATUS' => \Episciences_Paper::STATUS_REFUSED, 'SUBMISSION_DATE' => '2025-01-15 10:00:00', 'withxsl' => false]);

        $row = StatsController::formatPaperRow($paper, StatsController::FIGURE_COLUMNS['nbRefused'], [StatsController::COLUMN_REFUSAL => '2025-03-10 09:00:00']);

        $this->assertSame('2025-03-10', $row['refusalDate']);
        $this->assertArrayNotHasKey('publicationDate', $row);
        $this->assertArrayNotHasKey('acceptanceDate', $row);
    }

    public function testFormatPaperRow_ImportedPaper(): void
    {
        $paper = new \Episciences_Paper(['DOCID' => 14, 'PAPERID' => 14, 'STATUS' => \Episciences_Paper::STATUS_PUBLISHED, 'FLAG' => 'imported', 'SUBMISSION_DATE' => '2023-08-24 18:46:46', 'withxsl' => false]);

        $row = StatsController::formatPaperRow($paper, StatsController::FIGURE_COLUMNS['nbImported']);

        $this->assertTrue($row['imported']);
        $this->assertSame('2023-08-24', $row['submissionDate']);
    }

    public function testPapersAction_DeclaredForTheAdministrators(): void
    {
        $acl = parse_ini_file(APPLICATION_PATH . '/configs/acl.ini', true);

        $this->assertArrayHasKey('allow.stats-papers', $acl['administrator']);
        $this->assertArrayNotHasKey('allow.stats-papers', $acl['guest']);
        $this->assertArrayNotHasKey('allow.stats-papers', $acl['member']);
    }

    // -------------------------------------------------------------------------
    // getApiRequestOptions()
    // -------------------------------------------------------------------------

    public function testGetApiRequestOptions_Timeouts(): void
    {
        $options = StatsController::getApiRequestOptions(['year' => 2025]);

        $this->assertSame(StatsController::API_CONNECT_TIMEOUT, $options['connect_timeout']);
        $this->assertSame(StatsController::API_TIMEOUT, $options['timeout']);
        $this->assertGreaterThan(0, $options['connect_timeout']);
        $this->assertGreaterThan($options['connect_timeout'], $options['timeout']);
        $this->assertSame(['year' => 2025], $options['query']);
        $this->assertArrayNotHasKey('Authorization', $options['headers']);
    }

    public function testGetApiRequestOptions_WithToken(): void
    {
        $options = StatsController::getApiRequestOptions([], 'jwt');

        $this->assertSame('Bearer jwt', $options['headers']['Authorization']);
    }

    public function testAskApi_UsesTheRequestOptions(): void
    {
        $this->assertStringContainsString('self::getApiRequestOptions($options, $token)', $this->extractMethod('askApi'));
    }

    private function extractMethod(string $methodName): string
    {
        $source = (string)file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/StatsController.php');
        $start = strpos($source, 'function ' . $methodName);
        $this->assertNotFalse($start, "Method $methodName not found in StatsController");
        $end = strpos($source, 'function ', $start + strlen('function ' . $methodName));

        return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
    }
}
