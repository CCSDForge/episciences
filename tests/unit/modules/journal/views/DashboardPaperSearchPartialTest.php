<?php

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rendering tests for partials/dashboard_paper_search.phtml
 *
 * The box carries no visible label, so its accessible name comes from the panel title passed
 * by the caller. Several boxes coexist on the dashboard: they must stay distinguishable.
 */
class DashboardPaperSearchPartialTest extends TestCase
{
    private const PARTIAL = 'partials/dashboard_paper_search.phtml';

    private Zend_View $view;

    protected function setUp(): void
    {
        $this->view = new Zend_View();
        $this->view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        // The partial queues its script through the jQuery view helper, which a bare
        // Zend_View does not know about
        $this->view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');
    }

    /**
     * @param array<string, string> $params
     */
    private function render(array $params = []): string
    {
        return $this->view->partial(self::PARTIAL, $params);
    }

    public function testRendersTheCompactSearchBox(): void
    {
        $output = $this->render();

        self::assertStringContainsString('data-component="dashboard-paper-search"', $output);
        self::assertStringContainsString('data-role="paper-identifier"', $output);
        self::assertStringContainsString('data-role="submit-btn"', $output);
        self::assertStringContainsString('input-group-sm', $output);
    }

    public function testFieldAndButtonBothCarryAnAccessibleName(): void
    {
        $output = $this->render(['title' => 'Accès à un article assigné']);

        self::assertStringContainsString('aria-label="Accès à un article assigné"', $output);
        self::assertStringContainsString('aria-label="Rechercher"', $output);
        // Decorative icon must be hidden from assistive technologies
        self::assertStringContainsString('aria-hidden="true"', $output);
    }

    public function testTitleDefaultsWhenNotProvided(): void
    {
        self::assertStringContainsString('aria-label="Accès à un article"', $this->render());
    }

    public function testFromParameterScopesTheComponent(): void
    {
        $output = $this->render(['from' => 'assignedArticles']);

        self::assertStringContainsString('data-from="assignedArticles"', $output);
        self::assertStringContainsString('data-suffix="_assignedArticles"', $output);
    }

    public function testSuffixIsEmptyWithoutFromParameter(): void
    {
        $output = $this->render();

        self::assertStringContainsString('data-suffix=""', $output);
        self::assertStringContainsString('data-from=""', $output);
    }

    public function testCallerSuppliedValuesAreEscaped(): void
    {
        $output = $this->render(['from' => '"><script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>alert(1)</script>', $output);
        self::assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * The wide variant (own panel-heading, col-md-11, inline styles) had no caller left once
     * every dashboard panel embedded the compact box in its header.
     */
    public function testWideVariantIsGone(): void
    {
        $output = $this->render();

        self::assertStringNotContainsString('col-md-11', $output);
        self::assertStringNotContainsString('panel-heading', $output);
        self::assertStringNotContainsString('input-group-addon', $output);
    }
}
