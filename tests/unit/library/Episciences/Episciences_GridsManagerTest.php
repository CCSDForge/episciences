<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_GridsManager.
 *
 * Methods requiring the filesystem (REVIEW_GRIDS_PATH) or a DB are excluded.
 * getCriterionFormDefaults() is pure (no DB, no filesystem) and is the
 * primary target.
 *
 * @covers Episciences_GridsManager
 */
class Episciences_GridsManagerTest extends TestCase
{
    // =========================================================================
    // getCriterionFormDefaults() — pure method, no DB
    // =========================================================================

    private function makeCriterion(array $options = []): Episciences_Rating_Criterion
    {
        $c = new Episciences_Rating_Criterion();
        foreach ($options as $method => $value) {
            $c->$method($value);
        }
        return $c;
    }

    public function testGetCriterionFormDefaultsFreeCriterion(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertIsArray($defaults);
        self::assertSame(Episciences_Rating_Criterion::EVALUATION_TYPE_FREE, $defaults['evaluation_type']);
        self::assertSame('editors', $defaults['visibility']);
    }

    public function testGetCriterionFormDefaultsFallsBackToEditorsWhenVisibilityEmpty(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility('');
        $c->setPosition(1);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        // Empty visibility must default to 'editors'
        self::assertSame('editors', $defaults['visibility']);
    }

    public function testGetCriterionFormDefaultsQuantitativeCriterion(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);
        $c->setPosition(1);
        $c->setCoefficient(1);

        // 11 options → quantitative_rating_type = 0 (scale 0-10)
        $opts = [];
        for ($i = 0; $i <= 10; $i++) {
            $opts[] = ['value' => $i, 'label' => null];
        }
        $c->setOptions($opts);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertSame(Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE, $defaults['evaluation_type']);
        self::assertSame(0, $defaults['quantitative_rating_type']);
    }

    public function testGetCriterionFormDefaultsQualitativeCriterion(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);
        $c->setCoefficient(0);
        $c->setOptions([
            ['value' => 0, 'label' => null],
            ['value' => 1, 'label' => null],
            ['value' => 2, 'label' => null],
        ]);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertSame(Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, $defaults['evaluation_type']);
        self::assertSame(0, $defaults['qualitative_rating_type']); // not custom
    }

    public function testGetCriterionFormDefaultsIncludesOptionsArray(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);

        $opts = [['value' => 0, 'label' => null]];
        $c->setOptions($opts);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertArrayHasKey('options', $defaults);
        self::assertSame($opts, $defaults['options']);
    }

    public function testGetCriterionFormDefaultsIncludesLabelsByLanguage(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);
        $c->setLabels(['en' => 'Clarity', 'fr' => 'Clarté']);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertArrayHasKey('critere', $defaults);
        self::assertSame('Clarity', $defaults['critere']['en']);
        self::assertSame('Clarté', $defaults['critere']['fr']);
    }

    public function testGetCriterionFormDefaultsIncludesDescriptionsByLanguage(): void
    {
        $c = $this->makeCriterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);
        $c->setDescriptions(['en' => 'How clear is the paper?']);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertArrayHasKey('description', $defaults);
        self::assertSame('How clear is the paper?', $defaults['description']['en']);
    }

    // =========================================================================
    // sortCriterion() — regex pattern logic (pure)
    // =========================================================================

    public function testSortCriterionRegexPattern(): void
    {
        // The pattern used in sortCriterion(): preg_match("#grid_(.*)_criterion_(.*)#", $item, $matches)
        $item = 'grid_myGrid_criterion_42';
        preg_match('#grid_(.*)_criterion_(.*)#', $item, $matches);

        self::assertNotEmpty($matches);
        self::assertSame('myGrid', $matches[1]);
        self::assertSame('42', $matches[2]);
    }

    public function testSortCriterionRegexReturnsEmptyOnNonMatchingItem(): void
    {
        $item = 'not_a_valid_item';
        preg_match('#grid_(.*)_criterion_(.*)#', $item, $matches);

        self::assertEmpty($matches);
    }

    public function testGetCriterionFormDefaultsReopensAStandardQualitativeCriterionAsStandard(): void
    {
        // the standard options are saved with their Yes / No / Maybe labels
        $c = new Episciences_Rating_Criterion(['visibility' => 'editors', 'options' => Episciences_Rating_Criterion::STANDARD_QUALITATIVE_OPTIONS]);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertSame(Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, $defaults['evaluation_type']);
        self::assertSame(0, $defaults['qualitative_rating_type']);
    }

    public function testGetCriterionFormDefaultsReopensACustomQualitativeCriterionAsCustom(): void
    {
        $c = new Episciences_Rating_Criterion(['visibility' => 'editors', 'options' => [
            ['value' => 0, 'label' => ['en' => 'Good']],
            ['value' => 1, 'label' => ['en' => 'Bad']],
        ]]);

        $defaults = Episciences_GridsManager::getCriterionFormDefaults($c);

        self::assertSame(1, $defaults['qualitative_rating_type']);
    }

    // =========================================================================
    // getCriterionRatingFromPost()
    // =========================================================================

    /**
     * @return array<string, array{array<string, mixed>, string, int|null}>
     */
    public static function criterionRatingProvider(): array
    {
        $customOptions = ['option_0' => ['en' => 'Good'], 'option_1' => ['en' => 'Bad']];

        return [
            'free' => [['evaluation_type' => 'free'] + $customOptions, Episciences_Rating_Criterion::EVALUATION_TYPE_FREE, null],
            'quantitative 0-10' => [['evaluation_type' => 'quantitative', 'quantitative_rating_type' => '0'] + $customOptions, Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_10, 11],
            'quantitative 0-5' => [['evaluation_type' => 'quantitative', 'quantitative_rating_type' => '2'] + $customOptions, Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_5, 6],
            'quantitative custom' => [['evaluation_type' => 'quantitative', 'quantitative_rating_type' => '1'] + $customOptions, Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE_CUSTOM, 2],
            'qualitative standard' => [['evaluation_type' => 'qualitative', 'qualitative_rating_type' => '0'] + $customOptions, Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE, 3],
            'qualitative custom' => [['evaluation_type' => 'qualitative', 'qualitative_rating_type' => '1'] + $customOptions, Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM, 2],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @dataProvider criterionRatingProvider
     */
    public function testGetCriterionRatingFromPostGivesADistinctSubTypePerRating(array $post, string $expectedSubType, ?int $expectedOptionsCount): void
    {
        $rating = Episciences_GridsManager::getCriterionRatingFromPost($post);

        self::assertSame($expectedSubType, $rating['subType']);
        self::assertSame($expectedOptionsCount, $rating['options'] === null ? null : count($rating['options']));
    }

    public function testGetCriterionRatingFromPostForcesTheStandardQualitativeOptions(): void
    {
        $rating = Episciences_GridsManager::getCriterionRatingFromPost([
            'evaluation_type' => 'qualitative',
            'qualitative_rating_type' => '0',
            'option_0' => ['en' => 'Something else'],
        ]);

        self::assertSame(Episciences_Rating_Criterion::STANDARD_QUALITATIVE_OPTIONS, $rating['options']);
    }

    public function testGetCriterionRatingFromPostCleansTheCustomLabels(): void
    {
        $rating = Episciences_GridsManager::getCriterionRatingFromPost([
            'evaluation_type' => 'qualitative',
            'qualitative_rating_type' => '1',
            'option_0' => ['en' => ' <b>Good</b> & fair ', 'fr' => '  '],
        ]);

        self::assertSame([['value' => 0, 'label' => ['en' => 'Good &amp; fair']]], $rating['options']);
    }
}
