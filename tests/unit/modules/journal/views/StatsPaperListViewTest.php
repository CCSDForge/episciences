<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use StatsController;
use Zend_View;

/**
 * Button of the papers of a figure (stats/paper-list.phtml): the window takes the title of the figure, or the title
 * given with the list (the tile of the articles). Charts are exported under their title (stats/chart-panel.phtml).
 */
final class StatsPaperListViewTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    private function render(array $paperList): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->paperList = $paperList;

        return $view->render('stats/paper-list.phtml');
    }

    public function testTheWindowTakesTheTitleOfTheFigure(): void
    {
        $html = $this->render(['figure' => 'nbAccepted', 'count' => 3, 'hidden' => 1, 'incomplete' => false]);

        self::assertStringContainsString('data-figure="nbAccepted"', $html);
        self::assertStringNotContainsString('data-title', $html);
        self::assertStringContainsString('conflit d', $html);
    }

    public function testAListShownUnderAnotherTitleGivesItsTitle(): void
    {
        $html = $this->render(['figure' => 'nbSubmissions', 'count' => 2, 'hidden' => 0, 'incomplete' => false, 'title' => 'Articles']);

        self::assertStringContainsString('data-figure="nbSubmissions" data-title="Articles"', $html);
    }

    public function testTheListOfTheArticlesTileIsTitledAfterTheTile(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->totalsExplained = true;
        $view->totalArticles = 2;
        $view->totalImportedArticles = 0;
        $view->paperLists = [
            StatsController::NB_SUBMISSIONS => ['figure' => 'nbSubmissions', 'count' => 2, 'hidden' => 0, 'incomplete' => false],
        ];

        $html = $view->render('stats/total-submissions.phtml');

        self::assertStringContainsString('data-figure="nbSubmissions" data-title="Articles"', $html);
    }

    public function testTheChartIsExportedUnderItsTitleWithoutMarkup(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->canvasId = 'submissions-by-year-chart';
        $view->title = 'Soumissions <small>par année</small>';

        $html = $view->render('stats/chart-panel.phtml');

        self::assertStringContainsString('data-export-name="Soumissions par année"', $html);
    }
}
