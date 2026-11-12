<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use DateTimeImmutable;
use Episciences\Reviewer\LightAnswer;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Answers given without an account on the paper management page (partials/paper_light_answers.phtml, #319)
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

    public function testTheMessageIsEscapedAndTheFileIsLinkedThroughTheProtectedAction(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        $answer = new LightAnswer(1, 999701, RVID, 999702, 999703, 999704, true, "Good <b>paper</b>\nline 2", 'op<inion>.pdf', new DateTimeImmutable('2026-01-01 10:00:00'));
        $paper = (new Episciences_Paper())->setDocid(999702)->setRvid(RVID);

        $html = $view->partial('partials/paper_light_answers.phtml', ['paper' => $paper, 'answers' => [$answer]]);

        self::assertStringContainsString('Good &lt;b&gt;paper&lt;/b&gt;<br />', $html);
        self::assertStringNotContainsString('<b>paper</b>', $html);
        self::assertStringContainsString('href="/file/lightanswer?aid=999701"', $html);
        self::assertStringContainsString('op&lt;inion&gt;.pdf', $html);
    }
}
