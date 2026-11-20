<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences\Reviewer\Workload;
use Episciences\Reviewer\WorkloadPresenter;
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

    private function render(?Workload $workload, array $visibleDocIds = []): string
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $presented = $workload === null || $workload->isEmpty()
            ? null
            : (new WorkloadPresenter($visibleDocIds, static fn(string $date): string => substr($date, 0, 10)))->present($workload);

        return $view->partial('partials/reviewer_workload.phtml', ['workload' => $presented]);
    }

    public function testReviewsInProgressPendingInvitationsAndTheLatestInvitation(): void
    {
        $html = $this->render(new Workload(1, 2, '2026-10-20 09:00:00', Workload::ANSWER_DECLINED));

        self::assertStringContainsString('Reviews in progress: 1', $html);
        self::assertStringContainsString('Pending invitations: 2', $html);
        self::assertStringContainsString('Latest invitation: 2026-10-20 (declined)', $html);
    }

    public function testOnlyWhatIsKnownIsShown(): void
    {
        $html = $this->render(new Workload(0, 0, '2025-01-05 10:00:00', Workload::ANSWER_EXPIRED));

        self::assertStringNotContainsString('Reviews in progress', $html);
        self::assertStringNotContainsString('Pending invitations', $html);
        self::assertStringContainsString('(expired)', $html);
    }

    public function testThePapersTheUserMayManageAreLinkedTheOthersCounted(): void
    {
        $workload = new Workload(1, 1, '2026-11-15 09:00:00', Workload::ANSWER_PENDING, [
            ['docid' => 10, 'kind' => Workload::PAPER_REVIEW, 'date' => '2026-12-15 00:00:00'],
            ['docid' => 12, 'kind' => Workload::PAPER_INVITATION, 'date' => '2026-11-15 09:00:00'],
        ], 12);

        $html = $this->render($workload, [10]);

        // the number only, without the title of the paper
        self::assertStringContainsString('href="/administratepaper/view/id/10" target="_blank" rel="noopener">#10<span class="sr-only"> (new tab)</span></a>', $html);
        self::assertStringContainsString('(review due on 2026-12-15)', $html);
        self::assertStringNotContainsString('/id/12', $html);
        self::assertStringContainsString('Other papers: 1', $html);
    }

    public function testTheTableListsAtMostThreePapers(): void
    {
        $papers = [];
        foreach (range(1, 5) as $docId) {
            $papers[] = ['docid' => $docId, 'kind' => Workload::PAPER_REVIEW, 'date' => null];
        }

        $html = $this->render(new Workload(5, 0, null, null, $papers), range(1, 5));

        self::assertSame(WorkloadPresenter::TABLE_PAPERS, substr_count($html, '<a href='));
        self::assertStringContainsString('Other papers: 2', $html);
    }

    public function testAReviewerNeverInvited(): void
    {
        self::assertStringContainsString('Never invited', $this->render(null));
        self::assertStringContainsString('Never invited', $this->render(new Workload()));
    }
}
