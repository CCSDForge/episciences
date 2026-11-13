<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_HelperBroker;
use Zend_View;
use Zend_View_Helper_Abstract;

/**
 * Review settings page (#319): the rating grids, and a tab listing the review request types with the grid each one
 * uses, shown only to the users who manage the types.
 */
final class GridControllerListTest extends ControllerTestCase
{
    private const REPORT_TYPE_ID = 41;
    private const LIGHT_TYPE_ID = 42;
    private const CUSTOM_TYPE_ID = 43;

    private bool $localeRegistered = false;

    protected function setUp(): void
    {
        self::loadController('journal', 'GridController');

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
            $this->localeRegistered = true;
        }

        $this->givenJournal();

        $this->db->respondTo('/FROM `review_request_type` WHERE \(rvid = /', [
            ['id' => self::REPORT_TYPE_ID, 'rvid' => RVID, 'code' => 'full_report', 'labels' => '{"en":"Review report"}', 'light_answer' => 0, 'position' => 1, 'enabled' => 1],
            ['id' => self::LIGHT_TYPE_ID, 'rvid' => RVID, 'code' => 'quick_opinion', 'labels' => '{"en":"Quick opinion"}', 'light_answer' => 1, 'position' => 2, 'enabled' => 1],
            ['id' => self::CUSTOM_TYPE_ID, 'rvid' => RVID, 'code' => 'phase_two', 'labels' => '{"en":"Phase <two>"}', 'light_answer' => 0, 'position' => 3, 'enabled' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->localeRegistered) {
            \Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }

        parent::tearDown();
    }

    public function testTheRequestTypesTabListsTheTypesWithTheGridTheyUse(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $html = $this->renderList(['tab' => 'types']);

        self::assertStringContainsString('id="tab-types"', $html);
        self::assertMatchesRegularExpression('/id="tabpanel-types"[^>]*class="tab-pane active"/', $html, 'the requested tab is shown');
        self::assertMatchesRegularExpression('/id="tabpanel-grids"[^>]*hidden/', $html, 'the other tab is hidden');
        self::assertStringContainsString('Phase &lt;two&gt;', $html, 'the labels are escaped');
        self::assertStringContainsString('Aucune : réponse sans compte', $html);
        self::assertStringContainsString('Grille du volume, sinon grille par défaut', $html);
        // a grid can be created from the list for the types answered with an account only
        self::assertStringContainsString('name="request_type" value="' . self::REPORT_TYPE_ID . '"', $html);
        self::assertStringNotContainsString('name="request_type" value="' . self::LIGHT_TYPE_ID . '"', $html);
        // only the unused journal types can be deleted
        self::assertStringContainsString('name="id" value="' . self::CUSTOM_TYPE_ID . '"', $html);
        self::assertStringNotContainsString('name="id" value="' . self::REPORT_TYPE_ID . '"', $html);
    }

    public function testTheGridsTabIsShownByDefault(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $html = $this->renderList();

        self::assertMatchesRegularExpression('/id="tabpanel-grids"[^>]*class="tab-pane active"/', $html);
        self::assertMatchesRegularExpression('/id="tabpanel-types"[^>]*hidden/', $html);
        self::assertStringContainsString('Grilles par volume', $html);
    }

    public function testAnEditorSeesTheGridsWithoutTheRequestTypesTab(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $html = $this->renderList(['tab' => 'types']);

        self::assertStringNotContainsString('role="tablist"', $html);
        self::assertStringNotContainsString('/requesttype/', $html);
        self::assertStringContainsString('Grilles par volume', $html);
    }

    /**
     * @param array<string, string> $query
     */
    private function renderList(array $query = []): string
    {
        $result = $this->dispatch(\GridController::class, 'list', $this->getRequest($query));
        self::assertNull($result->redirectUrl());

        // a view of its own, given the variables set by the action: the shared view of the view renderer is left alone
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->assign(Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view->getVars());
        // the scripts of the page are not under test
        $view->registerHelper(new class extends Zend_View_Helper_Abstract {
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
        // script URLs of the page, defined by public/const.php outside of the tests
        foreach (['VENDOR_JQUERY_DATATABLES', 'VENDOR_DATATABLES_BOOTSTRAP', 'VENDOR_DATATABLES_CSS'] as $constant) {
            if (!defined($constant)) {
                define($constant, '');
            }
        }

        return $view->render('grid/list.phtml');
    }
}
