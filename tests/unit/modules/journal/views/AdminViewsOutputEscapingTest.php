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

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPLICATION_PATH . self::SCRIPTS . $relativePath);
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPTS);

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

    /**
     * @return array<string, array{string, string}>
     */
    public static function escapedOutputProvider(): array
    {
        return [
            'paper list title (attribute)' => [
                'administratepaper/datatable_list.phtml',
                'title="<?= htmlspecialchars(Episciences_Tools::decodeLatex($title)) ?>"',
            ],
            'paper list title (body)' => [
                'administratepaper/datatable_list.phtml',
                '<strong><?= htmlspecialchars(Ccsd_Tools::truncate(Episciences_Tools::decodeLatex($title), 75)) ?></strong>',
            ],
            'managed papers title' => [
                'administratepaper/managed.phtml',
                'htmlspecialchars(Ccsd_Tools::truncate($paper->getTitle(), 75))',
            ],
            'getcontacts target (JS context)' => [
                'administratemail/getcontacts.phtml',
                'var target = <?= json_encode((string)$this->target, JSON_HEX_TAG',
            ],
            'new version link' => [
                'partials/answer_revision_request_form.phtml',
                'href="<?= $this->escape($newVersionHref) ?>"',
            ],
            'new version link, query string' => [
                'partials/answer_revision_request_form.phtml',
                "'&z-identifier=' . urlencode((string)\$this->zIdentifier)",
            ],
            'volume form cancel button' => [
                'volume/form.phtml',
                'window.location=<?= htmlspecialchars(json_encode((string)$location, JSON_HEX_TAG',
            ],
        ];
    }

    /**
     * @dataProvider escapedOutputProvider
     */
    public function testViewEscapesTheValue(string $view, string $expected): void
    {
        self::assertStringContainsString($expected, $this->source($view));
    }

    public function testVolumeEditRefererOnlyUsesAnIntegerDocId(): void
    {
        $controller = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/VolumeController.php'
        );

        self::assertStringContainsString("\$docId = (int)\$request->getParam('docid');", $controller);
    }
}
