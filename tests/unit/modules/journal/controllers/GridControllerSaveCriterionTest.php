<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use DOMDocument;
use Episciences_Acl;
use Episciences_Rating_Criterion;
use Episciences_Rating_Grid;
use GridController;
use PHPUnit\Framework\Attributes\DataProvider;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Front;
use Zend_Locale;
use Zend_Registry;

/**
 * GridController::addcriterionAction() and addseparatorAction() write the criterion subtype
 * into the saved grid XML file (regression test for #1501).
 */
final class GridControllerSaveCriterionTest extends ControllerTestCase
{
    private int $gridId = 0;

    private string $gridFile = '';

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'GridController');
    }

    protected function setUp(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        if (!Zend_Registry::isRegistered('Zend_Locale')) {
            Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        }
        if (!Zend_Registry::isRegistered('languages')) {
            Zend_Registry::set('languages', ['en', 'fr']);
        }
        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('success')->clearCurrentMessages();
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('error')->clearCurrentMessages();

        $this->gridId = random_int(900000, 999999);
        $filename = 'grid_' . $this->gridId . '.xml';
        $this->gridFile = REVIEW_GRIDS_PATH . $filename;

        $grid = new Episciences_Rating_Grid(['filename' => $filename]);
        $grid->setCriteria([]);
        self::assertTrue($grid->save(REVIEW_GRIDS_PATH));
    }

    protected function tearDown(): void
    {
        if ($this->gridFile !== '' && file_exists($this->gridFile)) {
            @unlink($this->gridFile);
        }
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('success')->clearCurrentMessages();
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace('error')->clearCurrentMessages();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function criterionSubTypeProvider(): array
    {
        return [
            'quantitative 0-10' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE,
                    'quantitative_rating_type' => '0',
                    'coef' => '1',
                ],
                Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_10,
            ],
            'quantitative 0-5' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE,
                    'quantitative_rating_type' => '2',
                    'coef' => '2',
                ],
                Episciences_Rating_Criterion::SUBTYPE_QUANTITATIVE_5,
            ],
            'quantitative custom' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE,
                    'quantitative_rating_type' => '1',
                    'coef' => '1',
                    'option_0' => ['en' => 'Low', 'fr' => 'Faible'],
                    'option_1' => ['en' => 'High', 'fr' => 'Fort'],
                ],
                Episciences_Rating_Criterion::EVALUATION_TYPE_QUANTITATIVE_CUSTOM,
            ],
            'qualitative standard' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE,
                    'qualitative_rating_type' => '0',
                ],
                Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE,
            ],
            'qualitative custom' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE,
                    'qualitative_rating_type' => '1',
                    'option_0' => ['en' => 'Good', 'fr' => 'Bon'],
                    'option_1' => ['en' => 'Bad', 'fr' => 'Mauvais'],
                ],
                Episciences_Rating_Criterion::EVALUATION_TYPE_QUALITATIVE_CUSTOM,
            ],
            'free' => [
                [
                    'evaluation_type' => Episciences_Rating_Criterion::EVALUATION_TYPE_FREE,
                ],
                Episciences_Rating_Criterion::EVALUATION_TYPE_FREE,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $ratingPost
     */
    #[DataProvider('criterionSubTypeProvider')]
    public function testAddCriterionSavesExpectedSubTypeInXml(array $ratingPost, string $expectedSubType): void
    {
        $post = array_merge([
            'submit' => 'Valider',
            'critere' => ['en' => 'Methodology', 'fr' => 'Méthodologie'],
            'description' => ['en' => '', 'fr' => ''],
            'visibility' => Episciences_Rating_Criterion::VISIBILITY_EDITORS,
            'comment' => '1',
            'upload' => '0',
            'coef' => '1',
            'quantitative_rating_type' => '0',
            'qualitative_rating_type' => '0',
        ], $ratingPost);

        $request = $this->postRequest($post, true, ['rgid' => (string)$this->gridId]);
        $this->dispatch(GridController::class, 'addcriterion', $request);

        $dom = new DOMDocument();
        self::assertTrue($dom->load($this->gridFile));
        $divs = $dom->getElementsByTagName('div');
        self::assertSame(1, $divs->length);
        self::assertSame($expectedSubType, $divs->item(0)?->getAttribute('subtype'));

        $loaded = new Episciences_Rating_Grid();
        self::assertTrue($loaded->loadXML($this->gridFile));
        $criteria = $loaded->getCriteria();
        self::assertNotNull($criteria);
        self::assertCount(1, $criteria);
        self::assertSame($expectedSubType, $criteria[0]->getSubType());
    }

    public function testAddSeparatorSavesNoSubTypeAttributeInXml(): void
    {
        $post = [
            'submit' => 'Valider',
            'critere' => ['en' => 'Section 1', 'fr' => 'Section 1'],
            'description' => ['en' => '', 'fr' => ''],
            'visibility' => Episciences_Rating_Criterion::VISIBILITY_EDITORS,
        ];

        $request = $this->postRequest($post, true, ['rgid' => (string)$this->gridId]);
        $this->dispatch(GridController::class, 'addseparator', $request);

        $dom = new DOMDocument();
        self::assertTrue($dom->load($this->gridFile));
        $divs = $dom->getElementsByTagName('div');
        self::assertSame(1, $divs->length);
        self::assertFalse($divs->item(0)?->hasAttribute('subtype'));

        $loaded = new Episciences_Rating_Grid();
        self::assertTrue($loaded->loadXML($this->gridFile));
        $criteria = $loaded->getCriteria();
        self::assertNotNull($criteria);
        self::assertCount(1, $criteria);
        self::assertSame('', $criteria[0]->getSubType());
    }
}
