<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Rating_Criterion;
use Episciences_Rating_Grid;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rating column of the criteria table on grid/list.
 */
final class GridCriteriaPartialTest extends TestCase
{
    private const SCRIPT = 'partials/grid_criteria.phtml';

    private function renderRating(Episciences_Rating_Criterion $criterion): string
    {
        $grid = new Episciences_Rating_Grid(['id' => 1]);
        $grid->setCriteria([$criterion]);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->grid = $grid;

        $html = $view->render(self::SCRIPT);
        preg_match_all('#<td>(.*?)</td>#s', $html, $matches);

        // cells without class: criterion, comments, upload, rating, visibility
        return trim($matches[1][3] ?? '');
    }

    public function testStandardQualitativeCriterionIsShownAsStandard(): void
    {
        $criterion = new Episciences_Rating_Criterion([
            'visibility' => 'editors',
            'type' => Episciences_Rating_Criterion::TYPE_CRITERION,
            'options' => Episciences_Rating_Criterion::STANDARD_QUALITATIVE_OPTIONS,
        ]);

        self::assertSame('Evaluation qualitative (classique)', $this->renderRating($criterion));
    }

    public function testCustomQualitativeCriterionIsShownAsCustomized(): void
    {
        $criterion = new Episciences_Rating_Criterion([
            'visibility' => 'editors',
            'type' => Episciences_Rating_Criterion::TYPE_CRITERION,
            'subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM,
            'options' => [
                ['value' => 0, 'label' => ['en' => 'Good']],
                ['value' => 1, 'label' => ['en' => 'Bad']],
            ],
        ]);

        self::assertSame('Evaluation qualitative (personnalisée)', $this->renderRating($criterion));
    }

    public function testCustomQualitativeCriterionWithEmptyLabelsIsShownAsCustomized(): void
    {
        $criterion = new Episciences_Rating_Criterion([
            'visibility' => 'editors',
            'type' => Episciences_Rating_Criterion::TYPE_CRITERION,
            'subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM,
            'options' => [
                ['value' => 0, 'label' => null],
                ['value' => 1, 'label' => null],
            ],
        ]);

        self::assertSame('Evaluation qualitative (personnalisée)', $this->renderRating($criterion));
    }

    public function testCustomQuantitativeCriterionWithEmptyLabelsIsShownAsCustomized(): void
    {
        $criterion = new Episciences_Rating_Criterion([
            'visibility' => 'editors',
            'type' => Episciences_Rating_Criterion::TYPE_CRITERION,
            'subType' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE_CUSTOM,
            'coefficient' => 1,
            'options' => [
                ['value' => 0, 'label' => null],
                ['value' => 1, 'label' => null],
            ],
        ]);

        self::assertSame('Evaluation quantitative (personnalisée)', $this->renderRating($criterion));
    }

    public function testSeparatorIsShownAsSeparator(): void
    {
        $criterion = new Episciences_Rating_Criterion([
            'visibility' => 'editors',
            'type' => Episciences_Rating_Criterion::TYPE_SEPARATOR,
        ]);

        self::assertSame('Séparateur', $this->renderRating($criterion));
    }
}
