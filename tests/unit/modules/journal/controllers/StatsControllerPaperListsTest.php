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

        $rightsCheck = strpos($source, 'Episciences_Auth::isAdministrator(RVID)');
        $exchange = strpos($source, 'new DelegatedTokenClient');

        $this->assertNotFalse($rightsCheck);
        $this->assertNotFalse($exchange);
        $this->assertLessThan($exchange, $rightsCheck, 'The rights must be checked before the token exchange');
    }

    public function testIndexAction_FallsBackToTheStatisticsWithoutPapers(): void
    {
        $source = $this->extractMethod('indexAction');

        // with a token: dashboard with the papers
        $this->assertStringContainsString('self::WITH_PAPERS_PARAM => \'true\']), $token)', $source);
        // otherwise, or if this call fails: the dashboard as before, without token
        $this->assertStringContainsString('$this->askApi($uri, $params)', $source);
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
