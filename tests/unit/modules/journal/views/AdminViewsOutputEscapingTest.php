<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Output escaping of values coming from users or from repository records in the
 * administration views.
 */
final class AdminViewsOutputEscapingTest extends TestCase
{
    private const SCRIPTS = '/modules/journal/views/scripts/';

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPTS);
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');

        return $view;
    }

    public function testLinkedDataLogEscapesTheActingUserName(): void
    {
        $view = $this->view();
        $view->username = self::PAYLOAD;
        $view->typeLd = 'doi';
        $view->valueLd = '10.1234/abc';

        $html = $view->render('partials/paper_history_logs_linked_data.phtml');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function testLinkedDataLogToleratesAMissingUserName(): void
    {
        $view = $this->view();
        $view->username = null;
        $view->typeLd = 'doi';
        $view->valueLd = '10.1234/abc';

        self::assertStringContainsString('log-user-name"></span>', $view->render('partials/paper_history_logs_linked_data.phtml'));
    }

    public function testUserActionLogEscapesTheTagAndToleratesNull(): void
    {
        $view = $this->view();
        $view->fullName = 'Jane Doe';
        $view->tag = self::PAYLOAD;

        $html = $view->render('partials/paper_history_logs_user_action.phtml');
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);

        $view->tag = null;
        self::assertStringContainsString('Jane Doe', $view->render('partials/paper_history_logs_user_action.phtml'));
    }

    public function testDeadlineElementEscapesTheStoredValue(): void
    {
        $element = new \Zend_Form_Element_Text('review_deadline');
        $element->setValue('"><img src=x onerror=alert(1)> day');

        $view = $this->view();
        $view->element = $element;

        $html = $view->render('review/deadline_element.phtml');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;img', $html);
    }

    public function testReviewingDeadlineLogEscapesTheScreenName(): void
    {
        $view = $this->view();
        $view->newDeadline = '2026-12-31';
        $view->screenName = self::PAYLOAD;

        $html = $view->render('partials/paper_history_logs_reviewing_deadline.phtml');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    /**
     * The target ends up in a <script> block: whatever it holds must stay a JS string literal.
     *
     * @return array<string, array{string}>
     */
    public static function scriptBreakingTargetProvider(): array
    {
        return [
            'closing script tag' => ['</script><img src=x onerror=alert(1)>'],
            'quote and statement' => ["';alert(1);//"],
            'double quote' => ['";alert(1);//'],
            'line separator' => ["a\u{2028}b"],
        ];
    }

    /**
     * @dataProvider scriptBreakingTargetProvider
     */
    public function testContactsTargetCannotBreakOutOfTheScriptBlock(string $target): void
    {
        $view = $this->view();
        $view->target = $target;
        $view->js_contacts = '[]';

        // The template declares a global constant: a second render in the same process warns about it
        set_error_handler(static fn(int $no, string $str): bool => str_contains($str, 'JS_PREFIX already defined'), E_WARNING);
        try {
            $html = $view->render('administratemail/getcontacts.phtml');
        } finally {
            restore_error_handler();
        }

        self::assertSame(1, preg_match('/^\s*var target = (.*);$/m', $html, $matches), 'the target is assigned once');
        self::assertSame($target, json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR), 'a valid JS string literal holding the target');
        self::assertStringNotContainsString('</script><img', $html);
        self::assertSame(1, substr_count(strtolower($html), '</script>'), 'only the closing tag of the block itself');
    }

    private function newVersionLink(?string $zIdentifier): string
    {
        $request = new \Zend_Controller_Request_HttpTestCase();
        $request->setControllerName('paper');
        $front = \Zend_Controller_Front::getInstance();
        $previous = $front->getRequest();
        $front->setRequest($request);

        try {
            $paper = $this->createMock(\Episciences_Paper::class);
            $paper->method('isRevisionRequested')->willReturn(true);
            $paper->method('getStatus')->willReturn(\Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION);

            $view = $this->view();
            $view->paper = $paper;
            $view->review = $this->createMock(\Episciences_Review::class);
            $view->current_demand = ['PCID' => 12];
            $view->zIdentifier = $zIdentifier;
            $view->doNotDisplayContactChoice = true;

            return $view->render('partials/answer_revision_request_form.phtml');
        } finally {
            if ($previous !== null) {
                $front->setRequest($previous);
            } else {
                (new \ReflectionProperty($front, '_request'))->setValue($front, null);
            }
        }
    }

    public function testNewVersionLinkEncodesTheZIdentifier(): void
    {
        $html = $this->newVersionLink('x"><img src=x onerror=alert(1)>&a=b');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('href="/paper/newversion?id=12&amp;z-identifier=x%22%3E%3Cimg', $html);
        self::assertStringNotContainsString('&a=b', $html, 'an ampersand in the identifier must not add a parameter');
    }

    public function testNewVersionLinkWithoutZIdentifier(): void
    {
        self::assertStringContainsString('href="/paper/newversion?id=12"', $this->newVersionLink(null));
    }

    // ------------------------------------------------------------------
    // Paper titles harvested from the repositories
    // ------------------------------------------------------------------

    /**
     * Renders a script with a request on the given controller, as the paper lists build their
     * links from it.
     */
    private function renderWithRequest(Zend_View $view, string $script, string $controller): string
    {
        $request = new \Zend_Controller_Request_HttpTestCase();
        $request->setControllerName($controller)->setActionName('list');
        $front = \Zend_Controller_Front::getInstance();
        $previous = $front->getRequest();
        $front->setRequest($request);

        try {
            return $view->render($script);
        } finally {
            if ($previous !== null) {
                $front->setRequest($previous);
            } else {
                (new \ReflectionProperty($front, '_request'))->setValue($front, null);
            }
        }
    }

    /**
     * @return \Episciences_Paper&\PHPUnit\Framework\MockObject\MockObject
     */
    private function paperWithTitle(string $title): \Episciences_Paper
    {
        $submitter = $this->createMock(\Episciences_User::class);
        $submitter->method('getFullname')->willReturn(self::PAYLOAD);
        $submitter->method('getScreenName')->willReturn(self::PAYLOAD);

        $paper = $this->createMock(\Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(12);
        $paper->method('getPaperid')->willReturn(12);
        $paper->method('getStatus')->willReturn(\Episciences_Paper::STATUS_SUBMITTED);
        $paper->method('getTitle')->willReturn($title);
        $paper->method('getVersion')->willReturn(1.0);
        $paper->method('getVid')->willReturn(0);
        $paper->method('getSid')->willReturn(0);
        $paper->method('getWhen')->willReturn('2026-10-01 10:00:00');
        $paper->method('getPreviousVersions')->willReturn(null);
        $paper->method('getSubmitter')->willReturn($submitter);

        return $paper;
    }

    public function testReviewerRatingListEscapesThePaperTitle(): void
    {
        $reviewing = $this->createMock(\Episciences_Reviewer_Reviewing::class);
        $reviewing->method('getStatus')->willReturn(\Episciences_Reviewer_Reviewing::STATUS_PENDING);
        $reviewing->method('getAssignment')->willReturn(null);

        $reviewer = $this->createMock(\Episciences_Reviewer::class);
        $reviewer->method('getInvitation')->willReturn(null);
        $reviewer->method('getReviewing')->willReturn($reviewing);

        $view = $this->view();
        $view->papers = [$this->paperWithTitle(self::PAYLOAD)];
        $view->reviewer = $reviewer;
        $view->volumes = [];
        $view->sections = [];

        $html = $this->renderWithRequest($view, 'partials/datatable_ratings.phtml', 'reviewer');

        // The status icon is the only image of the row
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('<strong>&lt;img src=x onerror=alert(1)&gt;</strong>', $html);
    }

    public function testAuthorPaperListEscapesThePaperTitle(): void
    {
        $view = $this->view();
        $view->papers = [$this->paperWithTitle(self::PAYLOAD)];
        $view->volumes = [];
        $view->sections = [];

        $html = $this->renderWithRequest($view, 'partials/datatable_author_papers_list.phtml', 'paper');

        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('<strong>&lt;img src=x onerror=alert(1)&gt;</strong>', $html);
    }

    /**
     * Long titles are cut before being escaped, so that no entity is cut in the middle.
     */
    public function testPaperListsCutLongTitlesBeforeEscapingThem(): void
    {
        $title = str_repeat('a', 74) . '&b';

        $view = $this->view();
        $view->papers = [$this->paperWithTitle($title)];
        $view->volumes = [];
        $view->sections = [];

        $html = $this->renderWithRequest($view, 'partials/datatable_author_papers_list.phtml', 'paper');

        self::assertStringContainsString('<strong>' . str_repeat('a', 74) . '&amp;...</strong>', $html);
    }

    public function testControlBoardRowsEscapeThePaperTitleAndTheContributor(): void
    {
        $view = $this->view();
        $view->papers = [$this->paperWithTitle(self::PAYLOAD)];
        $view->output = ['aaData' => []];
        $view->volumes = [];
        $view->sections = [];
        $view->registerHelper(new class extends \Zend_View_Helper_Abstract {
            public function partial(): string
            {
                return '';
            }
        }, 'partial');
        $view->registerHelper(new class extends \Zend_View_Helper_Abstract {
            public function date(): string
            {
                return '2026-10-01';
            }
        }, 'date');

        $json = $this->renderWithRequest($view, 'administratepaper/ajaxcontrolboard.phtml', 'administratepaper');
        $row = json_decode($json, true, 512, JSON_THROW_ON_ERROR)['aaData'][0];

        self::assertStringNotContainsString('<img', $row[2], 'paper title');
        self::assertStringContainsString('title="&lt;img src=x onerror=alert(1)&gt;"', $row[2]);
        self::assertSame('&lt;img src=x onerror=alert(1)&gt;', $row[7], 'contributor');
    }

    public function testInviteReviewerPageDescriptionEscapesThePaperTitle(): void
    {
        $layout = new \Zend_Layout();
        $layoutHelper = new \Zend_View_Helper_Layout();
        $layoutHelper->setLayout($layout);

        $view = $this->view();
        $view->paper = $this->paperWithTitle(self::PAYLOAD);
        $view->registerHelper($layoutHelper, 'layout');
        // The scripts and the invitation forms are not under test
        $view->registerHelper(new class extends \Zend_View_Helper_Abstract {
            public function jQuery(): self
            {
                return $this;
            }

            /**
             * @param array<mixed> $arguments
             */
            public function __call(string $name, array $arguments): self
            {
                return $this;
            }
        }, 'jQuery');
        $view->registerHelper(new class extends \Zend_View_Helper_Abstract {
            public function partial(): string
            {
                return '';
            }
        }, 'partial');

        // Script URLs of the page, defined by public/const.php outside of the tests
        foreach (['VENDOR_TINYMCE_JQUERY', 'VENDOR_TINYMCE', 'TINYMCE_DIR', 'VENDOR_JQUERY_DATATABLES', 'VENDOR_DATATABLES_BOOTSTRAP', 'VENDOR_DATATABLES_CSS', 'VENDOR_JQUERY_UI_THEME_CSS'] as $constant) {
            if (!defined($constant)) {
                define($constant, '');
            }
        }

        $hadLang = \Zend_Registry::isRegistered('lang');
        if (!$hadLang) {
            \Zend_Registry::set('lang', 'en');
        }
        // The page gives the name of the inviting editor to its scripts
        $auth = \Episciences_Auth::getInstance();
        $previousStorage = $auth->getStorage();
        $auth->setStorage(new \Zend_Auth_Storage_NonPersistent());
        $editor = $this->createMock(\Episciences_User::class);
        $editor->method('getFullName')->willReturn('Jane Editor');
        $auth->getStorage()->write($editor);

        try {
            $view->render('administratepaper/invitereviewer.phtml');
        } finally {
            $auth->clearIdentity();
            $auth->setStorage($previousStorage);
            if (!$hadLang) {
                \Zend_Registry::getInstance()->offsetUnset('lang');
            }
        }

        // The layout prints the description as it is
        self::assertStringNotContainsString('<img', (string)$layout->description);
        self::assertStringEndsWith('#12 - &lt;img src=x onerror=alert(1)&gt;', (string)$layout->description);
    }
}
