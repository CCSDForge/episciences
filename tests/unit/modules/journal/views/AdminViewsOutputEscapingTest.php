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
}
