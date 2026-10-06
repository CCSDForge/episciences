<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * The modal structure is reachable without authentication: the request must not be
 * able to choose the markup or the style rendered by the modal views, and the error
 * view must escape the messages it prints unless the controller flags trusted markup.
 */
final class PartialDefaultControllerTest extends TestCase
{
    private const MODULES = APPLICATION_PATH . '/modules/';

    protected function setUp(): void
    {
        require_once self::MODULES . 'common/controllers/PartialDefaultController.php';
    }

    private function request(array $params): \Zend_Controller_Request_Simple
    {
        $request = new \Zend_Controller_Request_Simple();
        $request->setParams($params);
        return $request;
    }

    private function render(string $script, array $vars): string
    {
        $view = new class extends \Zend_View {
            public function translate($messageid = null)
            {
                return (string) $messageid;
            }
        };
        foreach ($vars as $name => $value) {
            $view->$name = $value;
        }
        $view->setScriptPath(self::MODULES . dirname($script));
        return $view->render(basename($script));
    }

    public function testOnlyBooleanLayoutOptionsAreExtracted(): void
    {
        $options = \PartialDefaultController::extractModalOptions($this->request([
            'style' => ['x' => '" onload="alert(1)'],
            'content' => '<script>alert(1)</script>',
            'hideSubmit' => 'true',
            'buttons' => 'false',
        ]));

        self::assertSame(['buttons' => false, 'hideSubmit' => true], $options);
    }

    public function testAbsentOptionsAreNotSet(): void
    {
        self::assertSame([], \PartialDefaultController::extractModalOptions($this->request([])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function modalViewProvider(): array
    {
        return [
            'journal modal' => ['journal/views/scripts/partials/modal.phtml'],
            'common modal' => ['common/views/scripts/partials/modal.phtml'],
        ];
    }

    /**
     * @dataProvider modalViewProvider
     */
    public function testModalViewsIgnoreInjectedStyleAndContent(string $view): void
    {
        $html = $this->render($view, [
            'style' => ['x' => '" onload="alert(1)'],
            'content' => '<script>alert(1)</script>',
        ]);

        self::assertStringNotContainsString('onload', $html);
        self::assertStringNotContainsString('alert(1)', $html);
    }

    /**
     * @dataProvider modalViewProvider
     */
    public function testModalViewsHideButtonsOnlyWhenAskedTo(string $view): void
    {
        self::assertStringContainsString('modal-footer', $this->render($view, []));
        self::assertStringNotContainsString('data-dismiss="modal">Fermer', $this->render($view, ['buttons' => false]));
    }

    public function testErrorViewEscapesUntrustedMessages(): void
    {
        $html = $this->render('journal/views/scripts/error/error.phtml', [
            'message' => '<img src=x onerror=alert(1)>',
            'description' => '<script>alert(2)</script>',
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testErrorViewKeepsTrustedDescriptionMarkup(): void
    {
        $html = $this->render('journal/views/scripts/error/error.phtml', [
            'message' => 'm',
            'description' => "<a href='/user/login'>Login</a>",
            'descriptionIsHtml' => true,
        ]);

        self::assertStringContainsString("<a href='/user/login'>Login</a>", $html);
    }

    public function testDenyAndHttpErrorViewsEscapeTheMessage(): void
    {
        foreach (['deny', 'http_error'] as $script) {
            $html = $this->render("journal/views/scripts/error/$script.phtml", ['message' => '<b>x</b>']);
            self::assertStringNotContainsString('<b>', $html, $script);
        }
    }
}
