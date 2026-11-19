<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences\Reviewer\Workload;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Invitation page, known reviewers table: what each reviewer is already asked in the journal (git #320).
 */
final class ReviewerWorkloadPartialTest extends TestCase
{
    private ?Zend_Translate $previousTranslate = null;

    protected function setUp(): void
    {
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => require APPLICATION_PATH . '/languages/en/views.php',
            'locale' => 'en',
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->previousTranslate !== null) {
            Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    private function render(?Workload $workload): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        return $view->partial('partials/reviewer_workload.phtml', ['workload' => $workload]);
    }

    public function testReviewsInProgressPendingInvitationsAndTheLatestInvitation(): void
    {
        $html = $this->render(new Workload(1, 2, '2026-10-20 09:00:00', Workload::ANSWER_DECLINED));

        self::assertStringContainsString('Reviews in progress: 1', $html);
        self::assertStringContainsString('Pending invitations: 2', $html);
        self::assertMatchesRegularExpression('/Latest invitation: .*2026.* \(declined\)/', $html);
    }

    public function testOnlyWhatIsKnownIsShown(): void
    {
        $html = $this->render(new Workload(0, 0, '2025-01-05 10:00:00', Workload::ANSWER_EXPIRED));

        self::assertStringNotContainsString('Reviews in progress', $html);
        self::assertStringNotContainsString('Pending invitations', $html);
        self::assertStringContainsString('(expired)', $html);
    }

    public function testAReviewerNeverInvited(): void
    {
        self::assertStringContainsString('Never invited', $this->render(null));
        self::assertStringContainsString('Never invited', $this->render(new Workload()));
    }
}
