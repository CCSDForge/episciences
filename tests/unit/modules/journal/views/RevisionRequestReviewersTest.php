<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use AdministratepaperController;
use Episciences\Reviewer\NextVersionRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences\View\Helper\RequestTypeLabel;
use Episciences_Paper_Logger;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * What a revision request asks of the reviewers on the next version (#319, lot B): the request type and the automatic
 * reassignment, in the revision requests panel of the paper management page and in the history of the paper.
 */
final class RevisionRequestReviewersTest extends TestCase
{
    private const QUICK_OPINION = 12;

    private ?Zend_Translate $previousTranslate = null;
    private ?Zend_Locale $previousLocale = null;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php';
    }

    protected function setUp(): void
    {
        $this->previousLocale = Zend_Registry::isRegistered('Zend_Locale') ? Zend_Registry::get('Zend_Locale') : null;
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => require APPLICATION_PATH . '/languages/en/views.php',
            'locale' => 'en',
        ]));
    }

    protected function tearDown(): void
    {
        foreach (['Zend_Locale' => $this->previousLocale, 'Zend_Translate' => $this->previousTranslate] as $key => $previous) {
            if ($previous !== null) {
                Zend_Registry::set($key, $previous);
            } else {
                Zend_Registry::getInstance()->offsetUnset($key);
            }
        }
    }

    private function quickOpinion(): RequestType
    {
        return new RequestType(self::QUICK_OPINION, RVID, null, ['en' => 'Quick <opinion>']);
    }

    private function view(): Zend_View
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findAllForJournal')->willReturn([$this->quickOpinion()]);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');
        $helper = new RequestTypeLabel($repository);
        $helper->setView($view);
        $view->registerHelper($helper, 'requestTypeLabel');

        return $view;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function history(string $action, array $detail): string
    {
        return $this->view()->partial('partials/paper_history_log_body.phtml', [
            'log' => ['ACTION' => $action],
            'detail' => $detail,
            'status' => '',
            'fullName' => 'Editor Name',
            'tag' => '',
        ]);
    }

    public function testTheHistoryShowsTheTypeAndTheReassignmentRecordedWithTheRequest(): void
    {
        $html = $this->history(Episciences_Paper_Logger::CODE_MINOR_REVISION_REQUEST, [
            'reassign_reviewers' => true,
            'next_request_type' => ['id' => self::QUICK_OPINION, 'labels' => ['en' => 'Quick <opinion>']],
        ]);

        self::assertStringContainsString('New request to the reviewers:', $html);
        self::assertStringContainsString('Quick &lt;opinion&gt;', $html);
        self::assertStringNotContainsString('Quick <opinion>', $html);
        self::assertMatchesRegularExpression('/Automatic reassignment of the reviewers:\s*Yes/', $html);
    }

    public function testTheHistoryOfARequestWithTheSameTypeSaysSo(): void
    {
        $html = $this->history(Episciences_Paper_Logger::CODE_MAJOR_REVISION_REQUEST, ['reassign_reviewers' => false]);

        self::assertStringContainsString('Same type as the current request', $html);
        self::assertMatchesRegularExpression('/Automatic reassignment of the reviewers:\s*No/', $html);
    }

    public function testARequestLoggedBeforeTheseChoicesShowsNothing(): void
    {
        $html = $this->history(Episciences_Paper_Logger::CODE_MINOR_REVISION_REQUEST, ['deadline' => '2026-12-01']);

        self::assertStringNotContainsString('revision-request-reviewers', $html);
    }

    public function testThePanelShowsTheTypeOfTheStoredRequestFromItsOptions(): void
    {
        $summary = NextVersionRequestType::summaryOf('{"reassign_reviewers":true,"next_request_type_id":12}');
        self::assertNotNull($summary);
        $view = $this->view();

        $html = $view->partial('partials/revision_request_reviewers.phtml', [
            'typeLabel' => $view->requestTypeLabel($summary['type']),
            'reassign' => $summary['reassign'],
        ]);

        self::assertStringContainsString('Quick &lt;opinion&gt;', $html);
        self::assertMatchesRegularExpression('/Automatic reassignment of the reviewers:\s*Yes/', $html);
    }

    public function testTheLoggedDetailHoldsTheTypeWithItsLabelsAndTheReassignment(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findUsable')->with(self::QUICK_OPINION, RVID)->willReturn($this->quickOpinion());

        self::assertSame(
            [
                'reassign_reviewers' => true,
                'next_request_type' => ['id' => self::QUICK_OPINION, 'labels' => ['en' => 'Quick <opinion>']],
            ],
            AdministratepaperController::revisionRequestReviewersDetail(
                ['deadline' => '2026-12-01', 'reassign_reviewers' => true, NextVersionRequestType::OPTION => self::QUICK_OPINION],
                $repository
            )
        );
        self::assertSame([], AdministratepaperController::revisionRequestReviewersDetail(['deadline' => '2026-12-01'], $repository));
    }
}
