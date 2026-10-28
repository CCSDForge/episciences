<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Evaluations block of the statistics page (stats/evaluations.phtml): each figure explains what it counts.
 */
final class StatsEvaluationsViewTest extends TestCase
{
    private function render(?float $requested, ?float $received, ?float $median): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->reviewsRequested = $requested;
        $view->reviewsReceived = $received;
        $view->medianReviewsNumber = $median;

        return $view->render('stats/evaluations.phtml');
    }

    public function testTheMedianIsTheNumberOfReviewsPerReviewedArticle(): void
    {
        $html = $this->render(12, 9, 2);

        self::assertStringContainsString("Nombre médian d'évaluations par article évalué", $html);
        self::assertStringNotContainsString('articles de recherche', $html);
    }

    public function testEachFigureExplainsWhatItCounts(): void
    {
        $html = $this->render(12, 9, 2);

        self::assertSame(3, substr_count($html, 'data-toggle="tooltip"'));
        self::assertStringContainsString('hors invitations annulées', $html);
        self::assertStringContainsString('comptés à leur date de validation', $html);
        self::assertStringContainsString('toutes versions confondues', $html);
    }

    public function testNoBlockWithoutFigure(): void
    {
        self::assertStringNotContainsString('id="evaluation"', $this->render(null, null, null));
    }
}
