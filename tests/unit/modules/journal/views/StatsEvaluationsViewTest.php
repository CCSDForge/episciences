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
    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    private function render(?float $requested, ?float $received, ?float $median, array $paperLists = []): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->reviewsRequested = $requested;
        $view->reviewsReceived = $received;
        $view->medianReviewsNumber = $median;
        $view->paperLists = $paperLists;

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

    /**
     * Journal administrators: the invitations and the reports counted can be viewed; the median has no list.
     */
    public function testTheInvitationsAndTheReportsCanBeViewed(): void
    {
        $html = $this->render(12, 9, 2, [
            \StatsController::REVIEWS_REQUESTED => ['figure' => \StatsController::REVIEWS_REQUESTED, 'count' => 11, 'hidden' => 1, 'incomplete' => false, 'reviews' => true],
            \StatsController::REVIEWS_RECEIVED => ['figure' => \StatsController::REVIEWS_RECEIVED, 'count' => 9, 'hidden' => 0, 'incomplete' => false, 'reviews' => true],
        ]);

        self::assertStringContainsString('data-figure="reviewsRequested"', $html);
        self::assertStringContainsString('Voir les invitations (11)', $html);
        self::assertStringContainsString('1 évaluation non listée', $html);
        self::assertStringContainsString('data-figure="reviewsReceived"', $html);
        self::assertStringContainsString('Voir les rapports (9)', $html);
        self::assertSame(2, substr_count($html, 'stat-papers-open'));
    }

    public function testNoListForTheOtherUsers(): void
    {
        self::assertStringNotContainsString('stat-papers-open', $this->render(12, 9, 2));
    }

    public function testNoBlockWithoutFigure(): void
    {
        self::assertStringNotContainsString('id="evaluation"', $this->render(null, null, null));
    }
}
