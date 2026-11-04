<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Statistics page: how the rates (stats/submissions.phtml) and the delays (stats/submission-delay.phtml)
 * are computed by the API is explained under their title, in French and in English.
 */
final class StatsFormulasViewTest extends TestCase
{
    private ?Zend_Translate $previousTranslate = null;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/StatsController.php';
    }

    protected function setUp(): void
    {
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
    }

    protected function tearDown(): void
    {
        if ($this->previousTranslate !== null) {
            Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    private function useLanguage(string $language): void
    {
        $content = $language === 'en' ? require APPLICATION_PATH . '/languages/en/views.php' : ['x' => 'x'];
        Zend_Registry::set('Zend_Translate', new Zend_Translate(['adapter' => 'array', 'content' => $content, 'locale' => $language]));
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        return $view;
    }

    private function renderRates(): string
    {
        $view = $this->view();
        $view->chart1Title = 'Soumissions';
        $view->acceptanceRate = 60.0;
        $view->publicationRate = 50.0;
        $view->declineRate = 40.0;

        return $view->render('stats/submissions.phtml');
    }

    private function renderDelays(): string
    {
        $view = $this->view();
        $view->submissionAcceptanceTime = 80;
        $view->submissionAcceptanceTimeUnit = 'DAY';
        $view->submissionAcceptanceTimeMedian = 60;
        $view->submissionAcceptanceTimeMedianUnit = 'DAY';

        return $view->render('stats/submission-delay.phtml');
    }

    public function testTheRatesGiveTheirFormulaAndWhatTheyCount(): void
    {
        $this->useLanguage('fr');

        $html = $this->renderRates();

        self::assertStringContainsString('Taux = articles concernés ÷ (articles acceptés + articles refusés) × 100', $html);
        self::assertStringContainsString("Taux d'acceptation + taux de refus = 100 %.", $html);
        self::assertStringContainsString("il ne dépasse pas le taux d'acceptation", $html);
        self::assertStringContainsString('les articles en attente de décision et les articles importés', $html);
        self::assertStringContainsString("l'année de la décision (acceptation, refus ou publication), pas de la soumission", $html);
        self::assertStringNotContainsString('(A/S)x100', $html);
    }

    public function testTheDelaysExplainTheAverageAndTheMedian(): void
    {
        $this->useLanguage('fr');

        $html = $this->renderDelays();

        self::assertStringContainsString('De la première soumission à la dernière acceptation (ou à la publication)', $html);
        self::assertStringContainsString("Moyenne : somme des délais ÷ nombre d'articles", $html);
        self::assertStringContainsString('Médiane : la moitié des articles a un délai inférieur ou égal', $html);
    }

    /**
     * The API counts the refusals by submission: a refused paper submitted again is a new submission.
     */
    public function testTheRefusalsAreCountedBySubmission(): void
    {
        $this->useLanguage('fr');

        foreach ([null, 2025] as $year) {
            $html = $this->renderDecisions($year);

            self::assertStringContainsString('Un seul rejet est comptabilisé par soumission. Un article refusé puis soumis à nouveau compte comme une nouvelle soumission.', $html);
            self::assertStringNotContainsString('un seul rejet est comptabilisé par article', $html);
        }

        $this->useLanguage('en');

        self::assertStringContainsString('Only one rejection is counted per submission. An article refused, then submitted again, counts as a new submission.', $this->renderDecisions(null));
    }

    public function testTheExplanationsAreTranslatedInEnglish(): void
    {
        $this->useLanguage('en');

        $rates = $this->renderRates();
        $delays = $this->renderDelays();

        self::assertStringContainsString('Rate = articles concerned ÷ (accepted articles + refused articles) × 100', $rates);
        self::assertStringContainsString('Acceptance rate + refusal rate = 100%.', $rates);
        self::assertStringContainsString('it does not exceed the acceptance rate', $rates);
        self::assertStringContainsString('Not counted: the articles awaiting a decision and the imported articles.', $rates);
        self::assertStringContainsString('By year: the year of the decision', $rates);
        self::assertStringContainsString('From the first submission to the latest acceptance', $delays);
        self::assertStringContainsString('Average: sum of the delays ÷ number of articles', $delays);
        self::assertStringContainsString('Median: half of the articles have a shorter or equal delay', $delays);
    }

    private function renderDecisions(?int $year): string
    {
        $view = $this->view();
        $view->chart1Title = 'Soumissions';
        $view->year = $year;
        $view->allAcceptations = 12;
        $view->allRefusals = 8;

        return $view->render('stats/submissions.phtml');
    }

    public function testByYearTheAcceptedAndRefusedArticlesIncludeThoseSubmittedBefore(): void
    {
        $this->useLanguage('fr');

        $html = $this->renderDecisions(2025);

        self::assertStringContainsString('Cela inclut les articles qui ont été soumis antérieurement et acceptés cette année.', $html);
        self::assertStringContainsString('Cela inclut les articles qui ont été soumis antérieurement et refusés cette année.', $html);
    }

    public function testWithoutYearTheAcceptedArticlesDoNotMentionTheYear(): void
    {
        $this->useLanguage('fr');

        $html = $this->renderDecisions(null);

        self::assertStringContainsString('Cela inclut les articles qui ont déjà été publiés', $html);
        self::assertStringNotContainsString('acceptés cette année', $html);
    }

    public function testByYearTheAcceptedArticlesAreExplainedInEnglish(): void
    {
        $this->useLanguage('en');

        $html = $this->renderDecisions(2025);

        self::assertStringContainsString('This includes articles that were previously submitted and accepted this year.', $html);
        self::assertStringContainsString('This includes articles that have already been published', $html);
    }
}
