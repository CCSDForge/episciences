<?php

namespace unit\modules\journal\views;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Rendering tests for partials/user_dashboard.phtml
 *
 * The template renders the same status counts in two layouts (quadrant grid and flat list)
 * from a single data structure. These tests pin down that both layouts stay in sync and that
 * every status the journal workflow can produce is still displayed somewhere.
 */
class UserDashboardPartialTest extends TestCase
{
    private const PARTIAL = 'partials/user_dashboard.phtml';

    private Zend_View $view;

    private ?\Zend_Controller_Request_Abstract $previousRequest = null;

    protected function setUp(): void
    {
        $this->view = new Zend_View();
        $this->view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');

        // The template builds links with the url() view helper, which needs a router holding
        // the default module route. Nothing dispatches a request in a unit test, so set it up
        // here and restore the front controller state afterwards.
        $front = \Zend_Controller_Front::getInstance();
        $this->previousRequest = $front->getRequest();

        if ($this->previousRequest === null) {
            $front->setRequest(new \Zend_Controller_Request_Http('http://localhost/'));
        }

        $router = $front->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
    }

    protected function tearDown(): void
    {
        if ($this->previousRequest !== null) {
            return;
        }

        // Zend_Controller_Front::setRequest() rejects null, so clear the singleton's request
        // directly to leave no dummy request behind for the next test.
        $property = new \ReflectionProperty(\Zend_Controller_Front::class, '_request');
        $property->setAccessible(true);
        $property->setValue(\Zend_Controller_Front::getInstance(), null);
    }

    /**
     * @param int[] $statuses one paper per given status
     * @return Episciences_Paper[]
     */
    private function papersWithStatuses(array $statuses): array
    {
        $papers = [];
        foreach ($statuses as $i => $status) {
            $papers[] = new Episciences_Paper(['DOCID' => 1000 + $i, 'STATUS' => $status]);
        }

        return $papers;
    }

    /**
     * @param Episciences_Paper[] $papers
     */
    private function render(array $papers, bool $gridLayout): string
    {
        return $this->view->partial(self::PARTIAL, [
            'controllerName' => 'administratepaper',
            'actionName' => 'list',
            'papers' => $papers,
            'gridLayout' => $gridLayout,
        ]);
    }

    public function testGridLayoutRendersFourQuadrantsOverTwoRows(): void
    {
        $output = $this->render($this->papersWithStatuses([Episciences_Paper::STATUS_SUBMITTED]), true);

        self::assertSame(4, substr_count($output, 'dashboard-quadrant-title'), 'One title per quadrant');
        self::assertSame(2, substr_count($output, '<div class="row">'), 'Quadrants are laid out two by two');
        self::assertStringContainsString('<h3 class="dashboard-quadrant-title">', $output, 'Quadrant titles must be h3: the panel title is an h2');
    }

    public function testLinearLayoutRendersNoQuadrant(): void
    {
        $output = $this->render($this->papersWithStatuses([Episciences_Paper::STATUS_SUBMITTED]), false);

        self::assertStringNotContainsString('dashboard-quadrant', $output);
        self::assertStringContainsString('article sans relecteur', $output);
    }

    public function testLayoutDefaultsToLinearWhenNotRequested(): void
    {
        $output = $this->view->partial(self::PARTIAL, [
            'controllerName' => 'administratepaper',
            'actionName' => 'list',
            'papers' => $this->papersWithStatuses([Episciences_Paper::STATUS_SUBMITTED]),
        ]);

        self::assertStringNotContainsString('dashboard-quadrant', $output);
        self::assertStringContainsString('article sans relecteur', $output);
    }

    /**
     * Both layouts are generated from the same data structure: a status displayed in one must be
     * displayed in the other. This is the regression this refactoring has to keep out.
     *
     * @dataProvider displayedStatusProvider
     */
    public function testStatusIsDisplayedInBothLayouts(int $status, string $expectedLabel): void
    {
        $papers = $this->papersWithStatuses([$status]);

        self::assertStringContainsString($expectedLabel, $this->render($papers, true), 'Missing from the grid layout');
        self::assertStringContainsString($expectedLabel, $this->render($papers, false), 'Missing from the linear layout');
    }

    /**
     * Every status the previous template listed, with its singular label.
     *
     * @return array<string, array{int, string}>
     */
    public static function displayedStatusProvider(): array
    {
        $cases = [
            Episciences_Paper::STATUS_SUBMITTED => 'article sans relecteur',
            Episciences_Paper::STATUS_OK_FOR_REVIEWING => 'article en attente de relecture',
            Episciences_Paper::STATUS_BEING_REVIEWED => 'article en cours de relecture',
            Episciences_Paper::STATUS_REVIEWED => 'article évalué, en attente de décision éditoriale',
            Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION => 'article en attente de modifications mineures',
            Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION => 'article en attente de modifications majeures',
            Episciences_Paper::STATUS_NO_REVISION => "article en attente d'une décision : refus de modifications",
            Episciences_Paper::STATUS_ACCEPTED => 'article accepté',
            Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION => "article accepté, en attente de la version finale de l'auteur",
            Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'article accepté, en attente de modifications majeures',
            Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING => 'article accepté - version finale soumise, en attente de la mise en forme par la revue',
            Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED => 'version temporaire acceptée, en attente de la version finale',
            Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION => "version temporaire acceptée après modification de l'auteur",
            Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION => 'version temporaire acceptée, en attente des modifications mineures',
            Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION => 'version temporaire acceptée, en attente des modifications majeures',
            Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES => 'article en attente des sources auteurs',
            Episciences_Paper::STATUS_CE_AUTHOR_SOURCES_DEPOSED => 'article en attente de la mise en forme par la revue',
            Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED => 'article mis en forme par la revue, en attente de la version finale',
            Episciences_Paper::STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION => "article accepté, en attente de la validation par l'auteur",
            Episciences_Paper::STATUS_CE_WAITING_AUTHOR_FINAL_VERSION => 'article en attente de la version finale',
            Episciences_Paper::STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED => 'article version finale en attente de validation',
            Episciences_Paper::STATUS_CE_AUTHOR_FORMATTING_DEPOSED => "article mis en forme par l'auteur, en attente de la version définitive",
            Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION => "article approuvé par l'auteur, en attente de publication",
            Episciences_Paper::STATUS_CE_READY_TO_PUBLISH => 'article en attente de la publication',
            Episciences_Paper::STATUS_PUBLISHED => 'article publié',
            Episciences_Paper::STATUS_ABANDONED => 'article abandonné',
            Episciences_Paper::STATUS_REFUSED => 'article refusé',
        ];

        $data = [];
        foreach ($cases as $status => $label) {
            $data["status $status"] = [$status, $label];
        }

        return $data;
    }

    public function testEmptyPaperListShowsTheEmptyMessage(): void
    {
        $output = $this->render([], true);

        self::assertStringContainsString('Aucun article pour le moment.', $output);
        self::assertStringNotContainsString('Total', $output);
    }

    public function testTotalIsRenderedWithoutStatusFilter(): void
    {
        $output = $this->render(
            $this->papersWithStatuses([Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_PUBLISHED]),
            true
        );

        self::assertStringContainsString('Total', $output);
        self::assertStringContainsString('<strong>2</strong>', $output);
    }
}
