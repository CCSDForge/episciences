<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use DateTimeImmutable;
use Episciences\Reviewer\LightAnswer;
use Episciences_Paper;
use Episciences_Rating_Criterion;
use Episciences_Rating_Report;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Answers given without an account (#319): the filled grid shown to the editorial staff (partials/paper_light_answers.phtml,
 * partials/light_answer_report.phtml)
 */
final class PaperLightAnswersViewTest extends TestCase
{
    private bool $localeRegistered = false;

    protected function setUp(): void
    {
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
            $this->localeRegistered = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->localeRegistered) {
            \Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        return $view;
    }

    /**
     * A filled grid: one criterion with a chosen option, a comment and a file
     */
    private function filledReport(): Episciences_Rating_Report
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $criterion->setId('item_0');
        $criterion->setLabels(['en' => 'Relevance <b>']);
        $criterion->setOptions([['value' => 0, 'label' => ['en' => 'Low']], ['value' => 1, 'label' => ['en' => 'High <i>']]]);
        $criterion->setComment_setting(true);
        $criterion->setAttachment_setting(true);
        $criterion->setNote(1);
        $criterion->setComment('<p>Fine</p>');
        $criterion->setAttachment('op<inion>.pdf');

        $report = new Episciences_Rating_Report();
        $report->setCriteria([$criterion]);

        return $report;
    }

    public function testTheFilledGridIsEscapedAndItsFilesAreLinkedThroughTheProtectedActionForTheEditorialStaff(): void
    {
        $html = $this->view()->partial('partials/light_answer_report.phtml', ['report' => $this->filledReport(), 'aid' => 999701, 'withLinks' => true]);

        self::assertStringContainsString('Relevance &lt;b&gt;', $html);
        self::assertStringContainsString('High &lt;i&gt;', $html);
        self::assertStringContainsString('<p>Fine</p>', $html, 'the comment was purified when the grid was filled in');
        self::assertStringContainsString('href="/file/lightanswer?aid=999701&amp;file=op%3Cinion%3E.pdf"', $html);
        self::assertStringContainsString('op&lt;inion&gt;.pdf', $html);
    }

    public function testTheInvitedPersonSeesTheFileNamesWithoutLinks(): void
    {
        $html = $this->view()->partial('partials/light_answer_report.phtml', ['report' => $this->filledReport(), 'aid' => 999701, 'withLinks' => false]);

        self::assertStringNotContainsString('/file/lightanswer', $html);
        self::assertStringContainsString('op&lt;inion&gt;.pdf', $html);
    }

    public function testAnAnswerWhoseGridCannotBeReadIsStillListed(): void
    {
        $answer = new LightAnswer(1, 999701, RVID, 999702, 999703, 999704, true, new DateTimeImmutable('2026-01-01 10:00:00'));
        $paper = (new Episciences_Paper())->setDocid(999702)->setRvid(RVID);

        $html = $this->view()->partial('partials/paper_light_answers.phtml', ['paper' => $paper, 'answers' => [$answer]]);

        self::assertStringContainsString("La grille remplie n'a pas pu être lue.", $html);
    }
}
