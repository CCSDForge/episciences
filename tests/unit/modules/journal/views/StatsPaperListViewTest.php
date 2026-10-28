<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Button of the papers of a figure (stats/paper-list.phtml): the window takes the title of the figure, or the title
 * given with the list.
 */
final class StatsPaperListViewTest extends TestCase
{
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
}
